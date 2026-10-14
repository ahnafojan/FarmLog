<?php

use App\Actions\CreateUsaha;
use App\Actions\GetDashboardSummary;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\TransaksiFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('dashboard combines completed cycles and general transactions without losing matching totals', function (string $period, int $bucket, int $bucketCount) {
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, ['nama' => 'Dashboard', 'jenis_usaha' => 'lele-pembesaran']);
    $this->actingAs($user);
    $income = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();
    $operational = $usaha->kategoris()->where('klasifikasi', 'operasional')->firstOrFail();
    $investment = $usaha->kategoris()->where('klasifikasi', 'investasi')->firstOrFail();

    foreach (['A', 'B'] as $name) {
        $cycle = $usaha->sikluses()->create([
            'nama' => $name, 'tanggal_mulai' => '2025-12-01', 'populasi_awal' => 100,
        ]);
        $cycle->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-06-15'])->save();
        TransaksiFactory::new()->untukKategori($income)->create(['sikluses_id' => $cycle->id, 'tanggal' => '2025-12-15', 'total' => 50]);
        TransaksiFactory::new()->untukKategori($operational)->create(['sikluses_id' => $cycle->id, 'tanggal' => '2025-12-15', 'total' => 10]);
        TransaksiFactory::new()->untukKategori($investment)->create(['sikluses_id' => $cycle->id, 'tanggal' => '2025-12-15', 'total' => 15]);
    }
    TransaksiFactory::new()->untukKategori($income)->create(['tanggal' => '2026-06-15', 'total' => 100]);
    TransaksiFactory::new()->untukKategori($operational)->create(['tanggal' => '2026-06-15', 'total' => 20]);
    TransaksiFactory::new()->untukKategori($investment)->create(['tanggal' => '2026-06-15', 'total' => 30]);
    TransaksiFactory::new()->untukKategori($income)->create(['tanggal' => '2025-12-31', 'total' => 999]);
    TransaksiFactory::new()->untukKategori($income)->create(['tanggal' => '2027-01-01', 'total' => 999]);
    TransaksiFactory::new()->untukKategori($income)->create(['tanggal' => '2026-06-15', 'total' => 999])->delete();

    foreach ([['selesai', '2025-12-31'], ['berjalan', null]] as [$status, $finishedAt]) {
        $excluded = $usaha->sikluses()->create([
            'nama' => 'Dikecualikan', 'tanggal_mulai' => '2025-12-01', 'populasi_awal' => 100,
        ]);
        $excluded->forceFill(['status' => $status, 'tanggal_selesai' => $finishedAt])->save();
        TransaksiFactory::new()->untukKategori($income)->create(['sikluses_id' => $excluded->id, 'tanggal' => '2026-06-15', 'total' => 999]);
    }
    $deleted = $usaha->sikluses()->create([
        'nama' => 'Dihapus', 'tanggal_mulai' => '2025-12-01', 'populasi_awal' => 100,
    ]);
    $deleted->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-06-15'])->save();
    TransaksiFactory::new()->untukKategori($income)->create(['sikluses_id' => $deleted->id, 'tanggal' => '2026-06-15', 'total' => 999]);
    $deleted->delete();
    $other = app(CreateUsaha::class)->handle(User::factory()->create(), ['nama' => 'Usaha lain', 'jenis_usaha' => 'lele-pembesaran']);
    $otherCycle = $other->sikluses()->create(['nama' => 'Siklus lain', 'tanggal_mulai' => '2025-12-01', 'populasi_awal' => 100]);
    $otherCycle->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-06-15'])->save();
    TransaksiFactory::new()->untukKategori($other->kategoris()->where('arah', 'pemasukan')->firstOrFail())->count(2)
        ->sequence(['sikluses_id' => null], ['sikluses_id' => $otherCycle->id])
        ->create(['tanggal' => '2026-06-15', 'total' => 999]);
    TransaksiFactory::new()->untukKategori($income)->create(['sikluses_id' => $cycle->id, 'tanggal' => '2025-12-15', 'total' => 999])->delete();

    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
        $result = app(GetDashboardSummary::class)->handle($usaha, $period, 2026, 'siklus_selesai', 6);
        $queries = collect(DB::getQueryLog());
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($result['summary'])->toBe(['pemasukan' => 200, 'operasional' => 40, 'investasi' => 60, 'laba_bersih' => 160]);
    $incomeBuckets = array_fill(0, $bucketCount, 0);
    $incomeBuckets[$bucket] = 200;
    $profitBuckets = array_fill(0, $bucketCount, 0);
    $profitBuckets[$bucket] = 160;
    expect($result['chart']['pemasukan'])->toBe($incomeBuckets);
    expect($result['chart']['laba_bersih'])->toBe($profitBuckets);
    expect($result['running']['pemasukan'])->toBe(999);
    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'AS period_date')))->toHaveCount(1);
    if (getenv('PERF_QUERY_REPORT')) {
        fwrite(STDOUT, "\n{$period}: ".$queries->count()." queries\n");
        foreach ($queries->filter(fn (array $query): bool => str_contains($query['query'], 'AS period_date')) as $query) {
            fwrite(STDOUT, json_encode($query)."\n");
        }
    }
})->with([
    'monthly' => ['pilih_bulan', 14, 30],
    'annual' => ['pilih_tahun', 5, 12],
]);

test('completed cycle and general totals include both period boundaries', function (string $period, string $firstDay, string $lastDay, int $bucketCount) {
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, ['nama' => 'Batas periode', 'jenis_usaha' => 'lele-pembesaran']);
    $this->actingAs($user);
    $income = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();
    $start = CarbonImmutable::parse($firstDay);
    $end = CarbonImmutable::parse($lastDay);

    foreach ([$start->subDay(), $start, $end, $end->addDay()] as $date) {
        $cycle = $usaha->sikluses()->create(['nama' => 'Siklus batas', 'tanggal_mulai' => '2025-01-01', 'populasi_awal' => 100]);
        $cycle->forceFill(['status' => 'selesai', 'tanggal_selesai' => $date->toDateString()])->save();
        TransaksiFactory::new()->untukKategori($income)->create(['sikluses_id' => $cycle->id, 'tanggal' => '2025-01-01', 'total' => 100]);
        TransaksiFactory::new()->untukKategori($income)->create(['tanggal' => $date->toDateString(), 'total' => 50]);
    }

    $result = app(GetDashboardSummary::class)->handle($usaha, $period, 2026, 'siklus_selesai', 6);

    expect($result['summary'])->toBe(['pemasukan' => 300, 'operasional' => 0, 'investasi' => 0, 'laba_bersih' => 300]);
    expect($result['chart']['pemasukan'])->toBe([150, ...array_fill(0, $bucketCount - 2, 0), 150]);
    expect($result['chart']['laba_bersih'])->toBe([150, ...array_fill(0, $bucketCount - 2, 0), 150]);
})->with([
    'monthly' => ['pilih_bulan', '2026-06-01', '2026-06-30', 30],
    'annual' => ['pilih_tahun', '2026-01-01', '2026-12-31', 12],
]);

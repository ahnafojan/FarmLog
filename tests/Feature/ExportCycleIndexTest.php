<?php

use App\Actions\CreateUsaha;
use App\Actions\ExportLaporanPdf;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\TransaksiFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** ANALYZE TABLE commits transactions, so these tests use migration-based isolation. */
uses(DatabaseMigrations::class);

test('cycle export details use an index for ordering on every pdf part', function (string $jenis, string $classification) {
    config(['dompdf.report_rows_per_pdf' => 150]);
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha index ekspor', 'jenis_usaha' => 'lele-pembesaran',
    ]);
    $income = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();
    $expense = $usaha->kategoris()->where('klasifikasi', 'operasional')->firstOrFail();
    $incomeRow = TransaksiFactory::new()->untukKategori($income)->raw(['total' => 100]);
    $expenseRow = TransaksiFactory::new()->untukKategori($expense)->raw(['total' => 40]);
    $insertMilliseconds = 0;

    for ($number = 1; $number <= 6; $number++) {
        $cycle = $usaha->sikluses()->create([
            'nama' => "Siklus index {$number}", 'tanggal_mulai' => '2026-01-01', 'populasi_awal' => 100,
        ]);
        $rows = [];

        for ($row = 0; $row < 2000; $row++) {
            $rows[] = [
                ...($row % 2 === 0 ? $incomeRow : $expenseRow),
                'sikluses_id' => $cycle->id,
                'tanggal' => CarbonImmutable::parse('2026-01-01')->addDays($row % 365)->toDateString(),
                'deleted_at' => $row % 10 === 0 ? '2026-10-01 00:00:00' : null,
            ];
        }

        $startedAt = hrtime(true);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('transaksis')->insert($chunk);
        }
        $insertMilliseconds += (hrtime(true) - $startedAt) / 1_000_000;
    }

    DB::select('ANALYZE TABLE transaksis');
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $response = app(ExportLaporanPdf::class)->handle(
            $user, $usaha, $jenis, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'),
            false, $classification, $cycle->id,
        );
        $queries = DB::getQueryLog();
        $content = TestResponse::fromBaseResponse($response)->streamedContent();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    $detailQueries = collect($queries)->filter(
        fn (array $query): bool => str_starts_with($query['query'], 'select `id`, `kategoris_id`, `tanggal`'),
    )->values();
    $plans = $detailQueries->map(fn (array $query): object => collect(
        DB::select('EXPLAIN '.$query['query'], $query['bindings']),
    )->firstWhere('table', 'transaksis'));

    if (getenv('PERF_QUERY_REPORT')) {
        fwrite(STDOUT, "\n{$jenis}/{$classification}: insert 12000 rows = ".round($insertMilliseconds, 2)." ms\n");
        foreach ($plans as $part => $plan) {
            fwrite(STDOUT, 'Part '.($part + 1).': '.json_encode($plan)."\n");
        }
        $first = $detailQueries->first();
        fwrite(STDOUT, json_encode(DB::select('EXPLAIN ANALYZE '.$first['query'], $first['bindings']))."\n");
    }

    expect($detailQueries->count())->toBeGreaterThan(1);
    foreach ($plans as $plan) {
        expect((string) $plan->Extra)->not->toContain('Using filesort');
        expect($plan->type)->not->toBe('ALL');
    }
    expect($content)->toStartWith('PK');
})->with([
    'sales' => ['penjualan', 'semua'],
    'expenses' => ['pengeluaran', 'semua'],
    'operational expenses' => ['pengeluaran', 'operasional'],
]);

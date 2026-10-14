<?php

use App\Actions\CreateUsaha;
use App\Actions\ExportLaporanPdf;
use App\Models\Usaha;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\TransaksiFactory;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View as ReportView;

uses(LazilyRefreshDatabase::class);

/** @return array{0: User, 1: Usaha} */
function pdfExportOwner(): array
{
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha laporan', 'jenis_usaha' => 'lele-pembesaran',
    ]);

    return [$user, $usaha];
}

/** @return array{documents: list<array<string, mixed>>, queries: list<array<string, mixed>>, content: string} */
function capturePdfExport(Closure $export): array
{
    $documents = [];
    View::composer('filament.pages.laporan-pdf', function (ReportView $view) use (&$documents): void {
        $documents[] = $view->getData();
    });
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $response = $export();
        $queries = DB::getQueryLog();
        $content = TestResponse::fromBaseResponse($response)->streamedContent();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    return compact('documents', 'queries', 'content');
}

test('annual export aggregate queries stay bounded as cycle count grows', function (string $jenis, int $cycleCount) {
    [$user, $usaha] = pdfExportOwner();
    $category = $usaha->kategoris()->where('arah', $jenis === 'penjualan' ? 'pemasukan' : 'pengeluaran')->firstOrFail();

    for ($number = 1; $number <= $cycleCount; $number++) {
        $cycle = $usaha->sikluses()->create([
            'nama' => "Siklus {$number}", 'tanggal_mulai' => '2026-01-01',
            'umur_masuk_hari' => 0, 'populasi_awal' => 100,
        ]);
        TransaksiFactory::new()->untukKategori($category)->create([
            'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 10000,
        ]);
    }

    $result = capturePdfExport(fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, $jenis, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'), false,
    ));

    expect($result['content'])->toStartWith('PK');
    expect($result['documents'])->toHaveCount($cycleCount + 1);
    expect($result['documents'][0]['transactionCount'])->toBe($cycleCount);
    $queries = collect($result['queries'])->pluck('query');
    expect([
        'aggregate' => $queries->filter(fn (string $sql): bool => preg_match('/\b(count|sum)\s*\(/i', $sql) === 1)->count(),
        'select' => $queries->filter(fn (string $sql): bool => str_starts_with(strtolower($sql), 'select'))->count(),
    ])->toBe(['aggregate' => 3, 'select' => 2 * $cycleCount + 4]);
})->with(['penjualan', 'pengeluaran'])->with([1, 5, 10]);

test('sales archives retain cycle totals general transactions and detail pagination', function (bool $monthly) {
    config(['dompdf.report_rows_per_pdf' => 2]);
    [$user, $usaha] = pdfExportOwner();
    $sale = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();
    $operational = $usaha->kategoris()->where('klasifikasi', 'operasional')->firstOrFail();
    $investment = $usaha->kategoris()->where('klasifikasi', 'investasi')->firstOrFail();
    $cycle = $usaha->sikluses()->create([
        'nama' => 'Siklus utama', 'tanggal_mulai' => '2026-10-01', 'populasi_awal' => 100,
    ]);
    $expenseOnly = $usaha->sikluses()->create([
        'nama' => 'Belum panen', 'tanggal_mulai' => '2026-10-01', 'populasi_awal' => 100,
    ]);
    $sales = TransaksiFactory::new()->untukKategori($sale)->count(3)->sequence(
        ['tanggal' => '2026-10-02', 'total' => 100],
        ['tanggal' => '2026-10-01', 'total' => 200],
        ['tanggal' => '2026-10-01', 'total' => 300],
    )->create(['sikluses_id' => $cycle->id]);
    TransaksiFactory::new()->untukKategori($operational)->create([
        'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 40,
    ]);
    TransaksiFactory::new()->untukKategori($investment)->create([
        'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 90,
    ]);
    TransaksiFactory::new()->untukKategori($operational)->create([
        'sikluses_id' => $expenseOnly->id, 'tanggal' => '2026-10-01', 'total' => 50,
    ]);
    $general = TransaksiFactory::new()->untukKategori($sale)->create(['tanggal' => '2026-10-01', 'total' => 70]);
    TransaksiFactory::new()->untukKategori($sale)->create([
        'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 999,
    ])->delete();
    TransaksiFactory::new()->untukKategori($sale)->create([
        'sikluses_id' => $cycle->id, 'tanggal' => '2025-10-01', 'total' => 999,
    ]);
    $deletedCycle = $usaha->sikluses()->create([
        'nama' => 'Dihapus', 'tanggal_mulai' => '2026-10-01', 'populasi_awal' => 100,
    ]);
    TransaksiFactory::new()->untukKategori($sale)->create([
        'sikluses_id' => $deletedCycle->id, 'tanggal' => '2026-10-01', 'total' => 999,
    ]);
    $deletedCycle->delete();
    [, $otherUsaha] = pdfExportOwner();
    TransaksiFactory::new()->untukKategori($otherUsaha->kategoris()->where('arah', 'pemasukan')->firstOrFail())
        ->create(['tanggal' => '2026-10-01', 'total' => 999]);

    $result = capturePdfExport(fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'penjualan', CarbonImmutable::parse($monthly ? '2026-10-01' : '2026-01-01'),
        CarbonImmutable::parse($monthly ? '2026-10-31' : '2026-12-31'), $monthly,
    ));

    expect($result['content'])->toStartWith('PK');
    $documents = collect($result['documents']);
    expect($documents)->toHaveCount(5);
    expect($documents->map(fn (array $document): array => [
        $document['siklusLabel'], $document['transactionCount'], $document['totalPenjualan'],
        $document['totalOperasional'], $document['totalInvestasi'], $document['totalPengeluaran'], $document['labaBersih'],
    ])->all())->toBe([
        ['Semua siklus', 4, 670, 90, 90, 180, 580],
        ['Transaksi umum', 1, 70, 0, 0, 0, 70],
        ['Siklus utama', 3, 600, 40, 90, 130, 560],
        ['Siklus utama', 3, 600, 40, 90, 130, 560],
        ['Belum panen', 0, 0, 50, 0, 50, -50],
    ]);
    expect($documents->pluck('partLabel')->all())->toBe([null, 'Bagian 1 dari 1', 'Bagian 1 dari 2', 'Bagian 2 dari 2', 'Bagian 1 dari 1']);
    expect($documents->pluck('rowOffset')->all())->toBe([null, 0, 0, 2, 0]);
    expect($documents->flatMap(fn (array $document): array => $document['transaksis']->pluck('id')->all())->all())
        ->toBe([$general->id, $sales[1]->id, $sales[2]->id, $sales[0]->id]);
})->with(['monthly overflow' => true, 'annual archive' => false]);

test('expense archives preserve classification filters in grouped totals and details', function (string $classification, array $expected, array $includedCategories) {
    [$user, $usaha] = pdfExportOwner();
    $cycle = $usaha->sikluses()->create([
        'nama' => 'Siklus biaya', 'tanggal_mulai' => '2026-10-01', 'populasi_awal' => 100,
    ]);
    $transactions = [];

    foreach (['operasional' => 40, 'investasi' => 90] as $categoryType => $total) {
        $category = $usaha->kategoris()->where('klasifikasi', $categoryType)->firstOrFail();
        $transactions[$categoryType] = TransaksiFactory::new()->untukKategori($category)->create([
            'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => $total,
        ])->id;
    }
    TransaksiFactory::new()->untukKategori($usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail())
        ->create(['sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 600]);

    $result = capturePdfExport(fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'pengeluaran', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'),
        false, $classification,
    ));

    expect($result['content'])->toStartWith('PK');
    expect($result['documents'])->toHaveCount(2);
    foreach ($result['documents'] as $document) {
        expect([
            $document['transactionCount'], $document['totalPenjualan'], $document['totalOperasional'],
            $document['totalInvestasi'], $document['totalPengeluaran'], $document['labaBersih'],
        ])->toBe($expected);
    }
    expect($result['documents'][1]['transaksis']->modelKeys())
        ->toBe(array_map(fn (string $categoryType): int => $transactions[$categoryType], $includedCategories));
})->with([
    'all expenses' => ['semua', [2, 0, 40, 90, 130, -40], ['operasional', 'investasi']],
    'operational only' => ['operasional', [1, 0, 40, 0, 40, -40], ['operasional']],
    'investment only' => ['investasi', [1, 0, 0, 90, 90, 0], ['investasi']],
]);

test('a selected cycle remains isolated when its export exceeds one pdf', function () {
    config(['dompdf.report_rows_per_pdf' => 1]);
    [$user, $usaha] = pdfExportOwner();
    $category = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();
    $cycle = $usaha->sikluses()->create([
        'nama' => 'Siklus pilihan', 'tanggal_mulai' => '2026-10-01', 'populasi_awal' => 100,
    ]);
    $sales = TransaksiFactory::new()->untukKategori($category)->count(2)->create([
        'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 100,
    ]);
    TransaksiFactory::new()->untukKategori($category)->create(['tanggal' => '2026-10-01', 'total' => 999]);

    $result = capturePdfExport(fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'penjualan', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'),
        false, siklusId: $cycle->id,
    ));

    expect($result['documents'])->toHaveCount(3);
    foreach ($result['documents'] as $document) {
        expect($document['siklusLabel'])->toBe('Siklus pilihan');
        expect($document['transactionCount'])->toBe(2);
        expect($document['totalPenjualan'])->toBe(200);
    }
    expect($result['documents'][1]['transaksis']->modelKeys())->toBe([$sales[0]->id]);
    expect($result['documents'][2]['transaksis']->modelKeys())->toBe([$sales[1]->id]);
});

test('an empty annual export contains only its zero summary', function () {
    [$user, $usaha] = pdfExportOwner();

    $result = capturePdfExport(fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'penjualan', CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'), false,
    ));

    expect($result['content'])->toStartWith('PK');
    expect($result['documents'])->toHaveCount(1);
    $summary = $result['documents'][0];
    expect([
        $summary['transactionCount'], $summary['totalPenjualan'], $summary['totalOperasional'],
        $summary['totalInvestasi'], $summary['totalPengeluaran'], $summary['labaBersih'],
    ])->toBe([0, 0, 0, 0, 0, 0]);
    expect($summary['summaryOnly'])->toBeTrue();
    expect($summary['transaksis'])->toBeEmpty();
});

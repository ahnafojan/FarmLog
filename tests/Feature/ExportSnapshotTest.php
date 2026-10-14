<?php

use App\Actions\CreateUsaha;
use App\Actions\ExportLaporanPdf;
use App\Models\Transaksi;
use App\Models\User;
use App\Support\ExportGuard;
use Carbon\CarbonImmutable;
use Database\Factories\TransaksiFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Testing\TestResponse;
use Illuminate\View\View as ReportView;

uses(DatabaseMigrations::class);

test('pdf rendering uses a bounded snapshot after committing all report reads', function (bool $monthly, int $limit, int $documentCount) {
    config(['dompdf.report_rows_per_pdf' => $limit]);
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, ['nama' => 'Snapshot', 'jenis_usaha' => 'lele-pembesaran']);
    $cycle = $usaha->sikluses()->create(['nama' => 'Siklus awal', 'tanggal_mulai' => '2026-10-01', 'populasi_awal' => 100]);
    $category = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();
    $originalCategoryName = $category->nama;
    $sales = TransaksiFactory::new()->untukKategori($category)->count(3)->create([
        'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 100, 'qty' => '1.125', 'harga_satuan' => 89,
    ]);
    $directoriesBefore = File::glob(storage_path('app/private/laporan-exports/*'));
    $documents = [];
    $readsAfterRendering = [];
    $rendering = false;
    DB::listen(function (QueryExecuted $query) use (&$rendering, &$readsAfterRendering): void {
        if ($rendering && str_starts_with(strtolower($query->sql), 'select')) {
            $readsAfterRendering[] = $query->sql;
        }
    });
    View::composer('filament.pages.laporan-pdf', function (ReportView $view) use (&$documents, &$rendering, $sales, $cycle, $category, $limit): void {
        expect(DB::transactionLevel())->toBe(0);
        $document = $view->getData();
        expect($document['transaksis']->count())->toBeLessThanOrEqual($limit);

        if ($documents === []) {
            $sales[0]->update(['total' => 900]);
            $sales[2]->delete();
            $category->update(['nama' => 'Kategori diubah']);
            $cycle->update(['nama' => 'Siklus diubah']);
            TransaksiFactory::new()->untukKategori($category)->create([
                'sikluses_id' => $cycle->id, 'tanggal' => '2026-10-01', 'total' => 700,
            ]);
            $rendering = true;
        }

        $documents[] = $document;
    });

    $response = app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'penjualan', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), $monthly,
    );
    $content = TestResponse::fromBaseResponse($response)->streamedContent();

    expect($content)->toStartWith($documentCount === 1 ? '%PDF-' : 'PK');
    expect($documents)->toHaveCount($documentCount);
    foreach ($documents as $document) {
        expect($document['totalPenjualan'])->toBe(300);
        expect($document['transactionCount'])->toBe(3);
    }
    $rows = collect($documents)->flatMap(fn (array $document) => $document['transaksis']);
    expect($rows->pluck('id')->all())->toBe($sales->modelKeys());
    expect($rows->pluck('total')->all())->toBe([100, 100, 100]);
    expect($rows->pluck('qty')->all())->toBe(['1.125', '1.125', '1.125']);
    expect($rows->map(fn (Transaksi $row): string => $row->tanggal->toDateString())->all())->toBe(['2026-10-01', '2026-10-01', '2026-10-01']);
    expect($rows->pluck('kategori.nama')->unique()->all())->toBe([$originalCategoryName]);
    expect(collect($documents)->pluck('siklusLabel')->unique()->values()->all())
        ->toBe($documentCount === 1 ? ['Semua siklus'] : ['Semua siklus', 'Siklus awal']);
    expect($readsAfterRendering)->toBeEmpty();
    expect(DB::transactionLevel())->toBe(0);
    expect(File::glob(storage_path('app/private/laporan-exports/*')))->toBe($directoriesBefore);
})->with([
    'single pdf' => [true, 3, 1],
    'monthly archive' => [true, 2, 3],
    'annual archive' => [false, 2, 3],
]);

test('a failed snapshot rolls back before rendering and removes temporary files', function () {
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, ['nama' => 'Snapshot', 'jenis_usaha' => 'lele-pembesaran']);
    $category = $usaha->kategoris()->where('arah', 'pemasukan')->firstOrFail();
    TransaksiFactory::new()->untukKategori($category)->create(['tanggal' => '2026-10-01']);
    $directoriesBefore = File::glob(storage_path('app/private/laporan-exports/*'));
    View::composer('filament.pages.laporan-pdf', function (): void {
        test()->fail('PDF tidak boleh dirender setelah snapshot gagal.');
    });
    DB::listen(function (QueryExecuted $query): void {
        if (str_starts_with($query->sql, 'select `id`, `kategoris_id`, `tanggal`')) {
            expect(DB::transactionLevel())->toBe(1);

            throw new RuntimeException('Pembacaan detail gagal.');
        }
    });

    expect(fn () => app(ExportGuard::class)->run($user->id, fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'penjualan', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), false,
    )))->toThrow(RuntimeException::class, 'Pembacaan detail gagal.');

    expect(DB::transactionLevel())->toBe(0);
    expect(File::glob(storage_path('app/private/laporan-exports/*')))->toBe($directoriesBefore);
    expect(Cache::lock("export:user:{$user->id}", 180)->get(fn (): bool => true))->toBeTrue();
    expect(Cache::lock('export:slot:1', 180)->get(fn (): bool => true))->toBeTrue();
});

test('a rendering failure leaves no open transaction temporary files or export locks', function () {
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, ['nama' => 'Snapshot', 'jenis_usaha' => 'lele-pembesaran']);
    $directoriesBefore = File::glob(storage_path('app/private/laporan-exports/*'));
    View::composer('filament.pages.laporan-pdf', function (): void {
        expect(DB::transactionLevel())->toBe(0);

        throw new RuntimeException('Rendering PDF gagal.');
    });

    expect(fn () => app(ExportGuard::class)->run($user->id, fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'penjualan', CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'), false,
    )))->toThrow(RuntimeException::class, 'Rendering PDF gagal.');

    expect(DB::transactionLevel())->toBe(0);
    expect(File::glob(storage_path('app/private/laporan-exports/*')))->toBe($directoriesBefore);
    expect(Cache::lock("export:user:{$user->id}", 180)->get(fn (): bool => true))->toBeTrue();
    expect(Cache::lock('export:slot:1', 180)->get(fn (): bool => true))->toBeTrue();
});

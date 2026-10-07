<?php

use App\Actions\CreateSiklus;
use App\Actions\CreateUsaha;
use App\Actions\ExportLaporanPdf;
use App\Actions\SaveTransaksi;
use App\Filament\Pages\Laporan;
use App\Models\Siklus;
use App\Models\TemplateUsaha;
use App\Models\Usaha;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\TemplateKategoriSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View as ViewInstance;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Gunakan database PostgreSQL khusus pengujian untuk migration aplikasi.');
    }

    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant(null);
});

/** @return array{0: User, 1: Usaha} */
function laporanOwner(): array
{
    test()->seed(TemplateKategoriSeeder::class);
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha Laporan',
        'template_usahas_id' => TemplateUsaha::query()->where('kode', 'lele-pembesaran')->sole()->id,
    ]);
    test()->actingAs($user);
    Filament::setTenant($usaha);
    Filament::bootCurrentPanel();

    return [$user, $usaha];
}

function laporanCycle(User $user, Usaha $usaha, string $nama = 'Kolam A'): Siklus
{
    return app(CreateSiklus::class)->handle($user, $usaha, [
        'nama' => $nama, 'tanggal_mulai' => '2026-10-01',
        'umur_masuk_hari' => 0, 'populasi_awal' => 10, 'harga_bibit' => 100,
    ]);
}

test('report filter selects running and completed cycles and can return to all cycles', function (bool $completed) {
    [$user, $usaha] = laporanOwner();
    $siklus = laporanCycle($user, $usaha);

    if ($completed) {
        $siklus->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-10-05'])->save();
    }

    Livewire::test(Laporan::class)
        ->assertSet('filters.sikluses_id', null)
        ->callAction('filter', data: [
            'periode' => 'bulanan', 'tahun' => 2026, 'bulan' => 10, 'sikluses_id' => $siklus->id,
        ])
        ->assertHasNoActionErrors()
        ->assertSet('filters.sikluses_id', $siklus->id)
        ->assertSee('Kolam A')
        ->assertSee('Oktober 2026')
        ->callAction('exportPenjualan')
        ->assertFileDownloaded('laporan-penjualan-2026-10-siklus-'.$siklus->id.'.pdf')
        ->callAction('exportPengeluaran', data: ['klasifikasi' => 'operasional'])
        ->assertFileDownloaded('laporan-pengeluaran-operasional-2026-10-siklus-'.$siklus->id.'.pdf')
        ->callAction('filter', data: [
            'periode' => 'tahunan', 'tahun' => 2026, 'sikluses_id' => null,
        ])
        ->assertHasNoActionErrors()
        ->assertSet('filters.sikluses_id', null)
        ->assertSee('Semua siklus (termasuk transaksi umum)')
        ->assertSee('Tahun 2026');
})->with(['running' => false, 'completed' => true]);

test('report filter rejects cycles unavailable to the current business', function (string $unavailable) {
    [$user, $usaha] = laporanOwner();
    $siklus = laporanCycle($user, $usaha);

    if ($unavailable === 'other business') {
        Filament::setTenant(null);
        $other = app(CreateUsaha::class)->handle($user, [
            'nama' => 'Usaha Lain', 'template_usahas_id' => $usaha->template_usahas_id,
        ]);
        Filament::setTenant($other);
        $siklus = laporanCycle($user, $other);
        Filament::setTenant($usaha);
    } else {
        $siklus->delete();
    }

    Livewire::test(Laporan::class)
        ->callAction('filter', data: [
            'periode' => 'bulanan', 'tahun' => 2026, 'bulan' => 10, 'sikluses_id' => $siklus->id,
        ])
        ->assertHasActionErrors(['sikluses_id'])
        ->assertSet('filters.sikluses_id', null);
})->with(['other business', 'deleted']);

test('exports apply cycle period and expense classification to details and totals', function (
    string $jenis,
    string $klasifikasi,
    bool $selected,
    string $start,
    string $end,
    bool $monthly,
    int $sales,
    int $operational,
    int $investment,
    int $rows,
) {
    [$user, $usaha] = laporanOwner();
    $siklus = laporanCycle($user, $usaha);
    $other = laporanCycle($user, $usaha, 'Kolam B');
    $saleCategory = $usaha->kategoris()->where('arah', 'pemasukan')->sole();

    foreach ([[$siklus, '2026-10-05', 10000], [$other, '2026-10-05', 20000], [$siklus, '2026-11-01', 40000]] as [$cycle, $date, $total]) {
        app(SaveTransaksi::class)->handle($user, $usaha, [
            'arah' => 'pemasukan', 'tanggal' => $date,
            'kategoris_id' => $saleCategory->id, 'sikluses_id' => $cycle->id,
            'qty' => 1, 'satuan' => 'kg', 'harga_satuan' => $total, 'total' => $total,
        ]);
    }

    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-10-05', 'total' => 700,
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Listrik/air')->sole()->id,
    ]);
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-10-05', 'total' => 3000,
        'kategoris_id' => $usaha->kategoris()->where('klasifikasi', 'investasi')->sole()->id,
        'sikluses_id' => $siklus->id,
    ]);
    $report = [];
    View::composer('filament.pages.laporan-pdf', function (ViewInstance $view) use (&$report): void {
        $report = $view->getData();
    });

    $response = app(ExportLaporanPdf::class)->handle(
        $user, $usaha, $jenis, CarbonImmutable::parse($start), CarbonImmutable::parse($end),
        $monthly, $klasifikasi, $selected ? $siklus->id : null,
    );

    expect($report)->toMatchArray([
        'totalPenjualan' => $sales,
        'totalOperasional' => $operational,
        'totalInvestasi' => $investment,
        'labaBersih' => $sales - $operational,
        'totalPengeluaran' => $jenis === 'pengeluaran' ? $operational + $investment : 0,
        'siklusLabel' => $selected ? 'Kolam A' : 'Semua siklus (termasuk transaksi umum)',
    ]);
    expect($report['transaksis'])->toHaveCount($rows);
    expect($response->headers->get('Content-Type'))->toBe('application/pdf');

    if ($selected && $rows > 0) {
        expect($report['transaksis']->pluck('sikluses_id')->unique()->all())->toBe([$siklus->id]);
    }
})->with([
    'selected sales' => ['penjualan', 'semua', true, '2026-10-01', '2026-10-31', true, 10000, 1000, 3000, 1],
    'all sales including general expenses' => ['penjualan', 'semua', false, '2026-10-01', '2026-10-31', true, 30000, 2700, 3000, 2],
    'selected expenses' => ['pengeluaran', 'semua', true, '2026-10-01', '2026-10-31', true, 0, 1000, 3000, 2],
    'selected operational expenses' => ['pengeluaran', 'operasional', true, '2026-10-01', '2026-10-31', true, 0, 1000, 0, 1],
    'selected investments' => ['pengeluaran', 'investasi', true, '2026-10-01', '2026-10-31', true, 0, 0, 3000, 1],
    'all expenses including general expenses' => ['pengeluaran', 'semua', false, '2026-10-01', '2026-10-31', true, 0, 2700, 3000, 4],
    'selected annual sales' => ['penjualan', 'semua', true, '2026-01-01', '2026-12-31', false, 50000, 1000, 3000, 2],
    'selected cycle without transactions in period' => ['penjualan', 'semua', true, '2027-10-01', '2027-10-31', true, 0, 0, 0, 0],
]);

test('export rejects a cycle deleted after the filter was applied', function () {
    [$user, $usaha] = laporanOwner();
    $siklus = laporanCycle($user, $usaha);
    $page = Livewire::test(Laporan::class)
        ->callAction('filter', data: [
            'periode' => 'bulanan', 'tahun' => 2026, 'bulan' => 10, 'sikluses_id' => $siklus->id,
        ])
        ->assertHasNoActionErrors();
    $siklus->delete();

    $page->callAction('exportPenjualan')
        ->assertHasActionErrors(['sikluses_id'])
        ->assertNoFileDownloaded();
});

test('export rejects a cycle belonging to another business even when called directly', function () {
    [$user, $usaha] = laporanOwner();
    Filament::setTenant(null);
    $other = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha Lain', 'template_usahas_id' => $usaha->template_usahas_id,
    ]);
    Filament::setTenant($other);
    $siklus = laporanCycle($user, $other);
    Filament::setTenant($usaha);

    expect(fn () => app(ExportLaporanPdf::class)->handle(
        $user, $usaha, 'penjualan', CarbonImmutable::parse('2026-10-01'),
        CarbonImmutable::parse('2026-10-31'), true, siklusId: $siklus->id,
    ))->toThrow(ValidationException::class, 'Pilih siklus yang tersedia dari usaha ini.');
});

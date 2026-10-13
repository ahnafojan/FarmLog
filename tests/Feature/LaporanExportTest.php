<?php

use App\Actions\CreateUsaha;
use App\Filament\Pages\Laporan;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

test('export labels render valid Alpine visibility expressions for icons and text', function (string $jenis) {
    $this->view('filament.pages.export-button-label', [
        'label' => 'Export laporan',
        'jenis' => $jenis,
    ])->assertDontSee('@js(', false)
        ->assertSee("activeExport === '{$jenis}'", false)
        ->assertSee("activeExport !== '{$jenis}'", false);
})->with(['penjualan', 'pengeluaran']);

test('export actions return the filtered download URL without leaving the page', function (string $action, string $jenis, array $data, string $klasifikasi) {
    $this->travelTo(now()->setDate(2026, 10, 12));
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha laporan', 'jenis_usaha' => 'lele-pembesaran',
    ]);
    $this->actingAs($user);
    Filament::setTenant($usaha);
    Filament::bootCurrentPanel();
    $page = Livewire::test(Laporan::class);
    $page->callAction('filter', [
        'periode' => 'bulanan', 'tahun' => 2025, 'bulan' => 6, 'sikluses_id' => null,
    ]);

    $page->callAction($action, $data)
        ->assertHasNoActionErrors()
        ->assertNoRedirect()
        ->assertReturned(route('laporan.download', [
            'usaha' => $usaha->slug, 'jenis' => $jenis, 'klasifikasi' => $klasifikasi,
            'periode' => 'bulanan', 'tahun' => 2025, 'bulan' => 6,
        ]));
})->with([
    'sales' => ['exportPenjualan', 'penjualan', [], 'semua'],
    'expenses' => ['exportPengeluaran', 'pengeluaran', ['klasifikasi' => 'investasi'], 'investasi'],
]);

test('expense export rejects an invalid classification before starting a download', function () {
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha laporan', 'jenis_usaha' => 'lele-pembesaran',
    ]);
    $this->actingAs($user);
    Filament::setTenant($usaha);
    Filament::bootCurrentPanel();

    Livewire::test(Laporan::class)
        ->callAction('exportPengeluaran', ['klasifikasi' => 'invalid'])
        ->assertHasActionErrors(['klasifikasi' => 'in'])
        ->assertNoRedirect();
});

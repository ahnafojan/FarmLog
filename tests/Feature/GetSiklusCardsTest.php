<?php

use App\Actions\CreateSiklus;
use App\Actions\CreateUsaha;
use App\Actions\GetSiklusCards;
use App\Actions\SaveTransaksi;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Sikluses\Pages\ListSikluses;
use App\Models\Siklus;
use App\Models\Usaha;
use App\Models\User;
use Database\Factories\TransaksiFactory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Livewire::withoutLazyLoading();
    $this->travelTo(now()->setDate(2026, 10, 13)->startOfDay());
});

/** @return array{0: User, 1: Usaha, 2: Siklus} */
function populationCycle(string $template = 'ayam-petelur'): array
{
    Filament::setTenant(null);
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha populasi', 'jenis_usaha' => $template,
    ]);
    test()->actingAs($user);
    Filament::setTenant($usaha);
    Filament::bootCurrentPanel();
    $cycle = app(CreateSiklus::class)->handle($user, $usaha, [
        'nama' => 'Siklus populasi', 'tanggal_mulai' => '2026-10-01',
        'umur_masuk_hari' => 0, 'populasi_awal' => 1000, 'harga_bibit' => 500,
    ]);

    return [$user, $usaha, $cycle];
}

test('animal sales reduce the remaining population in list and dashboard cards', function (string $template, string $categoryName, string $unit) {
    [$user, $usaha, $cycle] = populationCycle($template);
    $category = $usaha->kategoris()->where('nama', $categoryName)->sole();

    foreach ([25, 35] as $quantity) {
        app(SaveTransaksi::class)->handle($user, $usaha, [
            'arah' => 'pemasukan', 'tanggal' => '2026-10-13',
            'kategoris_id' => $category->id, 'sikluses_id' => $cycle->id,
            'qty' => $quantity, 'satuan' => $unit, 'harga_satuan' => 1000, 'total' => $quantity * 1000,
        ]);
    }

    $cards = app(GetSiklusCards::class);
    expect($cards->handle($usaha, 'berjalan')['cycles']->first()['jumlah'])->toEqual(940);
    expect($cards->latestRunning($usaha)['jumlah'])->toEqual(940);
    expect($cycle->fresh()->populasi_awal)->toBe(1000);
})->with([
    'culled hens' => ['ayam-petelur', 'Penjualan ayam afkir', 'ekor'],
    'mixed case and spaces' => ['ayam-petelur', 'Penjualan ayam afkir', ' Ekor '],
    'fish fry' => ['lele-pendederan', 'Penjualan benih', 'ekor'],
]);

test('cycle cards immediately refresh after entering an animal sale', function (string $pageClass) {
    [, $usaha, $cycle] = populationCycle();
    $category = $usaha->kategoris()->where('nama', 'Penjualan ayam afkir')->sole();
    $page = Livewire::test($pageClass)->assertSee('1.000 ekor');

    $page->callAction('catat', [
        'arah' => 'pemasukan', 'tanggal' => '2026-10-13',
        'kategoris_id' => $category->id, 'sikluses_id' => $cycle->id,
        'qty' => 25, 'satuan' => 'ekor', 'harga_satuan' => 1000, 'total' => 25000,
    ])->assertHasNoActionErrors()
        ->assertSee('Populasi tersisa')->assertSee('975 ekor');
})->with([
    'dashboard' => [Dashboard::class],
    'cycle list' => [ListSikluses::class],
]);

test('population excludes expenses other units deleted sales and other cycles', function () {
    [, $usaha, $cycle] = populationCycle();
    [, $otherUsaha, $otherCycle] = populationCycle();
    TransaksiFactory::new()->untukKategori($otherUsaha->kategoris()->where('nama', 'Penjualan ayam afkir')->sole())->create([
        'sikluses_id' => $otherCycle->id, 'qty' => 300, 'satuan' => 'ekor',
    ]);
    $this->actingAs($usaha->user);
    Filament::setTenant($usaha);
    $otherLocalCycle = $usaha->sikluses()->create([
        'nama' => 'Siklus lain', 'tanggal_mulai' => '2026-09-01',
        'umur_masuk_hari' => 0, 'populasi_awal' => 2000,
    ]);
    $saleCategory = $usaha->kategoris()->where('nama', 'Penjualan ayam afkir')->sole();
    $eggCategory = $usaha->kategoris()->where('nama', 'Penjualan telur')->sole();
    TransaksiFactory::new()->untukKategori($eggCategory)->create([
        'sikluses_id' => $cycle->id, 'qty' => 100, 'satuan' => 'kg',
    ]);
    TransaksiFactory::new()->untukKategori($saleCategory)->create([
        'sikluses_id' => $otherLocalCycle->id, 'qty' => 50, 'satuan' => 'ekor',
    ]);
    TransaksiFactory::new()->untukKategori($saleCategory)->create([
        'sikluses_id' => $cycle->id, 'qty' => 75, 'satuan' => 'ekor',
    ])->delete();
    $cards = app(GetSiklusCards::class);
    expect($cards->handle($usaha, 'berjalan')['cycles']->first()['jumlah'])->toEqual(1000);
    expect($cards->latestRunning($usaha)['jumlah'])->toEqual(1000);
});

test('population follows edited deleted restored and reassigned sales', function () {
    [$user, $usaha, $cycle] = populationCycle();
    $category = $usaha->kategoris()->where('nama', 'Penjualan ayam afkir')->sole();
    $data = [
        'arah' => 'pemasukan', 'tanggal' => '2026-10-13',
        'kategoris_id' => $category->id, 'sikluses_id' => $cycle->id,
        'qty' => 10, 'satuan' => 'ekor', 'harga_satuan' => 1000, 'total' => 10000,
    ];
    $save = app(SaveTransaksi::class);
    $sale = $save->handle($user, $usaha, $data);
    $cards = app(GetSiklusCards::class);

    $save->handle($user, $usaha, [...$data, 'qty' => 25, 'total' => 25000], $sale);
    expect($cards->latestRunning($usaha)['jumlah'])->toEqual(975);
    $sale->delete();
    expect($cards->latestRunning($usaha)['jumlah'])->toEqual(1000);
    $sale->restore();
    expect($cards->latestRunning($usaha)['jumlah'])->toEqual(975);

    $otherCycle = $usaha->sikluses()->create([
        'nama' => 'Siklus lain', 'tanggal_mulai' => '2026-09-01',
        'umur_masuk_hari' => 0, 'populasi_awal' => 2000,
    ]);
    $save->handle($user, $usaha, [...$data, 'sikluses_id' => $otherCycle->id], $sale);
    $populations = $cards->handle($usaha, 'berjalan')['cycles']->getCollection()->keyBy('id');
    expect($populations[$cycle->id]['jumlah'])->toEqual(1000);
    expect($populations[$otherCycle->id]['jumlah'])->toEqual(1990);
});

test('remaining population never goes below zero and is also shown for finished cycles', function () {
    [, $usaha, $cycle] = populationCycle();
    $category = $usaha->kategoris()->where('nama', 'Penjualan ayam afkir')->sole();
    TransaksiFactory::new()->untukKategori($category)->create([
        'sikluses_id' => $cycle->id, 'qty' => 1100, 'satuan' => 'ekor',
    ]);
    $cycle->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-10-13'])->save();

    $cards = app(GetSiklusCards::class)->handle($usaha, 'selesai');

    expect($cards['cycles']->first()['jumlah'])->toEqual(0);
    expect($cycle->fresh()->populasi_awal)->toBe(1000);
});

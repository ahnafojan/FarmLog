<?php

use App\Actions\CreateSiklus;
use App\Actions\CreateUsaha;
use App\Actions\GetDashboardSummary;
use App\Actions\SaveTransaksi;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Transaksis\Pages\ManageTransaksis;
use App\Models\Siklus;
use App\Models\TemplateUsaha;
use App\Models\Usaha;
use App\Models\User;
use Database\Seeders\TemplateKategoriSeeder;
use Database\Seeders\TemplatePenandaSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Gunakan database PostgreSQL khusus pengujian untuk migration aplikasi.');
    }

    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
});

/** @return array{0: User, 1: Usaha} */
function dashboardOwner(string $template = 'lele-pembesaran'): array
{
    test()->seed([TemplateKategoriSeeder::class, TemplatePenandaSeeder::class]);
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Lele Pak Budi',
        'template_usahas_id' => TemplateUsaha::query()->where('kode', $template)->sole()->id,
    ]);
    test()->actingAs($user);
    Filament::setTenant($usaha);
    Filament::bootCurrentPanel();

    return [$user, $usaha];
}

function dashboardCycle(User $user, Usaha $usaha): Siklus
{
    return app(CreateSiklus::class)->handle($user, $usaha, [
        'nama' => 'Kolam A — September 2026',
        'tanggal_mulai' => '2026-09-01',
        'umur_masuk_hari' => 0,
        'populasi_awal' => 2000,
        'harga_bibit' => 500,
    ]);
}

test('dashboard renders empty states and navigation without a cycle', function () {
    [, $usaha] = dashboardOwner();

    $this->get(Dashboard::getUrl(tenant: $usaha))
        ->assertOk()
        ->assertSee('Belum ada siklus berjalan')
        ->assertSee('Belum ada transaksi pada periode ini')
        ->assertSee('Buat siklus')
        ->assertSee('Navigasi utama');
});

test('tenant pages share mobile navigation with the correct active menu', function (string $path, ?string $activeLabel) {
    [, $usaha] = dashboardOwner();

    $response = $this->get('/app/'.$usaha->id.$path);
    $response->assertOk()->assertSee('Ganti usaha')->assertSee('Navigasi utama');

    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $activeLinks = $xpath->query('//nav[@aria-label="Navigasi utama"]/a[@aria-current="page"]');

    expect($xpath->query('//nav[@aria-label="Navigasi utama"]')->length)->toBe(1);
    expect($activeLinks->length)->toBe($activeLabel === null ? 0 : 1);

    if ($activeLabel !== null) {
        expect(trim($activeLinks->item(0)->textContent))->toBe($activeLabel);
    }
})->with([
    'dashboard' => ['', 'Beranda'],
    'transactions' => ['/transaksis', 'Transaksi'],
    'cycles' => ['/sikluses', 'Siklus'],
    'create cycle' => ['/sikluses/create', 'Siklus'],
    'categories' => ['/kategoris', null],
    'tenant settings' => ['/profile', null],
]);

test('tenant registration does not render mobile tenant navigation', function () {
    $this->actingAs(User::factory()->create());
    Filament::setTenant(null);

    $this->get('/app/new')->assertOk()->assertDontSee('Navigasi utama')->assertDontSee('Ganti usaha');
});

test('dashboard and transaction list use the same transaction modal', function (string $page, string $action) {
    dashboardOwner();

    Livewire::test($page)
        ->mountAction($action)
        ->assertSee('Catat transaksi')
        ->assertSee('Simpan')
        ->assertSee('Kategori')
        ->assertSee('Total');
})->with([
    'dashboard' => [Dashboard::class, 'catat'],
    'transactions' => [ManageTransaksis::class, 'create'],
]);

test('dashboard requires authentication and rejects another owners usaha', function () {
    [, $usaha] = dashboardOwner();
    $this->actingAs(User::factory()->create());

    $this->get(Dashboard::getUrl(tenant: $usaha))->assertNotFound();
});

test('guests cannot open the dashboard', function () {
    $this->get('/app/1')->assertRedirect(route('filament.app.auth.login'));
});

test('dashboard keeps current profit calculations and persists reporting basis', function () {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pemasukan', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('arah', 'pemasukan')->sole()->id,
        'sikluses_id' => $cycle->id, 'qty' => 10, 'satuan' => 'kg',
        'harga_satuan' => 200000, 'total' => 2000000,
    ]);

    $page = Livewire::test(Dashboard::class)
        ->assertSee('Laba sementara siklus berjalan')
        ->set('filters.dasar', 'tanggal_transaksi')
        ->assertHasNoErrors()
        ->assertDontSee('Laba sementara siklus berjalan')
        ->assertSee('Rp1.000.000');

    expect($page->get('dashboardData')['summary'])->toBe([
        'pemasukan' => 2000000, 'operasional' => 1000000, 'investasi' => 0, 'laba_bersih' => 1000000,
    ]);
    $this->assertDatabaseHas('usahas', ['id' => $usaha->id, 'rekap_dasar' => 'tanggal_transaksi']);
});

test('dashboard changes period and validates the custom year', function () {
    [$user, $usaha] = dashboardOwner();
    dashboardCycle($user, $usaha);

    Livewire::test(Dashboard::class)
        ->set('filters.dasar', 'tanggal_transaksi')
        ->set('filters.periode', 'pilih_tahun')
        ->set('filters.tahun', 2025)
        ->assertHasNoErrors()
        ->assertSee('Belum ada transaksi pada periode ini')
        ->set('filters.tahun', 2026)
        ->assertSee('Benih')
        ->set('filters.tahun', 10000)
        ->assertHasErrors(['filters.tahun' => 'between']);
});

test('dashboard catat saves a transaction and refreshes the summary', function () {
    [, $usaha] = dashboardOwner();

    Livewire::test(Dashboard::class)
        ->callAction('catat', data: [
            'arah' => 'pengeluaran', 'tanggal' => '2026-09-30',
            'kategoris_id' => $usaha->kategoris()->where('nama', 'Listrik/air')->sole()->id,
            'sikluses_id' => null, 'total' => 150000,
        ])
        ->assertHasNoActionErrors()
        ->assertSee('Listrik/air')
        ->assertSee('Rp150.000')
        ->assertSee('Rugi');

    $this->assertDatabaseHas('transaksis', ['usahas_id' => $usaha->id, 'total' => 150000, 'sikluses_id' => null]);
});

test('nearest schedule is separate from the harvest estimate and excludes completed milestones', function () {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    $cycle->penandas()->where('jenis', 'panen')->update(['tanggal' => '2026-10-20']);
    $cycle->penandas()->make()->forceFill([
        'nama' => 'Penyortiran ukuran', 'jenis' => 'pengingat', 'tanggal' => '2026-10-05',
    ])->save();
    $cycle->penandas()->make()->forceFill([
        'nama' => 'Jadwal selesai', 'jenis' => 'pengingat', 'tanggal' => '2026-10-01', 'selesai_at' => now(),
    ])->save();

    $data = app(GetDashboardSummary::class)->handle($usaha, 'bulan_ini', 2026, 'siklus_selesai');

    expect($data['cycle'])->toMatchArray(['penanda' => 'Penyortiran ukuran', 'sisa_hari' => 5]);
    expect($data['cycle']['panen'])->toMatchArray(['nama' => 'Perkiraan panen', 'sisa_hari' => 20, 'progres' => 59]);

    Livewire::test(Dashboard::class)->assertSee('Penyortiran ukuran')->assertSee('Perkiraan panen')->assertSee('20 hari lagi');
});

test('harvest progress handles overdue today and zero duration dates', function (string $date, int $days, int $progress) {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    $cycle->penandas()->where('jenis', 'panen')->update(['tanggal' => $date]);

    $data = app(GetDashboardSummary::class)->handle($usaha, 'bulan_ini', 2026, 'siklus_selesai');

    expect($data['cycle']['panen'])->toMatchArray(['sisa_hari' => $days, 'progres' => $progress]);
})->with([
    'overdue' => ['2026-09-25', -5, 100],
    'today' => ['2026-09-30', 0, 100],
    'zero duration' => ['2026-09-01', -29, 100],
]);

test('completed harvest estimates are not displayed', function () {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    $cycle->penandas()->where('jenis', 'panen')->update(['selesai_at' => now()]);

    $data = app(GetDashboardSummary::class)->handle($usaha, 'bulan_ini', 2026, 'siklus_selesai');

    expect($data['cycle']['panen'])->toBeNull();
    Livewire::test(Dashboard::class)->assertSee('Belum ada jadwal berikutnya')->assertDontSee('Progres waktu menuju');
});

test('harvest label follows the usaha template', function (string $template, string $label) {
    [$user, $usaha] = dashboardOwner($template);
    dashboardCycle($user, $usaha);

    Livewire::test(Dashboard::class)->assertSee($label);
})->with([
    'nursery' => ['lele-pendederan', 'Perkiraan jual'],
    'layers' => ['ayam-petelur', 'Perkiraan afkir'],
]);

<?php

use App\Actions\CreateSiklus;
use App\Actions\CreateUsaha;
use App\Actions\GetDashboardSummary;
use App\Actions\SaveTransaksi;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Kategoris\Pages\ManageKategoris;
use App\Filament\Resources\Sikluses\Pages\ListSikluses;
use App\Filament\Resources\Transaksis\Pages\ManageTransaksis;
use App\Filament\Widgets\RevenueProfitChart;
use App\Models\Siklus;
use App\Models\Usaha;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->travelTo(now()->setDate(2026, 9, 30)->startOfDay());
});

/** @return array{0: User, 1: Usaha} */
function dashboardOwner(string $template = 'lele-pembesaran'): array
{
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Lele Pak Budi',
        'jenis_usaha' => $template,
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
        ->assertSeeLivewire(RevenueProfitChart::class)
        ->assertSee('Belum ada siklus berjalan')
        ->assertSee('Belum ada transaksi pada periode ini')
        ->assertSee('Buat siklus')
        ->assertSee('Navigasi utama');
});

test('tenant pages share mobile navigation with the correct active menu', function (string $path, ?string $activeLabel) {
    [, $usaha] = dashboardOwner();

    $response = $this->get('/app/'.$usaha->slug.$path);
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

test('main pages render one floating button and the same transaction modal', function (string $page) {
    dashboardOwner();

    $component = Livewire::test($page);
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $buttons = (new DOMXPath($document))->query('//button[contains(concat(" ", normalize-space(@class), " "), " farm-catat ")]');

    expect($buttons->length)->toBe(1);
    expect(trim($buttons->item(0)->textContent))->toBe('Catat');

    $component
        ->mountAction('catat')
        ->assertActionMounted('catat')
        ->call('forceRender')
        ->assertSee('Catat transaksi')
        ->assertSee('Simpan')
        ->assertSee('Kategori')
        ->assertSee('Total');
})->with([
    'dashboard' => Dashboard::class,
    'cycles' => ListSikluses::class,
    'transactions' => ManageTransaksis::class,
    'categories' => ManageKategoris::class,
    'profile' => EditProfile::class,
]);

test('resource pages can save transactions from the floating action', function (string $page) {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    $category = $usaha->kategoris()->where('nama', 'Listrik/air')->sole();

    Livewire::test($page)
        ->callAction('catat', data: [
            'arah' => 'pengeluaran', 'tanggal' => '2026-09-30',
            'kategoris_id' => $category->id, 'sikluses_id' => $cycle->id, 'total' => 150000,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Transaksi berhasil dicatat');

    $this->assertDatabaseHas('transaksis', [
        'usahas_id' => $usaha->id, 'kategoris_id' => $category->id,
        'sikluses_id' => $cycle->id, 'total' => 150000,
    ]);
})->with([
    'cycles' => ListSikluses::class,
    'transactions' => ManageTransaksis::class,
    'categories' => ManageKategoris::class,
]);

test('profile transaction uses the selected owned business on subsequent requests', function () {
    [$user, $usaha] = dashboardOwner();
    Filament::setTenant(null);
    $secondUsaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha Kedua', 'jenis_usaha' => $usaha->jenis_usaha,
    ]);
    $category = $secondUsaha->kategoris()->where('nama', 'Listrik/air')->sole();
    Filament::setTenant(null);

    $component = Livewire::withQueryParams(['tenant' => $secondUsaha->slug])
        ->test(EditProfile::class)
        ->mountAction('catat')
        ->assertActionMounted('catat');

    Filament::setTenant(null);

    $component
        ->fillForm([
            'arah' => 'pengeluaran', 'tanggal' => '2026-09-30',
            'kategoris_id' => $category->id, 'sikluses_id' => null, 'total' => 150000,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Transaksi berhasil dicatat');

    $this->assertDatabaseHas('transaksis', ['usahas_id' => $secondUsaha->id, 'total' => 150000]);
    $this->assertDatabaseMissing('transaksis', ['usahas_id' => $usaha->id, 'total' => 150000]);
});

test('dashboard requires authentication and rejects another owners usaha', function () {
    [, $usaha] = dashboardOwner();
    $this->actingAs(User::factory()->create());

    $this->get(Dashboard::getUrl(tenant: $usaha))->assertNotFound();
});

test('guests cannot open the dashboard', function () {
    $this->get('/app/lele-pak-budi')->assertRedirect(route('filament.app.auth.login'));
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

    $page = Livewire::test(Dashboard::class)
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
    expect($page->get('dashboardData')['chart']['laba_bersih'][29])->toBe(-150000);
});

test('selected month filters summary chart and recent transactions across month boundaries', function (string $firstDay, string $lastDay, int $days) {
    [$user, $usaha] = dashboardOwner();
    $start = CarbonImmutable::parse($firstDay);
    $end = CarbonImmutable::parse($lastDay);
    $category = $usaha->kategoris()->where('nama', 'Listrik/air')->sole();
    $records = [];

    foreach ([$start->subDay(), $start, $end, $end->addDay(), $start->subYear()] as $date) {
        $records[] = app(SaveTransaksi::class)->handle($user, $usaha, [
            'arah' => 'pengeluaran', 'tanggal' => $date->toDateString(),
            'kategoris_id' => $category->id, 'total' => 150000,
        ]);
    }

    $page = Livewire::test(Dashboard::class)
        ->set('filters.dasar', 'tanggal_transaksi')
        ->set('filters.periode', 'pilih_bulan')
        ->set('filters.tahun', $start->year)
        ->set('filters.bulan', (string) $start->month)
        ->assertHasNoErrors()
        ->assertSee('Januari')
        ->assertSee('Desember');
    $data = $page->get('dashboardData');

    expect($data['summary']['laba_bersih'])->toBe(-300000);
    expect($data['chart']['labels'])->toHaveCount($days);
    expect($data['chart']['laba_bersih'])->toBe([-150000, ...array_fill(0, $days - 2, 0), -150000]);
    expect(array_column($data['transactions'], 'id'))->toBe([$records[2]->id, $records[1]->id]);

    $page->set('filters.periode', 'bulan_ini');
    expect($page->get('dashboardData')['summary']['laba_bersih'])->toBe(0);
    expect($page->get('dashboardData')['chart']['labels'])->toHaveCount(30)->toContain('01 Sep');
})->with([
    'January' => ['2025-01-01', '2025-01-31', 31],
    'December' => ['2025-12-01', '2025-12-31', 31],
    'leap February' => ['2024-02-01', '2024-02-29', 29],
    'regular February' => ['2025-02-01', '2025-02-28', 28],
]);

test('selected month follows the reporting basis for completed cycles', function () {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    $cycle->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-10-05'])->save();

    $page = Livewire::test(Dashboard::class)
        ->set('filters.periode', 'pilih_bulan')
        ->set('filters.bulan', 10)
        ->assertHasNoErrors();
    $data = $page->get('dashboardData');

    expect($data['summary']['laba_bersih'])->toBe(-1000000);
    expect($data['chart']['laba_bersih'])->toHaveCount(31);
    expect($data['chart']['laba_bersih'][4])->toBe(-1000000);
    expect($data['transactions'])->toHaveCount(1);

    $page->set('filters.dasar', 'tanggal_transaksi');
    expect($page->get('dashboardData')['summary']['laba_bersih'])->toBe(0);
    expect($page->get('dashboardData')['chart']['laba_bersih'])->toBe(array_fill(0, 31, 0));
    expect($page->get('dashboardData')['transactions'])->toBeEmpty();
});

test('selected month rejects invalid month and year values', function (string $field, mixed $value, string $rule) {
    dashboardOwner();

    Livewire::test(Dashboard::class)
        ->set('filters.periode', 'pilih_bulan')
        ->set('filters.'.$field, $value)
        ->assertHasErrors(['filters.'.$field => $rule]);
})->with([
    'missing month' => ['bulan', null, 'required_if'],
    'month too low' => ['bulan', 0, 'between'],
    'month too high' => ['bulan', 13, 'between'],
    'invalid month' => ['bulan', 'invalid', 'integer'],
    'missing year' => ['tahun', null, 'required_if'],
    'year too low' => ['tahun', 1899, 'between'],
    'year too high' => ['tahun', 10000, 'between'],
]);

test('chart groups revenue and profit by transaction date and excludes investment from profit', function () {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pemasukan', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('arah', 'pemasukan')->sole()->id,
        'sikluses_id' => $cycle->id, 'qty' => 10, 'satuan' => 'kg', 'harga_satuan' => 200000, 'total' => 2000000,
    ]);
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('klasifikasi', 'investasi')->firstOrFail()->id,
        'total' => 500000,
    ]);

    $page = Livewire::test(Dashboard::class)->set('filters.dasar', 'tanggal_transaksi');
    $data = $page->get('dashboardData');

    expect($data['chart']['labels'])->toHaveCount(30);
    expect($data['chart']['pemasukan'])->toBe([...array_fill(0, 29, 0), 2000000]);
    expect($data['chart']['laba_bersih'])->toBe([-1000000, ...array_fill(0, 28, 0), 2000000]);
    expect(array_sum($data['chart']['laba_bersih']))->toBe($data['summary']['laba_bersih']);
});

test('chart recognizes all cycle transactions on completion and general expenses on their own dates', function () {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pemasukan', 'tanggal' => '2026-09-20',
        'kategoris_id' => $usaha->kategoris()->where('arah', 'pemasukan')->sole()->id,
        'sikluses_id' => $cycle->id, 'qty' => 10, 'satuan' => 'kg', 'harga_satuan' => 250000, 'total' => 2500000,
    ]);
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-15',
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Listrik/air')->sole()->id,
        'total' => 100000,
    ]);
    $cycle->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-10-05'])->save();
    dashboardCycle($user, $usaha);

    $page = Livewire::test(Dashboard::class)->set('filters.periode', 'tahun_ini');
    $data = $page->get('dashboardData');

    expect($data['chart']['labels'])->toHaveCount(12);
    expect($data['chart']['pemasukan'])->toBe([...array_fill(0, 9, 0), 2500000, 0, 0]);
    expect($data['chart']['laba_bersih'])->toBe([...array_fill(0, 8, 0), -100000, 1500000, 0, 0]);
    expect(array_sum($data['chart']['laba_bersih']))->toBe($data['summary']['laba_bersih']);

    $page->set('filters.periode', 'bulan_ini');
    expect(array_sum($page->get('dashboardData')['chart']['laba_bersih']))->toBe(-100000);
});

test('chart uses the selected year and fills periods without transactions with zero', function () {
    [$user, $usaha] = dashboardOwner();
    dashboardCycle($user, $usaha);

    $page = Livewire::test(Dashboard::class)
        ->set('filters.dasar', 'tanggal_transaksi')
        ->set('filters.periode', 'pilih_tahun')
        ->set('filters.tahun', 2025);

    expect($page->get('dashboardData')['chart'])->toBe([
        'labels' => ['Jan 2025', 'Feb 2025', 'Mar 2025', 'Apr 2025', 'Mei 2025', 'Jun 2025', 'Jul 2025', 'Agt 2025', 'Sep 2025', 'Okt 2025', 'Nov 2025', 'Des 2025'],
        'pemasukan' => array_fill(0, 12, 0),
        'laba_bersih' => array_fill(0, 12, 0),
    ]);

    $page->set('filters.tahun', 2026);
    expect($page->get('dashboardData')['chart']['laba_bersih'][8])->toBe(-1000000);
});

test('chart excludes deleted transactions deleted cycles and another tenants records', function () {
    [$user, $usaha] = dashboardOwner();
    $cycle = dashboardCycle($user, $usaha);
    $cycle->delete();
    $transaction = app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Listrik/air')->sole()->id,
        'total' => 500000,
    ]);
    $transaction->delete();
    Filament::setTenant(null);
    $otherUser = User::factory()->create();
    $otherUsaha = app(CreateUsaha::class)->handle($otherUser, [
        'nama' => 'Usaha lain', 'jenis_usaha' => $usaha->jenis_usaha,
    ]);
    Filament::setTenant($otherUsaha);
    dashboardCycle($otherUser, $otherUsaha);
    $this->actingAs($user);
    Filament::setTenant($usaha);

    $page = Livewire::test(Dashboard::class)->set('filters.dasar', 'tanggal_transaksi');

    expect($page->get('dashboardData')['chart']['laba_bersih'])->toBe(array_fill(0, 30, 0));
});

test('chart includes leap day when showing the current month', function () {
    dashboardOwner();
    $this->travelTo(now()->setDate(2024, 2, 15));

    $page = Livewire::test(Dashboard::class);

    expect($page->get('dashboardData')['chart']['labels'])->toHaveCount(29)->toContain('29 Feb');
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

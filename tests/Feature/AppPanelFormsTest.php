<?php

use App\Actions\CreateSiklus as CreateSiklusAction;
use App\Actions\CreateUsaha;
use App\Actions\SaveTransaksi;
use App\Filament\Pages\Tenancy\EditUsaha;
use App\Filament\Pages\Tenancy\RegisterUsaha;
use App\Filament\Resources\Kategoris\Pages\ManageKategoris;
use App\Filament\Resources\Sikluses\Pages\CreateSiklus;
use App\Filament\Resources\Sikluses\Pages\EditSiklus;
use App\Filament\Resources\Sikluses\Pages\ListSikluses;
use App\Filament\Resources\Sikluses\RelationManagers\PenandasRelationManager;
use App\Filament\Resources\Transaksis\Pages\ManageTransaksis;
use App\Models\Siklus;
use App\Models\TemplateUsaha;
use App\Models\Usaha;
use App\Models\User;
use Database\Seeders\TemplateKategoriSeeder;
use Database\Seeders\TemplatePenandaSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Formulir ini memakai migration PostgreSQL. Gunakan database PostgreSQL khusus pengujian.');
    }

    Filament::setCurrentPanel(Filament::getPanel('app'));
});

/**
 * @return array{0: User, 1: Usaha}
 */
function createPanelOwner(string $code = 'ayam-petelur'): array
{
    test()->seed([TemplateKategoriSeeder::class, TemplatePenandaSeeder::class]);
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha '.$user->id,
        'template_usahas_id' => TemplateUsaha::query()->where('kode', $code)->sole()->id,
    ]);

    return [$user, $usaha];
}

function enterPanel(User $user, Usaha $usaha): void
{
    test()->actingAs($user);
    Filament::setTenant($usaha);
    Filament::bootCurrentPanel();
}

function createPanelCycle(User $user, Usaha $usaha): Siklus
{
    return app(CreateSiklusAction::class)->handle($user, $usaha, [
        'nama' => 'Siklus pertama', 'tanggal_mulai' => '2026-09-01',
        'umur_masuk_hari' => 140, 'populasi_awal' => 100, 'harga_bibit' => 20000,
    ]);
}

test('guests are redirected to login', function () {
    $this->get('/app')->assertRedirect(route('filament.app.auth.login'));
});

test('registration creates an ordinary user without an email verification column', function () {
    Filament::bootCurrentPanel();

    Livewire::test(Register::class)->fillForm([
        'name' => 'Pemilik baru', 'email' => 'pemilik@example.com',
        'password' => 'Testing-password-123!', 'passwordConfirmation' => 'Testing-password-123!',
    ])->call('register')->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', ['email' => 'pemilik@example.com', 'is_admin' => false]);
});

test('owners create an usaha with copied categories and the template reporting basis', function () {
    $this->seed(TemplateKategoriSeeder::class);
    $user = User::factory()->create();
    $this->actingAs($user);
    Filament::bootCurrentPanel();

    Livewire::test(RegisterUsaha::class)->fillForm([
        'nama' => 'Petelur saya',
        'template_usahas_id' => TemplateUsaha::query()->where('kode', 'ayam-petelur')->sole()->id,
    ])->call('register')->assertHasNoFormErrors();

    $usaha = $user->usahas()->sole();
    $this->assertDatabaseHas('usahas', ['id' => $usaha->id, 'rekap_dasar' => 'tanggal_transaksi']);
    expect($usaha->kategoris()->count())->toBe(9);
    $this->assertDatabaseHas('kategoris', ['usahas_id' => $usaha->id, 'kode_sistem' => 'bibit', 'arah' => 'pengeluaran']);
});

test('cycle creation records seed expenses and skips milestones before entry age', function () {
    [$user, $usaha] = createPanelOwner();
    enterPanel($user, $usaha);

    Livewire::test(CreateSiklus::class)->fillForm([
        'nama' => 'Pullet September', 'tanggal_mulai' => '2026-09-01',
        'umur_masuk_hari' => 140, 'populasi_awal' => 100, 'harga_bibit' => 20000,
    ])->call('create')->assertHasNoFormErrors();

    $siklus = $usaha->sikluses()->sole();
    $this->assertDatabaseHas('transaksis', [
        'usahas_id' => $usaha->id, 'sikluses_id' => $siklus->id,
        'arah' => 'pengeluaran', 'qty' => 100, 'total' => 2000000,
    ]);
    expect($siklus->penandas()->count())->toBe(2);
    $this->assertDatabaseHas('sikluses_penandas', ['sikluses_id' => $siklus->id, 'jenis' => 'puncak', 'tanggal' => '2026-11-10']);
    $this->assertDatabaseMissing('sikluses_penandas', ['sikluses_id' => $siklus->id, 'jenis' => 'mulai_produksi']);
});

test('owners can record general expenses without a cycle', function () {
    [$user, $usaha] = createPanelOwner();
    enterPanel($user, $usaha);

    Livewire::test(ManageTransaksis::class)->callAction('create', data: [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Listrik/air')->sole()->id,
        'sikluses_id' => null, 'total' => 150000, 'catatan' => 'Tagihan September',
    ])->assertHasNoActionErrors();

    $this->assertDatabaseHas('transaksis', [
        'usahas_id' => $usaha->id, 'sikluses_id' => null, 'total' => 150000, 'qty' => null,
    ]);
});

test('income requires a cycle in the transaction form', function () {
    [$user, $usaha] = createPanelOwner();
    enterPanel($user, $usaha);

    Livewire::test(ManageTransaksis::class)->callAction('create', data: [
        'arah' => 'pemasukan', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Penjualan telur')->sole()->id,
        'sikluses_id' => null, 'qty' => 2, 'satuan' => 'kg', 'harga_satuan' => 30000, 'total' => 60000,
    ])->assertHasActionErrors(['sikluses_id' => 'required']);

    $this->assertDatabaseCount('transaksis', 0);
});

test('owners record income with a manually overridden total then soft delete it', function () {
    [$user, $usaha] = createPanelOwner();
    $siklus = createPanelCycle($user, $usaha);
    enterPanel($user, $usaha);

    Livewire::test(ManageTransaksis::class)->callAction('create', data: [
        'arah' => 'pemasukan', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Penjualan telur')->sole()->id,
        'sikluses_id' => $siklus->id, 'qty' => 2.5, 'satuan' => 'kg', 'harga_satuan' => 30000,
        'total' => 70000, 'pembeli' => 'Warung',
    ])->assertHasNoActionErrors();

    $transaksi = $usaha->transaksis()->where('arah', 'pemasukan')->sole();
    expect($transaksi->total)->toBe(70000);

    Livewire::test(ManageTransaksis::class)->callAction(TestAction::make('delete')->table($transaksi));
    $this->assertSoftDeleted($transaksi);
});

test('owners can create income categories without an expense classification', function () {
    [$user, $usaha] = createPanelOwner();
    enterPanel($user, $usaha);

    Livewire::test(ManageKategoris::class)->callAction('create', data: [
        'nama' => 'Penjualan tambahan', 'arah' => 'pemasukan',
        'satuan_default' => 'kg', 'pakai_kuantitas' => true, 'aktif' => true,
    ])->assertHasNoActionErrors();

    $this->assertDatabaseHas('kategoris', [
        'usahas_id' => $usaha->id, 'nama' => 'Penjualan tambahan', 'arah' => 'pemasukan', 'klasifikasi' => null,
    ]);
});

test('owners can close a cycle with an editable completion date', function () {
    [$user, $usaha] = createPanelOwner();
    $siklus = createPanelCycle($user, $usaha);
    enterPanel($user, $usaha);

    Livewire::test(ListSikluses::class)
        ->callAction(TestAction::make('tutup')->table($siklus), data: ['tanggal_selesai' => '2026-09-25'])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('sikluses', ['id' => $siklus->id, 'status' => 'selesai', 'tanggal_selesai' => '2026-09-25']);
});

test('owners can edit milestone dates', function () {
    [$user, $usaha] = createPanelOwner();
    $siklus = createPanelCycle($user, $usaha);
    $penanda = $siklus->penandas()->where('jenis', 'puncak')->sole();
    enterPanel($user, $usaha);

    Livewire::test(PenandasRelationManager::class, ['ownerRecord' => $siklus, 'pageClass' => EditSiklus::class])
        ->callAction(TestAction::make('edit')->table($penanda), data: ['tanggal' => '2026-11-15'])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('sikluses_penandas', ['id' => $penanda->id, 'tanggal' => '2026-11-15']);
});

test('another owner cannot open an usaha or change its records', function () {
    [$owner, $usaha] = createPanelOwner();
    $siklus = createPanelCycle($owner, $usaha);
    $other = User::factory()->create();

    expect($other->canAccessTenant($usaha))->toBeFalse();
    expect(Gate::forUser($other)->allows('update', $siklus))->toBeFalse();
    $this->actingAs($other)->get('/app/'.$usaha->id.'/sikluses')->assertNotFound();
});

test('transactions reject categories belonging to another usaha', function () {
    [$user, $usaha] = createPanelOwner();
    [$other, $otherUsaha] = createPanelOwner();

    expect(fn () => app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-30', 'total' => 100,
        'kategoris_id' => $otherUsaha->kategoris()->where('nama', 'Gaji')->sole()->id,
    ]))->toThrow(ValidationException::class, 'Pilih kategori aktif dari usaha ini.');

    $this->assertDatabaseCount('transaksis', 0);
});

test('owners can change their usaha name and reporting basis', function () {
    [$user, $usaha] = createPanelOwner();
    enterPanel($user, $usaha);

    Livewire::test(EditUsaha::class)->fillForm(['nama' => 'Nama baru', 'rekap_dasar' => 'siklus_selesai'])
        ->call('save')->assertHasNoFormErrors();

    $this->assertDatabaseHas('usahas', ['id' => $usaha->id, 'nama' => 'Nama baru', 'rekap_dasar' => 'siklus_selesai']);
});

test('the active usaha scopes transaction lists even when both usaha belong to one owner', function () {
    [$user, $usaha] = createPanelOwner();
    $otherUsaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha kedua', 'template_usahas_id' => $usaha->template_usahas_id,
    ]);
    createPanelCycle($user, $usaha);
    createPanelCycle($user, $otherUsaha);
    $visible = $usaha->transaksis()->sole();
    $hidden = $otherUsaha->transaksis()->sole();
    enterPanel($user, $usaha);

    Livewire::test(ManageTransaksis::class)->assertCanSeeTableRecords([$visible])->assertCanNotSeeTableRecords([$hidden]);
});

test('new income cannot use a closed cycle but its existing transactions remain editable', function () {
    [$user, $usaha] = createPanelOwner();
    $siklus = createPanelCycle($user, $usaha);
    $siklus->forceFill(['status' => 'selesai', 'tanggal_selesai' => '2026-09-20'])->save();
    enterPanel($user, $usaha);

    Livewire::test(ManageTransaksis::class)->callAction('create', data: [
        'arah' => 'pemasukan', 'tanggal' => '2026-09-30',
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Penjualan telur')->sole()->id,
        'sikluses_id' => $siklus->id, 'qty' => 1, 'satuan' => 'kg', 'harga_satuan' => 30000, 'total' => 30000,
    ])->assertHasActionErrors(['sikluses_id']);

    $record = $siklus->transaksis()->sole();
    Livewire::test(ManageTransaksis::class)->callAction(TestAction::make('edit')->table($record), data: [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-01',
        'kategoris_id' => $record->kategoris_id, 'sikluses_id' => $siklus->id,
        'qty' => 100, 'satuan' => 'ekor', 'harga_satuan' => 19000, 'total' => 1900000,
    ])->assertHasNoActionErrors();

    $this->assertDatabaseCount('transaksis', 1);
    $this->assertDatabaseHas('transaksis', ['id' => $record->id, 'total' => 1900000, 'sikluses_id' => $siklus->id]);
});

test('used expense categories cannot be changed into investment or income', function () {
    [$user, $usaha] = createPanelOwner();
    $category = $usaha->kategoris()->where('nama', 'Listrik/air')->sole();
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-30', 'kategoris_id' => $category->id, 'total' => 150000,
    ]);
    enterPanel($user, $usaha);

    Livewire::test(ManageKategoris::class)->callAction(TestAction::make('edit')->table($category), data: [
        'nama' => 'Listrik bulanan', 'arah' => 'pemasukan', 'klasifikasi' => 'investasi', 'aktif' => true,
    ])->assertHasNoActionErrors();

    $this->assertDatabaseHas('kategoris', [
        'id' => $category->id, 'nama' => 'Listrik bulanan', 'arah' => 'pengeluaran', 'klasifikasi' => 'operasional',
    ]);
});

test('missing seed category shows an error and does not create a partial cycle', function () {
    [$user, $usaha] = createPanelOwner();
    $usaha->kategoris()->where('kode_sistem', 'bibit')->update(['aktif' => false]);
    enterPanel($user, $usaha);

    Livewire::test(CreateSiklus::class)->fillForm([
        'nama' => 'Gagal', 'tanggal_mulai' => '2026-09-01',
        'umur_masuk_hari' => 0, 'populasi_awal' => 100, 'harga_bibit' => 1000,
    ])->call('create')->assertHasFormErrors(['harga_bibit'])
        ->assertSee('Kategori bibit aktif belum tersedia pada usaha ini.');

    $this->assertDatabaseCount('sikluses', 0);
    $this->assertDatabaseCount('transaksis', 0);
    $this->assertDatabaseCount('sikluses_penandas', 0);
});

test('cycle closing rejects a date before its start', function () {
    [$user, $usaha] = createPanelOwner();
    $siklus = createPanelCycle($user, $usaha);
    enterPanel($user, $usaha);

    Livewire::test(ListSikluses::class)
        ->callAction(TestAction::make('tutup')->table($siklus), data: ['tanggal_selesai' => '2026-08-31'])
        ->assertHasActionErrors(['tanggal_selesai']);

    $this->assertDatabaseHas('sikluses', ['id' => $siklus->id, 'status' => 'berjalan', 'tanggal_selesai' => null]);
});

test('transaction services reject cycles from a different usaha', function () {
    [$user, $usaha] = createPanelOwner();
    [$other, $otherUsaha] = createPanelOwner();
    $otherSiklus = createPanelCycle($other, $otherUsaha);

    expect(fn () => app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pengeluaran', 'tanggal' => '2026-09-30', 'total' => 100,
        'kategoris_id' => $usaha->kategoris()->where('nama', 'Gaji')->sole()->id,
        'sikluses_id' => $otherSiklus->id,
    ]))->toThrow(ValidationException::class, 'Pilih siklus berjalan dari usaha ini.');

    expect($usaha->transaksis()->count())->toBe(0);
});

test('category selection prefills the last income price and quantity updates the total', function () {
    [$user, $usaha] = createPanelOwner();
    $siklus = createPanelCycle($user, $usaha);
    $category = $usaha->kategoris()->where('nama', 'Penjualan telur')->sole();
    app(SaveTransaksi::class)->handle($user, $usaha, [
        'arah' => 'pemasukan', 'tanggal' => '2026-09-29', 'kategoris_id' => $category->id,
        'sikluses_id' => $siklus->id, 'qty' => 1, 'satuan' => 'kg', 'harga_satuan' => 30000, 'total' => 30000,
    ]);
    enterPanel($user, $usaha);

    Livewire::test(ManageTransaksis::class)->mountAction('create')
        ->set('mountedActions.0.data.arah', 'pemasukan')
        ->set('mountedActions.0.data.kategoris_id', $category->id)
        ->assertActionDataSet(['harga_satuan' => 30000, 'satuan' => 'kg'])
        ->set('mountedActions.0.data.qty', 2.5)
        ->assertActionDataSet(['total' => 75000]);
});

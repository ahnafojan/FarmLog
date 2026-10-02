<?php

use App\Actions\CreateUsaha;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\RegisterUsaha;
use App\Models\TemplateUsaha;
use App\Models\Usaha;
use App\Models\User;
use Database\Seeders\TemplateKategoriSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    if (config('database.default') !== 'pgsql') {
        $this->markTestSkipped('Gunakan database PostgreSQL khusus pengujian untuk migration aplikasi.');
    }

    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant(null);
});

test('registering a business redirects to its slug dashboard', function () {
    $this->seed(TemplateKategoriSeeder::class);
    $this->actingAs(User::factory()->create());
    Filament::bootCurrentPanel();

    Livewire::test(RegisterUsaha::class)
        ->fillForm([
            'nama' => 'Kolam Budi',
            'template_usahas_id' => TemplateUsaha::query()->firstOrFail()->id,
        ])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(route('filament.app.pages.dashboard', ['tenant' => 'kolam-budi']));

    $this->assertDatabaseHas('usahas', ['nama' => 'Kolam Budi', 'slug' => 'kolam-budi']);
    $this->get('/app/kolam-budi')->assertOk();
});

test('business slugs remain unique including deleted businesses', function () {
    $this->seed(TemplateKategoriSeeder::class);
    $data = ['nama' => 'Kolam Budi', 'template_usahas_id' => TemplateUsaha::query()->firstOrFail()->id];
    $first = app(CreateUsaha::class)->handle(User::factory()->create(), $data);
    $first->delete();

    $second = app(CreateUsaha::class)->handle(User::factory()->create(), $data);
    $third = app(CreateUsaha::class)->handle(User::factory()->create(), $data);

    expect($second->slug)->toBe('kolam-budi-2');
    expect($third->slug)->toBe('kolam-budi-3');
});

test('business names produce usable slugs', function (string $name, string $slug) {
    $this->seed(TemplateKategoriSeeder::class);

    $usaha = app(CreateUsaha::class)->handle(User::factory()->create(), [
        'nama' => $name,
        'template_usahas_id' => TemplateUsaha::query()->firstOrFail()->id,
    ]);

    expect($usaha->slug)->toBe($slug);
})->with([
    'punctuation' => ['Kolam & Budi!', 'kolam-budi'],
    'empty transliteration' => ['!!!', 'usaha'],
    'registration route' => ['New', 'new-2'],
    'profile route' => ['Profile', 'profile-2'],
    'login route' => ['Login', 'login-2'],
    'logout route' => ['Logout', 'logout-2'],
    'register route' => ['Register', 'register-2'],
    'password reset route' => ['Password Reset', 'password-reset-2'],
]);

test('renaming a business preserves its URL and numeric IDs no longer resolve', function () {
    $this->seed(TemplateKategoriSeeder::class);
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Kolam Budi',
        'template_usahas_id' => TemplateUsaha::query()->firstOrFail()->id,
    ]);
    $this->actingAs($user);

    $usaha->update(['nama' => 'Kolam Baru']);

    expect(parse_url(Dashboard::getUrl(tenant: $usaha->fresh()), PHP_URL_PATH))->toBe('/app/kolam-budi');
    $this->get('/app/kolam-budi')->assertOk();
    $this->get('/app/'.$usaha->id)->assertNotFound();
    $this->get('/app/slug-tidak-ada')->assertNotFound();
});

test('slug migration backfills existing and deleted businesses without losing data', function () {
    $this->seed(TemplateKategoriSeeder::class);
    $data = ['nama' => 'Kolam Budi', 'template_usahas_id' => TemplateUsaha::query()->firstOrFail()->id];
    $first = app(CreateUsaha::class)->handle(User::factory()->create(), $data);
    $second = app(CreateUsaha::class)->handle(User::factory()->create(), $data);
    $first->delete();
    Schema::table('usahas', function (Blueprint $table): void {
        $table->dropColumn('slug');
    });

    $migration = require database_path('migrations/2026_10_02_031844_add_slug_to_usahas_table.php');
    $migration->up();

    expect(Usaha::withTrashed()->findOrFail($first->id)->slug)->toBe('kolam-budi');
    expect($second->fresh()->slug)->toBe('kolam-budi-2');
    $this->assertSoftDeleted($first);
    $this->assertDatabaseHas('usahas', ['id' => $second->id, 'nama' => 'Kolam Budi', 'user_id' => $second->user_id]);
});

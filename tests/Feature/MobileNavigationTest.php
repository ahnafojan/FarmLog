<?php

use App\Models\Usaha;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Routing\Route;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Filament::setTenant(null);
    Filament::bootCurrentPanel();
});

test('mobile hooks render nothing without an authenticated tenant', function () {
    expect((string) FilamentView::renderHook(PanelsRenderHook::CONTENT_START))->toBe('');
    expect((string) FilamentView::renderHook(PanelsRenderHook::CONTENT_END))->toBe('');
});

test('shared mobile header renders supplied businesses without a dashboard component', function () {
    $usaha = (new Usaha)->forceFill(['id' => 42, 'nama' => 'Kolam <Budi>', 'slug' => 'kolam-budi']);
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 7]);
    $user->shouldReceive('getTenants')->once()->with(Filament::getCurrentPanel())->andReturn(collect([$usaha]));
    $this->actingAs($user);
    Filament::setTenant($usaha);

    $html = (string) FilamentView::renderHook(PanelsRenderHook::CONTENT_START);

    expect($html)->toContain('Ganti usaha', 'Kolam &lt;Budi&gt;', '/app/kolam-budi', 'Pengaturan usaha')
        ->not->toContain('Kolam <Budi>');
});

test('shared mobile navigation highlights the current resource', function () {
    $this->actingAs(User::factory()->make());
    Filament::setTenant((new Usaha)->forceFill(['id' => 42, 'nama' => 'Kolam Budi', 'slug' => 'kolam-budi']));
    request()->setRouteResolver(fn () => (new Route('GET', 'app/kolam-budi/transaksis', fn () => null))
        ->name('filament.app.resources.transaksis.index'));

    $html = (string) FilamentView::renderHook(PanelsRenderHook::CONTENT_END);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $links = (new DOMXPath($document))->query('//nav/a[@aria-current="page"]');

    expect($links->length)->toBe(1);
    expect(trim($links->item(0)->textContent))->toBe('Transaksi');
});

test('profile keeps its navigation within the users businesses', function (?string $tenantSlug, bool $hasBusinesses, string $expectedPath) {
    $usahas = $hasBusinesses ? collect([
        (new Usaha)->forceFill(['id' => 42, 'nama' => 'Kolam <Budi>', 'slug' => 'kolam-budi']),
        (new Usaha)->forceFill(['id' => 84, 'nama' => 'Kolam Kedua', 'slug' => 'kolam-kedua']),
    ]) : collect();
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 7, 'name' => 'Budi', 'email' => 'budi@example.com']);
    $user->shouldReceive('getTenants')->andReturn($usahas);
    $this->actingAs($user);

    $response = $this->get(Filament::getProfileUrl(['tenant' => $tenantSlug]));

    $response->assertOk()->assertSee('Profil akun')->assertSee('Informasi akun')->assertSee('Keamanan akun');
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $navigation = $xpath->query('//nav[@aria-label="Navigasi utama"]');
    $activeLinks = $xpath->query('//nav[@aria-label="Navigasi utama"]/a[@aria-current="page"]');
    $cycleLink = $xpath->query('//nav[@aria-label="Navigasi utama"]/a')->item(1);

    expect($navigation->length)->toBe(1);
    expect($activeLinks->length)->toBe(1);
    expect(trim($activeLinks->item(0)->textContent))->toBe('Akun');
    expect(parse_url($cycleLink->getAttribute('href'), PHP_URL_PATH))->toBe($expectedPath);
    expect($xpath->query('//header//button[@aria-label="Ganti tema"]')->length)->toBe(1);
})->with([
    'selected business' => ['kolam-kedua', true, '/app/kolam-kedua/sikluses'],
    'direct profile visit' => [null, true, '/app/kolam-budi/sikluses'],
    'unowned business falls back to own business' => ['usaha-orang-lain', true, '/app/kolam-budi/sikluses'],
    'account without a business' => [null, false, '/app/new'],
]);

test('guests are redirected to login from the profile', function () {
    $this->get(Filament::getProfileUrl())->assertRedirect(Filament::getLoginUrl());
});

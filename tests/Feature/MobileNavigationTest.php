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
    $usaha = (new Usaha)->forceFill(['id' => 42, 'nama' => 'Kolam <Budi>']);
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => 7]);
    $user->shouldReceive('getTenants')->once()->with(Filament::getCurrentPanel())->andReturn(collect([$usaha]));
    $this->actingAs($user);
    Filament::setTenant($usaha);

    $html = (string) FilamentView::renderHook(PanelsRenderHook::CONTENT_START);

    expect($html)->toContain('Ganti usaha', 'Kolam &lt;Budi&gt;', '/app/42', 'Pengaturan usaha')
        ->not->toContain('Kolam <Budi>');
});

test('shared mobile navigation highlights the current resource', function () {
    $this->actingAs(User::factory()->make());
    Filament::setTenant((new Usaha)->forceFill(['id' => 42, 'nama' => 'Kolam Budi']));
    request()->setRouteResolver(fn () => (new Route('GET', 'app/42/transaksis', fn () => null))
        ->name('filament.app.resources.transaksis.index'));

    $html = (string) FilamentView::renderHook(PanelsRenderHook::CONTENT_END);
    $document = new DOMDocument;
    @$document->loadHTML($html);
    $links = (new DOMXPath($document))->query('//nav/a[@aria-current="page"]');

    expect($links->length)->toBe(1);
    expect(trim($links->item(0)->textContent))->toBe('Transaksi');
});

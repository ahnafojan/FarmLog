<?php

use App\Actions\CreateUsaha;
use App\Models\Usaha;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'database']);
    $this->freezeTime();
});

/** @return array{0: User, 1: Usaha} */
function exportOwner(): array
{
    $user = User::factory()->create();
    $usaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha ekspor', 'jenis_usaha' => 'lele-pembesaran',
    ]);

    return [$user, $usaha];
}

function exportDownloadUrl(Usaha $usaha): string
{
    return route('laporan.download', [
        'usaha' => $usaha->slug, 'jenis' => 'penjualan',
        'periode' => 'bulanan', 'tahun' => 2026, 'bulan' => 10,
    ]);
}

test('the fourth export request in a minute is rejected and the limit resets', function () {
    [$user, $usaha] = exportOwner();
    $this->actingAs($user);
    $url = exportDownloadUrl($usaha);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $response = $this->get($url)->assertOk()->assertDownload();
        expect($response->streamedContent())->toStartWith('%PDF-');
    }

    $this->get($url)->assertTooManyRequests()
        ->assertSeeText('Maksimal 3 permintaan per menit')
        ->assertHeader('Retry-After', '60');

    $this->travel(61)->seconds();
    $response = $this->get($url)->assertOk()->assertDownload();
    expect($response->streamedContent())->toStartWith('%PDF-');
});

test('the export rate limit is shared across businesses but isolated between users', function () {
    [$user, $usaha] = exportOwner();
    $otherUsaha = app(CreateUsaha::class)->handle($user, [
        'nama' => 'Usaha kedua', 'jenis_usaha' => 'lele-pembesaran',
    ]);
    [$otherUser, $otherUserUsaha] = exportOwner();
    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $this->get(exportDownloadUrl($usaha))->assertOk()->streamedContent();
    }

    $this->getJson(exportDownloadUrl($otherUsaha))->assertTooManyRequests()
        ->assertJsonPath('message', 'Batas ekspor tercapai. Maksimal 3 permintaan per menit. Silakan coba lagi nanti.');
    $response = $this->actingAs($otherUser)->get(exportDownloadUrl($otherUserUsaha))
        ->assertOk()->assertDownload();
    expect($response->streamedContent())->toStartWith('%PDF-');
});

test('an export is rejected while its users lock is held without releasing that lock', function () {
    [$user, $usaha] = exportOwner();
    $lock = Cache::lock("export:user:{$user->id}", 180);
    expect($lock->get())->toBeTrue();

    try {
        $this->actingAs($user)->get(exportDownloadUrl($usaha))
            ->assertTooManyRequests()
            ->assertSeeText('Ekspor Anda masih diproses')
            ->assertHeader('Retry-After', '180');

        expect($lock->isOwnedByCurrentProcess())->toBeTrue();
    } finally {
        $lock->release();
    }
});

test('the export endpoint rejects requests when both site slots are occupied', function () {
    [$user, $usaha] = exportOwner();
    $firstSlot = Cache::lock('export:slot:1', 180);
    $secondSlot = Cache::lock('export:slot:2', 180);
    expect($firstSlot->get())->toBeTrue();
    expect($secondSlot->get())->toBeTrue();

    try {
        $this->actingAs($user)->get(exportDownloadUrl($usaha))
            ->assertTooManyRequests()->assertSeeText('Dua ekspor sedang diproses');
        expect(Cache::lock("export:user:{$user->id}", 180)->get(fn (): bool => true))->toBeTrue();
        expect($firstSlot->isOwnedByCurrentProcess())->toBeTrue();
        expect($secondSlot->isOwnedByCurrentProcess())->toBeTrue();

        $firstSlot->release();
        $response = $this->get(exportDownloadUrl($usaha))->assertOk()->assertDownload();
        expect($response->streamedContent())->toStartWith('%PDF-');
    } finally {
        $secondSlot->release();
        $firstSlot->release();
    }
});

test('guests cannot start an export', function () {
    [, $usaha] = exportOwner();

    $this->get(exportDownloadUrl($usaha))->assertRedirect(route('filament.app.auth.login'));
});

test('users cannot export another owners business', function () {
    [, $usaha] = exportOwner();
    $otherUser = User::factory()->create();

    $this->actingAs($otherUser)->get(exportDownloadUrl($usaha))->assertNotFound();
});

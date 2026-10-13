<?php

use App\Support\ExportGuard;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    config(['cache.default' => 'database']);
});

test('export locks are held during generation and released after success', function () {
    $response = new StreamedResponse;

    $result = app(ExportGuard::class)->run(1, function () use ($response): StreamedResponse {
        expect(Cache::lock('export:user:1', 180)->get())->toBeFalse();
        expect(Cache::lock('export:slot:1', 180)->get())->toBeFalse();

        return $response;
    });

    expect($result)->toBe($response);
    expect(Cache::lock('export:user:1', 180)->get(fn (): bool => true))->toBeTrue();
    expect(Cache::lock('export:slot:1', 180)->get(fn (): bool => true))->toBeTrue();
});

test('export locks are released when generation throws an exception', function () {
    expect(fn () => app(ExportGuard::class)->run(1, function (): StreamedResponse {
        throw new RuntimeException('Pembuatan PDF gagal.');
    }))->toThrow(RuntimeException::class, 'Pembuatan PDF gagal.');

    expect(Cache::lock('export:user:1', 180)->get(fn (): bool => true))->toBeTrue();
    expect(Cache::lock('export:slot:1', 180)->get(fn (): bool => true))->toBeTrue();
});

test('two different users can export while a third user is rejected', function () {
    $guard = app(ExportGuard::class);
    $response = new StreamedResponse;

    $result = $guard->run(1, fn (): StreamedResponse => $guard->run(2, function () use ($guard, $response): StreamedResponse {
        expect(Cache::lock('export:slot:1', 180)->get())->toBeFalse();
        expect(Cache::lock('export:slot:2', 180)->get())->toBeFalse();

        try {
            $guard->run(3, function (): StreamedResponse {
                test()->fail('Ekspor ketiga tidak boleh dijalankan.');
            });
            test()->fail('Ekspor ketiga harus ditolak.');
        } catch (HttpResponseException $exception) {
            expect($exception->getResponse()->getStatusCode())->toBe(429);
            expect($exception->getResponse()->getContent())->toContain('Dua ekspor sedang diproses');
        }

        expect(Cache::lock('export:user:3', 180)->get(fn (): bool => true))->toBeTrue();

        return $response;
    }));

    expect($result)->toBe($response);
    expect(Cache::lock('export:slot:1', 180)->get(fn (): bool => true))->toBeTrue();
    expect(Cache::lock('export:slot:2', 180)->get(fn (): bool => true))->toBeTrue();
});

test('export locks expire after 180 seconds without releasing a new owners locks', function () {
    $this->freezeTime();
    $newUserLock = Cache::lock('export:user:1', 180);
    $newSlotLock = Cache::lock('export:slot:1', 180);

    try {
        app(ExportGuard::class)->run(1, function () use ($newUserLock, $newSlotLock): StreamedResponse {
            $this->travel(179)->seconds();
            expect($newUserLock->get())->toBeFalse();
            expect($newSlotLock->get())->toBeFalse();

            $this->travel(1)->seconds();
            expect($newUserLock->get())->toBeTrue();
            expect($newSlotLock->get())->toBeTrue();

            return new StreamedResponse;
        });

        expect($newUserLock->isOwnedByCurrentProcess())->toBeTrue();
        expect($newSlotLock->isOwnedByCurrentProcess())->toBeTrue();
    } finally {
        $newSlotLock->release();
        $newUserLock->release();
    }
});

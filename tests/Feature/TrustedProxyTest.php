<?php

use Illuminate\Support\Facades\Route;

test('asset URLs use the scheme supplied by a trusted local proxy', function (string $remoteAddress, ?string $forwardedProto, string $scheme) {
    Route::get('/proxy-check', fn (): array => [
        'asset' => asset('build/assets/app.css'),
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => $remoteAddress,
        'HTTP_X_FORWARDED_PROTO' => $forwardedProto,
    ])->get('http://tunnel.example/proxy-check');

    $response->assertExactJson([
        'asset' => $scheme.'://tunnel.example/build/assets/app.css',
    ]);
})->with([
    'IPv4 loopback tunnel' => ['127.0.0.1', 'https', 'https'],
    'IPv6 loopback tunnel' => ['::1', 'https', 'https'],
    'direct local HTTP' => ['127.0.0.1', null, 'http'],
    'untrusted proxy' => ['203.0.113.10', 'https', 'http'],
]);

test('asset URLs use the public host only when forwarded by a trusted local proxy', function (string $remoteAddress, string $origin) {
    Route::get('/proxy-check', fn (): array => [
        'asset' => asset('build/assets/app.css'),
    ]);

    $response = $this->withServerVariables([
        'REMOTE_ADDR' => $remoteAddress,
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_HOST' => 'tunnel.example',
    ])->get('http://localhost:8000/proxy-check');

    $response->assertExactJson([
        'asset' => $origin.'/build/assets/app.css',
    ]);
})->with([
    'IPv4 loopback tunnel' => ['127.0.0.1', 'https://tunnel.example'],
    'IPv6 loopback tunnel' => ['::1', 'https://tunnel.example'],
    'untrusted proxy' => ['203.0.113.10', 'http://localhost:8000'],
]);

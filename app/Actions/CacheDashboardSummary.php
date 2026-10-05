<?php

namespace App\Actions;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CacheDashboardSummary
{
    /**
     * @param  Closure(): array<string, mixed>  $resolve
     * @return array<string, mixed>
     */
    public function handle(
        int $usahaId,
        string $filterKey,
        Closure $resolve,
    ): array {
        $versionKey = $this->versionKey($usahaId);
        $version = Cache::get($versionKey);

        if ($version === null) {
            $candidate = (string) Str::uuid();

            Cache::add($versionKey, $candidate, 86400);

            $version = Cache::get($versionKey, $candidate);
        }

        $key = "dashboard:v1:{$usahaId}:{$version}:{$filterKey}";

        return Cache::remember($key, 60, $resolve);
    }

    public function invalidate(int $usahaId): void
    {
        Cache::put(
            $this->versionKey($usahaId),
            (string) Str::uuid(),
            86400,
        );
    }

    private function versionKey(int $usahaId): string
    {
        return "dashboard:v1:{$usahaId}:version";
    }
}

<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportGuard
{
    /**
     * @param  Closure(): StreamedResponse  $export
     */
    public function run(int $userId, Closure $export): StreamedResponse
    {
        $userLock = Cache::lock("export:user:{$userId}", 180);

        if (! $userLock->get()) {
            throw new HttpResponseException(response(
                'Ekspor Anda masih diproses. Tunggu hingga selesai sebelum mencoba lagi.',
                429,
                ['Retry-After' => '180'],
            ));
        }

        try {
            for ($slot = 1; $slot <= 2; $slot++) {
                $slotLock = Cache::lock("export:slot:{$slot}", 180);

                if (! $slotLock->get()) {
                    continue;
                }

                try {
                    return $export();
                } finally {
                    $slotLock->release();
                }
            }

            throw new HttpResponseException(response(
                'Dua ekspor sedang diproses. Silakan coba lagi setelah salah satunya selesai.',
                429,
                ['Retry-After' => '180'],
            ));
        } finally {
            $userLock->release();
        }
    }
}

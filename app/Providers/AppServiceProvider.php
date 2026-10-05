<?php

namespace App\Providers;

use App\Actions\CacheDashboardSummary;
use App\Models\Kategori;
use App\Models\Siklus;
use App\Models\SiklusPenanda;
use App\Models\Transaksi;
use App\Models\Usaha;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(CacheDashboardSummary $cache): void
    {
        $invalidate = function (Model $model) use ($cache): void {
            if ($model instanceof Usaha) {
                $usahaIds = [$model->getKey()];
            } elseif ($model instanceof SiklusPenanda) {
                $cycleIds = array_values(array_filter([
                    $model->sikluses_id,
                    $model->getRawOriginal('sikluses_id'),
                ]));

                $usahaIds = Siklus::withTrashed()
                    ->whereIn('id', $cycleIds)
                    ->pluck('usahas_id')
                    ->all();
            } else {
                $usahaIds = [
                    $model->usahas_id,
                    $model->getRawOriginal('usahas_id'),
                ];
            }

            $usahaIds = array_values(array_unique(array_filter($usahaIds)));

            $model->getConnection()->afterCommit(function () use ($usahaIds, $cache): void {
                foreach ($usahaIds as $usahaId) {
                    $cache->invalidate((int) $usahaId);
                }
            });
        };

        foreach ([
            Transaksi::class,
            Kategori::class,
            Siklus::class,
            SiklusPenanda::class,
            Usaha::class,
        ] as $modelClass) {
            $modelClass::saved($invalidate);
            $modelClass::deleted($invalidate);
        }
    }
}

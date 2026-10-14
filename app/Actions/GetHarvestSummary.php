<?php

namespace App\Actions;

use App\Models\Siklus;
use App\Models\SiklusPenanda;
use Carbon\CarbonInterface;

class GetHarvestSummary
{
    /**
     * @return array{nama: string, tanggal: string, sisa_hari: int, progres: int}|null
     */
    public function handle(Siklus $cycle, ?SiklusPenanda $harvest, CarbonInterface $today): ?array
    {
        if ($harvest === null) {
            return null;
        }

        $remainingDays = (int) $today->diffInDays($harvest->tanggal, false);
        $currentAge = max(0, $cycle->umur_masuk_hari + (int) $cycle->tanggal_mulai->diffInDays($today, false));
        $targetAge = $cycle->umur_masuk_hari + (int) $cycle->tanggal_mulai->diffInDays($harvest->tanggal, false);

        return [
            'nama' => $harvest->nama,
            'tanggal' => $harvest->tanggal->locale('id')->translatedFormat('d M Y'),
            'sisa_hari' => $remainingDays,
            'progres' => $remainingDays <= 0
                ? 100
                : ($targetAge > 0 ? (int) min(99, round($currentAge / $targetAge * 100)) : 0),
        ];
    }
}

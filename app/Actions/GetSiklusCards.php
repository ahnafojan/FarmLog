<?php

namespace App\Actions;

use App\Models\Siklus;
use App\Models\Usaha;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GetSiklusCards
{
    public function handle(Usaha $usaha, string $status): array
    {
        Gate::authorize('view', $usaha);

        Validator::make(
            ['status' => $status],
            ['status' => ['required', Rule::in(['berjalan', 'selesai'])]],
        )->validate();

        $counts = $usaha->sikluses()
            ->select('status')
            ->selectRaw('COUNT(*) AS jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $cycles = $usaha->sikluses()
            ->where('status', $status)
            ->with([
                'penandas' => fn ($query) => $query
                    ->whereNull('selesai_at')
                    ->orderBy('tanggal')
                    ->orderBy('id'),
            ])
            ->withSum([
                'transaksis as pemasukan' => fn (Builder $query) => $query
                    ->where('arah', 'pemasukan'),

                'transaksis as operasional' => fn (Builder $query) => $query
                    ->where('arah', 'pengeluaran')
                    ->whereHas(
                        'kategori',
                        fn (Builder $category) => $category
                            ->where('klasifikasi', 'operasional'),
                    ),

                'transaksis as investasi' => fn (Builder $query) => $query
                    ->where('arah', 'pengeluaran')
                    ->whereHas(
                        'kategori',
                        fn (Builder $category) => $category
                            ->where('klasifikasi', 'investasi'),
                    ),
            ], 'total')
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->paginate(6, ['*'], 'cyclePage');

        $cycles->through(function (Siklus $siklus): array {
            $today = today();
            $finished = $siklus->status === 'selesai';

            $ageDate = $finished
                ? ($siklus->tanggal_selesai ?? $today)
                : $today;

            $milestone = $finished
                ? null
                : $siklus->penandas->first(
                    fn ($item) => $item->tanggal->gte($today),
                );

            $harvest = $finished
                ? null
                : $siklus->penandas->first(
                    fn ($item) => in_array($item->jenis, ['panen', 'afkir'], true),
                );

            $income = (int) $siklus->pemasukan;
            $operational = (int) $siklus->operasional;

            $duration = $harvest
                ? (int) $siklus->tanggal_mulai->diffInDays($harvest->tanggal, false)
                : 0;

            $elapsed = (int) $siklus->tanggal_mulai->diffInDays($today, false);

            $harvestDays = $harvest
                ? (int) $today->diffInDays($harvest->tanggal, false)
                : null;

            return [
                'id' => $siklus->id,
                'nama' => $siklus->nama,
                'status' => $siklus->status,
                'mulai' => $siklus->tanggal_mulai->format('d/m/Y'),
                'selesai' => $siklus->tanggal_selesai?->format('d/m/Y'),
                'jumlah' => $siklus->populasi_awal,
                'umur' => max(
                    0,
                    $siklus->umur_masuk_hari
                        + (int) $siklus->tanggal_mulai->diffInDays($ageDate, false),
                ),
                'penanda' => $milestone?->nama,
                'tanggal_penanda' => $milestone?->tanggal->format('d/m/Y'),
                'sisa_hari' => $milestone
                    ? (int) $today->diffInDays($milestone->tanggal, false)
                    : null,
                'panen' => $harvest ? [
                    'nama' => $harvest->nama,
                    'tanggal' => $harvest->tanggal->translatedFormat('d M Y'),
                    'sisa_hari' => $harvestDays,
                    'progres' => $duration > 0
                        ? (int) max(0, min(100, round($elapsed / $duration * 100)))
                        : ($harvestDays <= 0 ? 100 : 0),
                ] : null,
                'laba_bersih' => $income - $operational,
                'summary' => [
                    'pemasukan' => $income,
                    'operasional' => $operational,
                    'investasi' => (int) $siklus->investasi,
                    'laba_bersih' => $income - $operational,
                ],
            ];
        });

        return [
            'cycles' => $cycles,
            'counts' => [
                'berjalan' => (int) ($counts['berjalan'] ?? 0),
                'selesai' => (int) ($counts['selesai'] ?? 0),
            ],
        ];
    }
}
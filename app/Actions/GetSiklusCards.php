<?php

namespace App\Actions;

use App\Models\Siklus;
use App\Models\Usaha;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GetSiklusCards
{
    public function __construct(
        private GetHarvestSummary $getHarvestSummary,
        private GetFinancialSummary $getFinancialSummary,
    ) {}

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

        $cycles = $this->cardQuery($usaha)
            ->select(['id', 'usahas_id', 'nama', 'status', 'tanggal_mulai', 'tanggal_selesai', 'populasi_awal', 'umur_masuk_hari'])
            ->where('status', $status)
            ->paginate(6, ['*'], 'cyclePage', total: (int) ($counts[$status] ?? 0));

        $this->loadSummaries($usaha, $cycles->getCollection());

        $cycles->through(
            fn (Siklus $siklus): array => $this->toCard($siklus),
        );

        return [
            'cycles' => $cycles,
            'counts' => [
                'berjalan' => (int) ($counts['berjalan'] ?? 0),
                'selesai' => (int) ($counts['selesai'] ?? 0),
            ],
        ];
    }

    private function cardQuery(Usaha $usaha): HasMany
    {
        return $usaha->sikluses()
            ->with([
                'penandas' => fn (HasMany $query) => $query
                    ->whereNull('selesai_at')
                    ->orderBy('tanggal')
                    ->orderBy('id'),
            ])
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id');
    }

    /** @param Collection<int, Siklus> $cycles */
    private function loadSummaries(Usaha $usaha, Collection $cycles): void
    {
        if ($cycles->isEmpty()) {
            return;
        }

        $totals = $this->getFinancialSummary->totalsQuery(
            $usaha->transaksis()->whereIn('sikluses_id', $cycles->modelKeys())->getQuery(),
        )->selectRaw('transaksis.sikluses_id')
            ->groupBy('transaksis.sikluses_id')
            ->toBase()->get()->keyBy('sikluses_id');

        foreach ($cycles as $cycle) {
            $total = $totals->get($cycle->id);
            $cycle->setAttribute('pemasukan', (int) ($total?->pemasukan ?? 0));
            $cycle->setAttribute('operasional', (int) ($total?->operasional ?? 0));
            $cycle->setAttribute('investasi', (int) ($total?->investasi ?? 0));
        }
    }

    private function tocard(Siklus $siklus): array
    {
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

        $summary = $this->getFinancialSummary->fromTotals(
            (int) $siklus->pemasukan,
            (int) $siklus->operasional,
            (int) $siklus->investasi,
        );

        return [
            'id' => $siklus->id,
            'nama' => $siklus->nama,
            'status' => $siklus->status,
            'mulai' => $siklus->tanggal_mulai->locale('id')->translatedFormat('d M Y'),
            'selesai' => $siklus->tanggal_selesai?->locale('id')->translatedFormat('d M Y'),
            'jumlah' => $siklus->populasi_awal,
            'umur' => max(
                0,
                $siklus->umur_masuk_hari
                    + (int) $siklus->tanggal_mulai->diffInDays($ageDate, false),
            ),
            'penanda' => $milestone?->nama,
            'tanggal_penanda' => $milestone?->tanggal->locale('id')->translatedFormat('d M Y'),
            'sisa_hari' => $milestone
                ? (int) $today->diffInDays($milestone->tanggal, false)
                : null,
            'panen' => $this->getHarvestSummary->handle($siklus, $harvest, $today),
            'laba_bersih' => $summary['laba_bersih'],
            'summary' => $summary,
        ];
    }

    public function latestRunning(Usaha $usaha): ?array
    {
        Gate::authorize('view', $usaha);

        $siklus = $this->cardQuery($usaha)
            ->where('status', 'berjalan')
            ->withSum([
                'transaksis as pemasukan' => fn (Builder $query) => $query
                    ->where('arah', 'pemasukan'),
                'transaksis as operasional' => fn (Builder $query) => $query
                    ->where('arah', 'pengeluaran')
                    ->whereRelation('kategori', 'klasifikasi', 'operasional'),
                'transaksis as investasi' => fn (Builder $query) => $query
                    ->where('arah', 'pengeluaran')
                    ->whereRelation('kategori', 'klasifikasi', 'investasi'),
            ], 'total')
            ->first();

        return $siklus !== null
            ? $this->toCard($siklus)
            : null;
    }
}

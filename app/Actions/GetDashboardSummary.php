<?php

namespace App\Actions;

use App\Models\Transaksi;
use App\Models\Usaha;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GetDashboardSummary
{
    public function __construct(
        private GetSiklusCards $getSiklusCards,
        private GetFinancialSummary $getFinancialSummary,
        private CacheDashboardSummary $cacheDashboardSummary,
    ) {}

    /**
     * @return array{summary: array<string, int>, chart: array{labels: list<string>, pemasukan: list<int>, laba_bersih: list<int>}, running: array<string, int>|null, cycle: array<string, mixed>|null, transactions: array<int, array<string, mixed>>}
     */
    public function handle(Usaha $usaha, string $period, int $year, string $basis, ?int $month = null): array
    {
        Gate::authorize('view', $usaha);

        Validator::make(compact('period', 'year', 'basis', 'month'), [
            'period' => [Rule::in(['bulan_ini', 'pilih_bulan', 'tahun_ini', 'pilih_tahun'])],
            'year' => ['integer', 'between:1900,9999'],
            'month' => ['required_if:period,pilih_bulan', 'nullable', 'integer', 'between:1,12'],
            'basis' => [Rule::in(['siklus_selesai', 'tanggal_transaksi'])],
        ])->validate();

        $today = CarbonImmutable::today();
        $isMonthly = in_array($period, ['bulan_ini', 'pilih_bulan'], true);
        $start = match ($period) {
            'bulan_ini' => $today->startOfMonth(),
            'pilih_bulan' => CarbonImmutable::create($year, $month, 1)->startOfDay(),
            'tahun_ini' => $today->startOfYear(),
            'pilih_tahun' => CarbonImmutable::create($year, 1, 1)->startOfDay(),
        };
        $end = $isMonthly ? $start->endOfMonth() : $start->endOfYear();

        $resolve = fn (): array => $this->calculate($usaha, $basis, $start, $end, $isMonthly);

        if ($usaha->getConnection()->transactionLevel() > 0) {
            return $resolve();
        }

        $filterKey = hash('sha256', implode('|', [
            $start->toDateString(),
            $end->toDateString(),
            $basis,
            $today->toDateString(),
            config('app.timezone'),
            app()->getLocale(),
        ]));

        return $this->cacheDashboardSummary->handle(
            (int) $usaha->getKey(),
            $filterKey,
            $resolve,
        );
    }

    /**
     * @return array{summary: array<string, int>, chart: array{labels: list<string>, pemasukan: list<int>, laba_bersih: list<int>}, running: array<string, int>|null, cycle: array<string, mixed>|null, transactions: array<int, array<string, mixed>>}
     */
    private function calculate(Usaha $usaha, string $basis, CarbonImmutable $start, CarbonImmutable $end, bool $isMonthly): array
    {
        $dates = [$start->toDateString(), $end->toDateString()];

        $transactions = Transaksi::query()->where('transaksis.usahas_id', $usaha->id)
            ->where(fn (Builder $query) => $query->whereNull('sikluses_id')->orWhereHas('siklus'));
        $periodTransactions = clone $transactions;

        if ($basis === 'tanggal_transaksi') {
            $periodTransactions->whereBetween('transaksis.tanggal', $dates);
        } else {
            $periodTransactions->where(function (Builder $query) use ($dates): void {
                $query->whereHas('siklus', fn (Builder $cycle) => $cycle->where('status', 'selesai')->whereBetween('tanggal_selesai', $dates))
                    ->orWhere(fn (Builder $general) => $general->whereNull('sikluses_id')->whereBetween('transaksis.tanggal', $dates));
            });
        }
        $cycleData = $this->getSiklusCards->latestRunning($usaha);

        return [
            'summary' => $this->getFinancialSummary->handle($periodTransactions),
            'chart' => $this->chart(clone $periodTransactions, $start, $end, $isMonthly ? 'day' : 'month', $basis),
            'running' => $basis === 'siklus_selesai'
                ? $this->getFinancialSummary->handle((clone $transactions)->whereHas('siklus', fn (Builder $query) => $query->where('status', 'berjalan')))
                : null,
            'cycle' => $cycleData,
            'transactions' => (clone $periodTransactions)->with(['kategori:id,nama', 'siklus:id,nama'])
                ->orderByDesc('tanggal')->orderByDesc('id')->limit(5)->get()
                ->map(fn (Transaksi $transaction): array => [
                    'id' => $transaction->id,
                    'tanggal' => $transaction->tanggal->locale('id')->translatedFormat('d M Y'),
                    'kategori' => $transaction->kategori->nama,
                    'siklus' => $transaction->siklus?->nama ?? 'Biaya umum usaha',
                    'arah' => $transaction->arah,
                    'total' => $transaction->total,
                ])->all(),
        ];
    }

    /**
     * @param  Builder<Transaksi>  $query
     * @return array{labels: list<string>, pemasukan: list<int>, laba_bersih: list<int>}
     */
    private function chart(Builder $query, CarbonImmutable $start, CarbonImmutable $end, string $interval, string $basis): array
    {
        $dateColumn = 'transaksis.tanggal';

        if ($basis === 'siklus_selesai') {
            $query->leftJoin('sikluses', 'sikluses.id', '=', 'transaksis.sikluses_id');
            $dateColumn = 'COALESCE(sikluses.tanggal_selesai, transaksis.tanggal)';
        }

        $bucket = "DATE_TRUNC('{$interval}', {$dateColumn})::date";
        $totals = $this->getFinancialSummary->totalsQuery($query)
            ->selectRaw("{$bucket} AS period_date")
            ->groupByRaw($bucket)
            ->toBase()->get()->keyBy('period_date');
        $chart = ['labels' => [], 'pemasukan' => [], 'laba_bersih' => []];

        for ($date = $start; $date <= $end; $date = $date->add($interval, 1)) {
            $total = $totals->get($date->toDateString());
            $summary = $this->getFinancialSummary->fromTotals(
                (int) ($total?->pemasukan ?? 0),
                (int) ($total?->operasional ?? 0),
                (int) ($total?->investasi ?? 0),
            );

            $chart['labels'][] = $date->translatedFormat($interval === 'day' ? 'd M' : 'M Y');
            $chart['pemasukan'][] = $summary['pemasukan'];
            $chart['laba_bersih'][] = $summary['laba_bersih'];
        }

        return $chart;
    }
}

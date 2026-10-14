<?php

namespace App\Actions;

use App\Models\Transaksi;
use App\Models\Usaha;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
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
        $interval = $isMonthly ? 'day' : 'month';

        $transactions = Transaksi::query()->where('transaksis.usahas_id', $usaha->id);
        $periodTransactions = clone $transactions;

        if ($basis === 'tanggal_transaksi') {
            $periodTransactions->whereBetween('transaksis.tanggal', $dates)
                ->where(fn (Builder $query) => $query->whereNull('sikluses_id')->orWhereHas('siklus'));
            $totals = $this->totalsByPeriod($periodTransactions, 'transaksis.tanggal', $interval);
        } else {
            $completedCycles = $usaha->sikluses()->select(['id', 'tanggal_selesai'])
                ->where('status', 'selesai')->whereBetween('tanggal_selesai', $dates);
            $periodTransactions->where(function (Builder $query) use ($dates, $completedCycles): void {
                $query->whereIn('sikluses_id', (clone $completedCycles)->select('id'))
                    ->orWhere(fn (Builder $general) => $general->whereNull('sikluses_id')->whereBetween('transaksis.tanggal', $dates));
            });
            $completedTransactions = (clone $transactions)->joinSub(
                $completedCycles, 'completed_cycles', 'completed_cycles.id', '=', 'transaksis.sikluses_id',
            );
            $generalTransactions = (clone $transactions)->whereNull('sikluses_id')->whereBetween('transaksis.tanggal', $dates);
            $totals = $this->totalsByPeriod($completedTransactions, 'completed_cycles.tanggal_selesai', $interval)
                ->unionAll($this->totalsByPeriod($generalTransactions, 'transaksis.tanggal', $interval));
        }
        $cycleData = $this->getSiklusCards->latestRunning($usaha);
        $periodSummary = $this->periodSummary($totals->get(), $start, $end, $interval);

        return [
            'summary' => $periodSummary['summary'],
            'chart' => $periodSummary['chart'],
            'running' => $basis === 'siklus_selesai'
                ? $this->getFinancialSummary->handle((clone $transactions)->whereIn('sikluses_id', $usaha->sikluses()->select('id')->where('status', 'berjalan')))
                : null,
            'cycle' => $cycleData,
            'transactions' => (clone $periodTransactions)->with(['kategori:id,nama', 'siklus:id,nama'])
                ->orderByDesc('tanggal')->orderByDesc('id')->limit(5)
                ->get(['id', 'tanggal', 'kategoris_id', 'sikluses_id', 'arah', 'total'])
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
     */
    private function totalsByPeriod(Builder $query, string $dateColumn, string $interval): QueryBuilder
    {
        $bucket = $interval === 'day' ? $dateColumn : "DATE_FORMAT({$dateColumn}, '%Y-%m-01')";

        return $this->getFinancialSummary->totalsQuery($query)
            ->selectRaw("{$bucket} AS period_date")
            ->groupByRaw($bucket)
            ->toBase();
    }

    /**
     * @param  Collection<int, object{period_date: string, pemasukan: numeric-string, operasional: numeric-string, investasi: numeric-string}>  $totals
     * @return array{summary: array<string, int>, chart: array{labels: list<string>, pemasukan: list<int>, laba_bersih: list<int>}}
     */
    private function periodSummary(Collection $totals, CarbonImmutable $start, CarbonImmutable $end, string $interval): array
    {
        $periods = $totals->groupBy('period_date');
        $chart = ['labels' => [], 'pemasukan' => [], 'laba_bersih' => []];

        for ($date = $start; $date <= $end; $date = $date->add($interval, 1)) {
            $total = $periods->get($date->toDateString(), collect());
            $summary = $this->getFinancialSummary->fromTotals(
                (int) $total->sum('pemasukan'),
                (int) $total->sum('operasional'),
                (int) $total->sum('investasi'),
            );

            $chart['labels'][] = $date->translatedFormat($interval === 'day' ? 'd M' : 'M Y');
            $chart['pemasukan'][] = $summary['pemasukan'];
            $chart['laba_bersih'][] = $summary['laba_bersih'];
        }

        return [
            'summary' => $this->getFinancialSummary->fromTotals(
                (int) $totals->sum('pemasukan'),
                (int) $totals->sum('operasional'),
                (int) $totals->sum('investasi'),
            ),
            'chart' => $chart,
        ];
    }
}

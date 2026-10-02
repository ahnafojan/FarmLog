<?php

namespace App\Actions;

use App\Models\Transaksi;
use App\Models\Usaha;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class GetDashboardSummary
{
    public function __construct(private GetHarvestSummary $getHarvestSummary) {}

    /**
     * @return array{summary: array<string, int>, chart: array{labels: list<string>, pemasukan: list<int>, laba_bersih: list<int>}, running: array<string, int>|null, cycle: array<string, mixed>|null, transactions: array<int, array<string, mixed>>}
     */
    public function handle(Usaha $usaha, string $period, int $year, string $basis, ?int $month = null): array
    {
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

        $cycle = $usaha->sikluses()->where('status', 'berjalan')
            ->orderByDesc('tanggal_mulai')->orderByDesc('id')->first();
        $cycleData = null;

        if ($cycle !== null) {
            $milestone = $cycle->penandas()->whereNull('selesai_at')->whereDate('tanggal', '>=', $today)
                ->orderBy('tanggal')->orderBy('id')->first();
            $harvest = $cycle->penandas()->whereNull('selesai_at')->whereIn('jenis', ['panen', 'afkir'])
                ->orderBy('tanggal')->orderBy('id')->first();
            $currentAge = max(0, $cycle->umur_masuk_hari + (int) $cycle->tanggal_mulai->diffInDays($today, false));
            $cycleSummary = $this->summarize((clone $transactions)->where('sikluses_id', $cycle->id));

            $cycleData = [
                'id' => $cycle->id,
                'nama' => $cycle->nama,
                'jumlah' => $cycle->populasi_awal,
                'umur' => $currentAge,
                'mulai' => $cycle->tanggal_mulai->format('d/m/Y'),
                'penanda' => $milestone?->nama,
                'tanggal_penanda' => $milestone?->tanggal->format('d/m/Y'),
                'sisa_hari' => $milestone ? (int) $today->diffInDays($milestone->tanggal) : null,
                'laba_bersih' => $cycleSummary['laba_bersih'],
                'panen' => $this->getHarvestSummary->handle($cycle, $harvest, $today),
            ];
        }

        return [
            'summary' => $this->summarize(clone $periodTransactions),
            'chart' => $this->chart(clone $periodTransactions, $start, $end, $isMonthly ? 'day' : 'month', $basis),
            'running' => $basis === 'siklus_selesai'
                ? $this->summarize((clone $transactions)->whereHas('siklus', fn (Builder $query) => $query->where('status', 'berjalan')))
                : null,
            'cycle' => $cycleData,
            'transactions' => (clone $periodTransactions)->with(['kategori:id,nama', 'siklus:id,nama'])
                ->orderByDesc('tanggal')->orderByDesc('id')->limit(5)->get()
                ->map(fn (Transaksi $transaction): array => [
                    'id' => $transaction->id,
                    'tanggal' => $transaction->tanggal->format('d/m/Y'),
                    'kategori' => $transaction->kategori->nama,
                    'siklus' => $transaction->siklus?->nama ?? 'Biaya umum usaha',
                    'arah' => $transaction->arah,
                    'total' => $transaction->total,
                ])->all(),
        ];
    }

    /**
     * @param  Builder<Transaksi>  $query
     * @return array{pemasukan: int, operasional: int, investasi: int, laba_bersih: int}
     */
    private function summarize(Builder $query): array
    {
        $totals = $this->financialTotals($query)->toBase()->first();

        $income = (int) $totals->pemasukan;
        $operational = (int) $totals->operasional;

        return [
            'pemasukan' => $income,
            'operasional' => $operational,
            'investasi' => (int) $totals->investasi,
            'laba_bersih' => $income - $operational,
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
        $totals = $this->financialTotals($query)
            ->selectRaw("{$bucket} AS period_date")
            ->groupByRaw($bucket)
            ->toBase()->get()->keyBy('period_date');
        $chart = ['labels' => [], 'pemasukan' => [], 'laba_bersih' => []];

        for ($date = $start; $date <= $end; $date = $date->add($interval, 1)) {
            $total = $totals->get($date->toDateString());
            $income = (int) ($total?->pemasukan ?? 0);
            $operational = (int) ($total?->operasional ?? 0);

            $chart['labels'][] = $date->translatedFormat($interval === 'day' ? 'd M' : 'M Y');
            $chart['pemasukan'][] = $income;
            $chart['laba_bersih'][] = $income - $operational;
        }

        return $chart;
    }

    /**
     * @param  Builder<Transaksi>  $query
     * @return Builder<Transaksi>
     */
    private function financialTotals(Builder $query): Builder
    {
        return $query->join('kategoris', 'kategoris.id', '=', 'transaksis.kategoris_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksis.arah = 'pemasukan' THEN transaksis.total ELSE 0 END), 0) AS pemasukan")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksis.arah = 'pengeluaran' AND kategoris.klasifikasi = 'operasional' THEN transaksis.total ELSE 0 END), 0) AS operasional")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksis.arah = 'pengeluaran' AND kategoris.klasifikasi = 'investasi' THEN transaksis.total ELSE 0 END), 0) AS investasi");
    }
}

<?php

namespace App\Actions;

use App\Models\Transaksi;
use Illuminate\Database\Eloquent\Builder;

class GetFinancialSummary
{
    /**
     * @param  Builder<Transaksi>  $query
     * @return array{pemasukan: int, operasional: int, investasi: int, laba_bersih: int}
     */
    public function handle(Builder $query): array
    {
        $totals = $this->totalsQuery($query)->reorder()->toBase()->first();

        return $this->fromTotals(
            (int) $totals->pemasukan,
            (int) $totals->operasional,
            (int) $totals->investasi,
        );
    }

    /**
     * @param  Builder<Transaksi>  $query
     * @return Builder<Transaksi>
     */
    public function totalsQuery(Builder $query): Builder
    {
        return (clone $query)->join('kategoris', 'kategoris.id', '=', 'transaksis.kategoris_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksis.arah = 'pemasukan' THEN transaksis.total ELSE 0 END), 0) AS pemasukan")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksis.arah = 'pengeluaran' AND kategoris.klasifikasi = 'operasional' THEN transaksis.total ELSE 0 END), 0) AS operasional")
            ->selectRaw("COALESCE(SUM(CASE WHEN transaksis.arah = 'pengeluaran' AND kategoris.klasifikasi = 'investasi' THEN transaksis.total ELSE 0 END), 0) AS investasi");
    }

    /**
     * @return array{pemasukan: int, operasional: int, investasi: int, laba_bersih: int}
     */
    public function fromTotals(int $income, int $operational, int $investment): array
    {
        return [
            'pemasukan' => $income,
            'operasional' => $operational,
            'investasi' => $investment,
            'laba_bersih' => $income - $operational,
        ];
    }
}

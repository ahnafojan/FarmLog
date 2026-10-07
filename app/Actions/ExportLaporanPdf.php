<?php

namespace App\Actions;

use App\Models\Transaksi;
use App\Models\Usaha;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportLaporanPdf
{
    public function __construct(private GetFinancialSummary $getFinancialSummary) {}

    public function handle(
        User $user,
        Usaha $usaha,
        string $jenis,
        CarbonImmutable $mulai,
        CarbonImmutable $selesai,
        bool $bulanan,
        string $klasifikasi = 'semua',
    ): StreamedResponse {
        Gate::forUser($user)->authorize('view', $usaha);

        abort_unless(in_array($jenis, ['penjualan', 'pengeluaran'], true), 404);
        Validator::make(
            ['klasifikasi' => $klasifikasi],
            [
                'klasifikasi' => [
                    'required',
                    Rule::in(['semua', 'operasional', 'investasi']),
                ],
            ],
        )->validate();

        if ($jenis === 'penjualan') {
            $klasifikasi = 'semua';
        }

        $klasifikasiLabel = match ($klasifikasi) {
            'operasional' => 'Operasional',
            'investasi' => 'Investasi',
            default => 'Semua pengeluaran',
        };

        $query = Transaksi::query()
            ->where('transaksis.usahas_id', $usaha->getKey())
            ->where(fn (Builder $query) => $query->whereNull('sikluses_id')->orWhereHas('siklus'))
            ->whereBetween('transaksis.tanggal', [$mulai->toDateString(), $selesai->toDateString()]);

        if ($jenis === 'pengeluaran') {
            $query->where('transaksis.arah', 'pengeluaran');

            if ($klasifikasi !== 'semua') {
                $query->whereRelation(
                    'kategori',
                    'klasifikasi',
                    $klasifikasi,
                );
            }
        }
        $summary = $this->getFinancialSummary->handle($query);
        $transaksis = $query->with('kategori')
            ->where('arah', $jenis === 'penjualan' ? 'pemasukan' : 'pengeluaran')
            ->orderBy('tanggal')->orderBy('id')->get();

        $printedAt = CarbonImmutable::now(config('dompdf.report_timezone'))->locale('id');

        $pdf = Pdf::loadView('filament.pages.laporan-pdf', [
            'usaha' => $usaha,
            'printedBy' => $user->name,
            'printedAt' => $printedAt,
            'printedTimezone' => match ($printedAt->getTimezone()->getName()) {
                'Asia/Jakarta', 'Asia/Pontianak' => 'WIB',
                'Asia/Makassar' => 'WITA',
                'Asia/Jayapura' => 'WIT',
                default => $printedAt->getTimezone()->getName(),
            },
            'jenis' => $jenis,
            'judul' => $jenis === 'penjualan'
                ? 'Laporan Penjualan dan Laba Bersih'
                : ($klasifikasi === 'semua'
                    ? 'Laporan Pengeluaran'
                    : 'Laporan Pengeluaran '.$klasifikasiLabel),
            'periode' => $bulanan ? $mulai->locale('id')->translatedFormat('F Y') : 'Tahun '.$mulai->format('Y'),
            'transaksis' => $transaksis,
            'totalPenjualan' => $summary['pemasukan'],
            'totalOperasional' => $summary['operasional'],
            'totalInvestasi' => $summary['investasi'],
            'totalPengeluaran' => $jenis === 'pengeluaran' ? (int) $transaksis->sum('total') : 0,
            'labaBersih' => $summary['laba_bersih'],
            'klasifikasi' => $klasifikasi,
            'klasifikasiLabel' => $klasifikasiLabel,
        ])->setPaper('a4', 'portrait');

        $kodePeriode = $mulai->format($bulanan ? 'Y-m' : 'Y');
        $namaFile = $jenis === 'pengeluaran'
            ? "laporan-pengeluaran-{$klasifikasi}-{$kodePeriode}.pdf"
            : "laporan-{$jenis}-{$kodePeriode}.pdf";

        return response()->streamDownload(
            function () use ($pdf): void {
                echo $pdf->output();
            },
            $namaFile,
            ['Content-Type' => 'application/pdf'],
        );
    }
}

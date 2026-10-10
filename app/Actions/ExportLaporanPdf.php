<?php

namespace App\Actions;

use App\Models\Transaksi;
use App\Models\Usaha;
use App\Models\User;
use Barryvdh\DomPDF\PDF;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

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
        ?int $siklusId = null,
    ): StreamedResponse {
        Gate::forUser($user)->authorize('view', $usaha);
        abort_unless(in_array($jenis, ['penjualan', 'pengeluaran'], true), 404);
        Validator::make(['klasifikasi' => $klasifikasi], [
            'klasifikasi' => ['required', Rule::in(['semua', 'operasional', 'investasi'])],
        ])->validate();

        $siklus = $siklusId === null ? null : $usaha->sikluses()->find($siklusId);

        if ($siklusId !== null && $siklus === null) {
            throw ValidationException::withMessages(['sikluses_id' => 'Pilih siklus yang tersedia dari usaha ini.']);
        }

        if ($jenis === 'penjualan') {
            $klasifikasi = 'semua';
        }

        $query = Transaksi::query()
            ->where('transaksis.usahas_id', $usaha->getKey())
            ->where(fn (Builder $query) => $query->whereNull('sikluses_id')->orWhereHas('siklus'))
            ->whereBetween('transaksis.tanggal', [$mulai->toDateString(), $selesai->toDateString()]);

        if ($siklus !== null) {
            $query->where('transaksis.sikluses_id', $siklus->getKey());
        }

        if ($jenis === 'pengeluaran') {
            $query->where('transaksis.arah', 'pengeluaran');

            if ($klasifikasi !== 'semua') {
                $query->whereRelation('kategori', 'klasifikasi', $klasifikasi);
            }
        }

        $printedAt = CarbonImmutable::now(config('dompdf.report_timezone'))->locale('id');
        $klasifikasiLabel = match ($klasifikasi) {
            'operasional' => 'Operasional',
            'investasi' => 'Investasi',
            default => 'Semua pengeluaran',
        };
        $data = [
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
                : ($klasifikasi === 'semua' ? 'Laporan Pengeluaran' : 'Laporan Pengeluaran '.$klasifikasiLabel),
            'periode' => $bulanan ? $mulai->locale('id')->translatedFormat('F Y') : 'Tahun '.$mulai->format('Y'),
            'siklusLabel' => $siklus?->nama ?? 'Semua siklus',
            'klasifikasi' => $klasifikasi,
            'klasifikasiLabel' => $klasifikasiLabel,
        ];
        $kodePeriode = $mulai->format($bulanan ? 'Y-m' : 'Y');
        $kodeSiklus = $siklus === null ? '' : '-siklus-'.$siklus->getKey();
        $namaFile = $jenis === 'pengeluaran'
            ? "laporan-pengeluaran-{$klasifikasi}-{$kodePeriode}{$kodeSiklus}"
            : "laporan-{$jenis}-{$kodePeriode}{$kodeSiklus}";
        $timeout = max(1, (int) config('dompdf.report_timeout_seconds', 180));
        $currentTimeout = (int) ini_get('max_execution_time');

        if ($currentTimeout > 0 && $currentTimeout < $timeout && function_exists('set_time_limit')) {
            set_time_limit($timeout);
        }

        $directory = storage_path('app/private/laporan-exports/'.Str::uuid());
        File::ensureDirectoryExists($directory, 0700);
        $cleanup = static function () use ($directory): void {
            File::deleteDirectory($directory);
        };
        register_shutdown_function($cleanup);

        try {
            $path = DB::transaction(fn (): string => $this->writeDocuments(
                $query, $data, $directory, $namaFile, ! $bulanan && $siklusId === null,
            ));

            return response()->streamDownload(
                static function () use ($path, $cleanup): void {
                    try {
                        readfile($path);
                    } finally {
                        $cleanup();
                    }
                },
                basename($path),
                ['Content-Type' => str_ends_with($path, '.zip') ? 'application/zip' : 'application/pdf'],
            );
        } catch (Throwable $exception) {
            $cleanup();

            throw $exception;
        }
    }

    /**
     * @param  Builder<Transaksi>  $query
     * @param  array<string, mixed>  $data
     */
    private function writeDocuments(Builder $query, array $data, string $directory, string $name, bool $forceZip): string
    {
        $limit = max(1, (int) config('dompdf.report_rows_per_pdf', 300));
        $details = $this->detailsQuery($query, $data['jenis']);
        $count = (clone $details)->count();
        $report = $this->summaryData($query, $data, $count);

        if (! $forceZip && $count <= $limit) {
            $path = $directory.'/'.$name.'.pdf';
            $this->writePdf($path, [...$report, 'transaksis' => $details->limit($limit)->get()]);

            return $path;
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip diperlukan untuk mengunduh laporan besar.');
        }

        $path = $directory.'/'.$name.'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arsip laporan tidak dapat dibuat.');
        }

        try {
            $summaryPath = $directory.'/000-ringkasan.pdf';
            $this->writePdf($summaryPath, [...$report, 'transaksis' => collect(), 'summaryOnly' => true]);
            $this->addToArchive($zip, $summaryPath);

            $groups = (clone $query)->select('sikluses_id')->distinct()
                ->with('siklus:id,nama')->orderBy('sikluses_id')->lazy(100);

            foreach ($groups as $group) {
                $groupQuery = (clone $query)->where('sikluses_id', $group->sikluses_id);
                $groupDetails = $this->detailsQuery($groupQuery, $data['jenis']);
                $groupCount = (clone $groupDetails)->count();
                $groupData = $this->summaryData($groupQuery, [
                    ...$data,
                    'siklusLabel' => $group->siklus?->nama ?? 'Transaksi umum',
                ], $groupCount);
                $prefix = $group->sikluses_id === null ? 'transaksi-umum' : 'siklus-'.$group->sikluses_id;
                $part = 0;
                $last = null;

                do {
                    $page = clone $groupDetails;

                    if ($last !== null) {
                        $page->where(fn (Builder $after) => $after
                            ->where('tanggal', '>', $last->tanggal->toDateString())
                            ->orWhere(fn (Builder $sameDay) => $sameDay
                                ->where('tanggal', $last->tanggal->toDateString())->where('id', '>', $last->id)));
                    }

                    $rows = $page->limit($limit)->get();
                    $part++;
                    $partPath = $directory.'/'.$prefix.'-bagian-'.sprintf('%03d', $part).'.pdf';
                    $this->writePdf($partPath, [
                        ...$groupData,
                        'transaksis' => $rows,
                        'partLabel' => 'Bagian '.$part.' dari '.max(1, (int) ceil($groupCount / $limit)),
                        'rowOffset' => ($part - 1) * $limit,
                    ]);
                    $this->addToArchive($zip, $partPath);
                    $last = $rows->last();
                } while ($part * $limit < $groupCount);
            }
        } catch (Throwable $exception) {
            $zip->close();

            throw $exception;
        }

        if (! $zip->close()) {
            throw new RuntimeException('Arsip laporan tidak dapat disimpan.');
        }

        return $path;
    }

    /**
     * @param  Builder<Transaksi>  $query
     * @return Builder<Transaksi>
     */
    private function detailsQuery(Builder $query, string $jenis): Builder
    {
        return (clone $query)->where('arah', $jenis === 'penjualan' ? 'pemasukan' : 'pengeluaran')
            ->select(['id', 'kategoris_id', 'tanggal', 'qty', 'satuan', 'harga_satuan', 'total', 'pembeli', 'catatan'])
            ->with('kategori:id,nama,klasifikasi')->orderBy('tanggal')->orderBy('id');
    }

    /**
     * @param  Builder<Transaksi>  $query
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function summaryData(Builder $query, array $data, int $count): array
    {
        $summary = $this->getFinancialSummary->handle($query);

        return [
            ...$data,
            'totalPenjualan' => $summary['pemasukan'],
            'totalOperasional' => $summary['operasional'],
            'totalInvestasi' => $summary['investasi'],
            'totalPengeluaran' => $summary['operasional'] + $summary['investasi'],
            'labaBersih' => $summary['laba_bersih'],
            'transactionCount' => $count,
            'summaryOnly' => false,
        ];
    }

    /** @param array<string, mixed> $data */
    private function writePdf(string $path, array $data): void
    {
        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper');

        try {
            $pdf->loadView('filament.pages.laporan-pdf', $data)->setPaper('a4', 'portrait')->save($path);
        } finally {
            unset($pdf);
            gc_collect_cycles();
        }
    }

    private function addToArchive(ZipArchive $zip, string $path): void
    {
        if (! $zip->addFile($path, basename($path))) {
            throw new RuntimeException('Bagian PDF tidak dapat ditambahkan ke arsip.');
        }
    }
}

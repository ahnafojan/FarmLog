<?php

namespace App\Http\Controllers;

use App\Actions\ExportLaporanPdf;
use App\Models\Usaha;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadLaporanController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Usaha $usaha, ExportLaporanPdf $export): StreamedResponse
    {
        abort_unless($request->user()->can('view', $usaha), 404);

        $data = $request->validate([
            'jenis' => ['required', Rule::in(['penjualan', 'pengeluaran'])],
            'periode' => ['required', Rule::in(['bulanan', 'tahunan'])],
            'tahun' => ['required', 'integer', 'between:1900,9999'],
            'bulan' => ['required_if:periode,bulanan', 'nullable', 'integer', 'between:1,12'],
            'klasifikasi' => ['sometimes', Rule::in(['semua', 'operasional', 'investasi'])],
            'sikluses_id' => ['nullable', 'integer', Rule::exists('sikluses', 'id')
                ->where('usahas_id', $usaha->id)->whereNull('deleted_at')],
        ]);

        $bulanan = $data['periode'] === 'bulanan';
        $mulai = CarbonImmutable::create((int) $data['tahun'], $bulanan ? (int) $data['bulan'] : 1, 1)->startOfDay();

        return $export->handle(
            $request->user(),
            $usaha,
            $data['jenis'],
            $mulai,
            $bulanan ? $mulai->endOfMonth() : $mulai->endOfYear(),
            $bulanan,
            $data['klasifikasi'] ?? 'semua',
            isset($data['sikluses_id']) ? (int) $data['sikluses_id'] : null,
        );
    }
}

<?php

use App\Models\Transaksi;
use Carbon\CarbonImmutable;

function laporanPdfData(string $jenis, array $transaksis = []): array
{
    return [
        'usaha' => (object) ['nama' => 'Usaha Uji'],
        'printedBy' => 'Pemilik Usaha',
        'printedAt' => CarbonImmutable::parse('2026-10-05 10:00:00', 'Asia/Jakarta'),
        'printedTimezone' => 'WIB',
        'jenis' => $jenis,
        'klasifikasi' => 'semua',
        'klasifikasiLabel' => 'Semua pengeluaran',
        'judul' => 'Laporan Usaha',
        'periode' => 'Oktober 2026',
        'transaksis' => collect($transaksis),
        'totalPenjualan' => 0,
        'totalOperasional' => 0,
        'totalInvestasi' => 0,
        'totalPengeluaran' => 0,
        'labaBersih' => 0,
    ];
}

test('sales report displays the entered quantity unit and unit price', function (string $qty, string $expectedQuantity) {
    $transaksi = (new Transaksi([
        'tanggal' => '2026-10-01',
        'qty' => $qty,
        'satuan' => 'kg',
        'harga_satuan' => 12500,
        'total' => (int) round((float) $qty * 12500),
    ]))->setRelation('kategori', null);

    $view = $this->view('filament.pages.laporan-pdf', laporanPdfData('penjualan', [$transaksi]));

    $view->assertSeeTextInOrder(['Jumlah', 'Harga satuan', $expectedQuantity, 'kg', 'Rp 12.500']);
})->with([
    'whole quantity' => ['10.000', '10'],
    'fractional quantity' => ['2.500', '2,5'],
    'three decimal places' => ['1.125', '1,125'],
]);

test('sales report distinguishes missing unit prices from a zero price', function () {
    $missing = (new Transaksi(['total' => 10000]))->setRelation('kategori', null);
    $zero = (new Transaksi(['qty' => 1, 'satuan' => 'ekor', 'harga_satuan' => 0, 'total' => 0]))
        ->setRelation('kategori', null);

    $view = $this->view('filament.pages.laporan-pdf', laporanPdfData('penjualan', [$missing, $zero]));

    $view->assertSeeTextInOrder(['Nominal', '-', '-', '-', '-', 'Rp 10.000', '1', 'ekor', 'Rp 0']);
});

test('expense report keeps its existing detail columns', function () {
    $view = $this->view('filament.pages.laporan-pdf', laporanPdfData('pengeluaran'));

    $view->assertSeeText('Klasifikasi')
        ->assertDontSeeText('Harga satuan')
        ->assertDontSeeText('Jumlah')
        ->assertSeeText('Belum ada pengeluaran');
});

test('sales report renders an empty period with the quantity and price headings', function () {
    $view = $this->view('filament.pages.laporan-pdf', laporanPdfData('penjualan'));

    $view->assertSeeTextInOrder(['Jumlah', 'Harga satuan', 'Belum ada penjualan']);
});

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>{{ $judul }} - {{ $usaha->nama }}</title>
    <style>
        @page {
            margin: 36pt 32pt 46pt;
        }

        body {
            margin: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9pt;
            line-height: 1.5;
            color: #263b35;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        td,
        th {
            vertical-align: top;
        }

        h1,
        h2,
        p {
            margin: 0;
        }

        .muted {
            color: #73817b;
        }

        .positive {
            color: #15803d;
        }

        .negative {
            color: #b91c1c;
        }

        .eyebrow {
            font-size: 7pt;
            letter-spacing: 1.5pt;
            text-transform: uppercase;
        }

        .letterhead {
            border-bottom: 3pt solid #244b3d;
            padding-bottom: 17pt;
            margin-bottom: 20pt;
        }

        .letterhead td {
            vertical-align: middle;
        }

        .logo-cell {
            width: 76pt;
            padding-right: 15pt;
        }

        .logo {
            width: 72pt;
            height: 72pt;
        }

        .business-name {
            font-size: 23pt;
            line-height: 1.2;
            font-weight: bold;
            overflow-wrap: break-word;
        }

        .business-caption {
            margin-top: 6pt;
            font-size: 8pt;
            color: #73817b;
        }

        .document-mark {
            width: 84pt;
            padding-left: 14pt;
            text-align: right;
        }

        .document-mark strong {
            display: block;
            font-size: 10pt;
            letter-spacing: 1pt;
        }

        .document-mark span {
            font-size: 7pt;
            color: #73817b;
        }

        .report-heading {
            margin-bottom: 15pt;
        }

        .report-heading h1 {
            margin-top: 5pt;
            font-size: 16pt;
            line-height: 1.35;
        }

        .metadata {
            margin-bottom: 19pt;
            border-bottom: 1pt solid #e0e7e3;
        }

        .metadata td {
            padding: 0 10pt 13pt 0;
        }

        .metadata .label {
            font-size: 7pt;
            color: #73817b;
            margin-bottom: 3pt;
        }

        .metadata .value {
            font-size: 9pt;
            font-weight: bold;
            overflow-wrap: break-word;
        }

        .metadata .printed-by {
            padding-top: 6pt;
        }

        .summary {
            margin-bottom: 23pt;
            page-break-inside: avoid;
            table-layout: fixed;
        }

        .summary td {
            width: 33.33%;
            padding: 12pt 10pt;
            border: 1pt solid #e0e7e3;
            background: #f6f8f7;
        }

        .summary .profit {
            background: #edf7f0;
            border-top: 3pt solid #15803d;
        }

        .summary .expense {
            background: #fff1f2;
            border-top: 3pt solid #b91c1c;
        }

        .summary .label {
            font-size: 7pt;
            margin-bottom: 7pt;
            color: #596b62;
        }

        .summary .amount {
            font-size: 12pt;
            font-weight: bold;
            overflow-wrap: break-word;
        }

        .summary .hint {
            font-size: 6.5pt;
            color: #73817b;
            margin-top: 5pt;
        }

        .section-heading {
            margin-bottom: 8pt;
            page-break-after: avoid;
        }

        .section-heading h2 {
            font-size: 10pt;
        }

        .section-heading td:last-child {
            text-align: right;
            font-size: 8pt;
            color: #73817b;
        }

        .transactions {
            table-layout: fixed;
            font-size: 8pt;
        }

        .transactions thead {
            display: table-header-group;
        }

        .transactions th {
            padding: 9pt 7pt;
            background: #244b3d;
            color: #fff;
            font-size: 7pt;
            text-align: left;
        }

        .expense-report .transactions th {
            background: #863c3c;
        }

        .transactions td {
            padding: 10pt 7pt;
            border-bottom: 1pt solid #e5eae7;
            overflow-wrap: break-word;
        }

        .transactions tr.alternate td {
            background: #f6f8f7;
        }

        .transactions .number {
            text-align: center;
            color: #73817b;
        }

        .transactions .nominal {
            text-align: right;
        }

        .transactions .category {
            font-weight: bold;
        }

        .transactions .note {
            margin-top: 4pt;
            font-size: 7pt;
            line-height: 1.4;
            color: #73817b;
            white-space: pre-line;
        }

        .transactions .empty {
            padding: 25pt 12pt;
            text-align: center;
            color: #73817b;
        }

        .closing {
            margin-top: 16pt;
            page-break-inside: avoid;
        }

        .total-line {
            border-top: 2pt solid #244b3d;
        }

        .total-line td {
            padding: 10pt 7pt;
            font-size: 10pt;
            font-weight: bold;
        }

        .total-line td:last-child {
            text-align: right;
        }

        .expense-report .total-line {
            border-top-color: #b91c1c;
        }

        .explanation {
            margin-top: 16pt;
            padding: 11pt 13pt;
            border-left: 3pt solid #cbd8d0;
            background: #f6f8f7;
            font-size: 7.5pt;
            color: #596b62;
        }

        .explanation strong {
            display: block;
            margin-bottom: 3pt;
            color: #263b35;
        }

        .footer {
            position: fixed;
            bottom: -29pt;
            left: 0;
            right: 0;
            border-top: 1pt solid #e0e7e3;
            padding-top: 7pt;
            font-size: 7pt;
            color: #73817b;
        }

        .footer td:last-child {
            text-align: right;
        }

        .page-number:after {
            content: counter(page);
        }
    </style>
</head>

<body class="{{ $jenis === 'penjualan' ? 'sales-report' : 'expense-report' }}">
    @php
        $isSales = $jenis === 'penjualan';
        $rupiah = fn(int $amount): string => 'Rp ' . number_format($amount, 0, ',', '.');
    @endphp

    <div class="footer">
        <table>
            <tr>
                <td>PariwangiGroup &middot; {{ $isSales ? 'Laporan penjualan & laba' : 'Laporan pengeluaran' }}</td>
                <td>Halaman <span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <div class="letterhead">
        <table>
            <tr>
                <td class="logo-cell">
                    <img class="logo" src="{{ public_path('logos-black.webp') }}" alt="Logo PariwangiGroup">
                </td>
                <td>
                    <p class="eyebrow muted">Laporan keuangan usaha</p>
                    <p class="business-name">{{ $usaha->nama }}</p>
                    <p class="business-caption">Pencatatan usaha melalui PariwangiGroup</p>
                </td>
                <td class="document-mark">
                    <strong>PariwangiGroup</strong>
                    <span>REKAP KEUANGAN</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="report-heading">
        <p class="eyebrow {{ $isSales ? 'positive' : 'negative' }}">
            {{ $isSales ? 'Penjualan & profitabilitas' : 'Pengeluaran usaha' }}</p>
        <h1>{{ $judul }}</h1>
    </div>

    <table class="metadata">
        <tr>
            <td style="width: 34%">
                <p class="label">PERIODE LAPORAN</p>
                <p class="value">{{ $periode }}</p>
            </td>
            <td style="width: 31%">
                <p class="label">DASAR PENCATATAN</p>
                <p class="value">Tanggal transaksi</p>
            </td>
            <td>
                <p class="label">TANGGAL CETAK</p>
                <p class="value">{{ $printedAt->translatedFormat('d M Y') }}</p>
                <p class="muted">{{ $printedAt->format('H:i') }} {{ $printedTimezone }}</p>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="printed-by">
                <p class="label">DICETAK OLEH</p>
                <p class="value">{{ $printedBy }}</p>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            @if ($isSales)
                <td>
                    <p class="label">TOTAL PENJUALAN</p>
                    <p class="amount positive">{{ $rupiah($totalPenjualan) }}</p>
                    <p class="hint">Pemasukan pada periode ini</p>
                </td>
                <td>
                    <p class="label">BIAYA OPERASIONAL</p>
                    <p class="amount negative">{{ $rupiah($totalOperasional) }}</p>
                    <p class="hint">Pengurang laba usaha</p>
                </td>
                <td class="{{ $labaBersih < 0 ? 'expense' : 'profit' }}">
                    <p class="label">{{ $labaBersih < 0 ? 'RUGI BERSIH' : 'LABA BERSIH' }}</p>
                    <p class="amount {{ $labaBersih < 0 ? 'negative' : 'positive' }}">{{ $rupiah($labaBersih) }}</p>
                    <p class="hint">Penjualan dikurangi operasional</p>
                </td>
            @else
                <td>
                    <p class="label">OPERASIONAL</p>
                    <p class="amount negative">{{ $rupiah($totalOperasional) }}</p>
                    <p class="hint">Biaya kegiatan usaha</p>
                </td>
                <td>
                    <p class="label">INVESTASI</p>
                    <p class="amount negative">{{ $rupiah($totalInvestasi) }}</p>
                    <p class="hint">Pengeluaran kategori investasi</p>
                </td>
                <td class="expense">
                    <p class="label">TOTAL PENGELUARAN</p>
                    <p class="amount negative">{{ $rupiah($totalPengeluaran) }}</p>
                    <p class="hint">Seluruh pengeluaran periode ini</p>
                </td>
            @endif
        </tr>
    </table>

    <table class="section-heading">
        <tr>
            <td>
                <h2>{{ $isSales ? 'Rincian penjualan' : 'Rincian pengeluaran' }}</h2>
            </td>
            <td>{{ $transaksis->count() }} transaksi</td>
        </tr>
    </table>

    <table class="transactions">
        <thead>
            <tr>
                <th style="width: 5%">No.</th>
                <th style="width: 16%">Tanggal</th>
                <th style="width: 30%">Kategori / catatan</th>
                <th style="width: 20%">{{ $isSales ? 'Pembeli' : 'Klasifikasi' }}</th>
                <th style="width: 29%" class="nominal">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transaksis as $transaksi)
                <tr class="{{ $loop->even ? 'alternate' : '' }}">
                    <td class="number">{{ $loop->iteration }}</td>
                    <td>{{ $transaksi->tanggal?->format('d/m/Y') }}</td>
                    <td>
                        <p class="category">{{ $transaksi->kategori?->nama ?? '-' }}</p>
                        @if (filled($transaksi->catatan))
                            <p class="note">{{ $transaksi->catatan }}</p>
                        @endif
                    </td>
                    <td>
                        {{ $isSales ? ($transaksi->pembeli ?: '-') : ucfirst($transaksi->kategori?->klasifikasi ?? '-') }}
                    </td>
                    <td class="nominal {{ $isSales ? 'positive' : 'negative' }}">
                        <strong>{{ $rupiah($transaksi->total) }}</strong>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">
                        Belum ada {{ $isSales ? 'penjualan' : 'pengeluaran' }} pada periode {{ $periode }}.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="closing">
        <table class="total-line">
            <tr>
                <td>{{ $isSales ? 'Total penjualan' : 'Total pengeluaran' }}</td>
                <td class="{{ $isSales ? 'positive' : 'negative' }}">
                    {{ $rupiah($isSales ? $totalPenjualan : $totalPengeluaran) }}
                </td>
            </tr>
        </table>

        <div class="explanation">
            <strong>Catatan laporan</strong>
            @if ($isSales)
                Laba bersih dihitung dari total penjualan dikurangi pengeluaran operasional.
                Pengeluaran investasi dicatat terpisah dan tidak mengurangi laba pada laporan ini.
            @else
                Pengeluaran dikelompokkan menjadi operasional dan investasi sesuai kategori transaksi.
                Rincian di atas mencakup pengeluaran pada periode yang dipilih.
            @endif
        </div>
    </div>
</body>

</html>

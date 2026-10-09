<?php

return [
    'jenis' => [
        'ayam-petelur' => [
            'nama' => 'Ayam Petelur',
            'rekap_default' => 'tanggal_transaksi',
            'kategoris' => [
                ['nama' => 'Ayam/DOC', 'kode_sistem' => 'bibit', 'satuan_default' => 'ekor'],
                ['nama' => 'Pakan', 'satuan_default' => 'kg'],
                ['nama' => 'Vaksin/vitamin/obat'],
                ['nama' => 'Gaji'],
                ['nama' => 'Listrik/air'],
                ['nama' => 'Lain-lain'],
                ['nama' => 'Kandang/peralatan', 'klasifikasi' => 'investasi'],
                ['nama' => 'Penjualan telur', 'arah' => 'pemasukan', 'satuan_default' => 'kg'],
                ['nama' => 'Penjualan ayam afkir', 'arah' => 'pemasukan', 'satuan_default' => 'ekor'],
            ],
            'penandas' => [
                ['nama' => 'Mulai bertelur', 'jenis' => 'mulai_produksi', 'hari_ke' => 126],
                ['nama' => 'Puncak produksi', 'jenis' => 'puncak', 'hari_ke' => 210],
                ['nama' => 'Perkiraan afkir', 'jenis' => 'afkir', 'hari_ke' => 560],
            ],
        ],
        'lele-pendederan' => [
            'nama' => 'Lele Pendederan',
            'rekap_default' => 'siklus_selesai',
            'kategoris' => [
                ['nama' => 'Benih', 'kode_sistem' => 'bibit', 'satuan_default' => 'ekor'],
                ['nama' => 'Pakan', 'satuan_default' => 'kg'],
                ['nama' => 'Obat/vitamin'],
                ['nama' => 'Gaji'],
                ['nama' => 'Listrik/air'],
                ['nama' => 'Lain-lain'],
                ['nama' => 'Kolam/peralatan', 'klasifikasi' => 'investasi'],
                ['nama' => 'Penjualan benih', 'arah' => 'pemasukan', 'satuan_default' => 'ekor'],
            ],
            'penandas' => [
                ['nama' => 'Perkiraan jual', 'jenis' => 'panen', 'hari_ke' => 60],
            ],
        ],
        'lele-pembesaran' => [
            'nama' => 'Lele Pembesaran',
            'rekap_default' => 'siklus_selesai',
            'kategoris' => [
                ['nama' => 'Benih', 'kode_sistem' => 'bibit', 'satuan_default' => 'ekor'],
                ['nama' => 'Pakan', 'satuan_default' => 'kg'],
                ['nama' => 'Obat/vitamin'],
                ['nama' => 'Gaji'],
                ['nama' => 'Listrik/air'],
                ['nama' => 'Lain-lain'],
                ['nama' => 'Kolam/peralatan', 'klasifikasi' => 'investasi'],
                ['nama' => 'Penjualan panen', 'arah' => 'pemasukan', 'satuan_default' => 'kg'],
            ],
            'penandas' => [
                ['nama' => 'Perkiraan panen', 'jenis' => 'panen', 'hari_ke' => 120],
            ],
        ],
    ],
];

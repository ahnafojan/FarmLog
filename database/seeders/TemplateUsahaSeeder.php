<?php

namespace Database\Seeders;

use App\Models\TemplateUsaha;
use Illuminate\Database\Seeder;

class TemplateUsahaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'kode' => 'ayam-petelur',
                'nama' => 'Ayam Petelur',
                'rekap_default' => 'tanggal_transaksi',
                'urutan' => 1,
            ],
            [
                'kode' => 'lele-pendederan',
                'nama' => 'Lele Pendederan',
                'rekap_default' => 'siklus_selesai',
                'urutan' => 2,
            ],
            [
                'kode' => 'lele-pembesaran',
                'nama' => 'Lele Pembesaran',
                'rekap_default' => 'siklus_selesai',
                'urutan' => 3,
            ],
        ];

        foreach ($templates as $template) {
            TemplateUsaha::query()->firstOrCreate(
                ['kode' => $template['kode']],
                [
                    'nama' => $template['nama'],
                    'satuan_populasi' => 'ekor',
                    'rekap_default' => $template['rekap_default'],
                    'aktif' => true,
                    'urutan' => $template['urutan'],
                ],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\TemplateKategori;
use App\Models\TemplateUsaha;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemplateKategoriSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(TemplateUsahaSeeder::class);

        $templateCategories = [
            'ayam-petelur' => [
                ['nama' => 'Ayam/DOC', 'kode_sistem' => 'bibit', 'satuan_default' => 'ekor'],
                ['nama' => 'Pakan', 'satuan_default' => 'kg'],
                ['nama' => 'Vaksin/vitamin/obat'],
                ['nama' => 'Gaji', 'klasifikasi' => 'operasional'],
                ['nama' => 'Listrik/air', 'klasifikasi' => 'operasional'],
                ['nama' => 'Lain-lain', 'klasifikasi' => 'operasional'],
                ['nama' => 'Kandang/peralatan', 'klasifikasi' => 'investasi'],
                ['nama' => 'Penjualan telur', 'arah' => 'pemasukan', 'satuan_default' => 'kg'],
                ['nama' => 'Penjualan ayam afkir', 'arah' => 'pemasukan', 'satuan_default' => 'ekor'],
            ],
            'lele-pendederan' => [
                ['nama' => 'Benih', 'kode_sistem' => 'bibit', 'satuan_default' => 'ekor'],
                ['nama' => 'Pakan', 'satuan_default' => 'kg'],
                ['nama' => 'Obat/vitamin'],
                ['nama' => 'Gaji', 'klasifikasi' => 'operasional'],
                ['nama' => 'Listrik/air', 'klasifikasi' => 'operasional'],
                ['nama' => 'Lain-lain', 'klasifikasi' => 'operasional'],
                ['nama' => 'Kolam/peralatan', 'klasifikasi' => 'investasi'],
                ['nama' => 'Penjualan benih', 'arah' => 'pemasukan', 'satuan_default' => 'ekor'],
            ],
            'lele-pembesaran' => [
                ['nama' => 'Benih', 'kode_sistem' => 'bibit', 'satuan_default' => 'ekor'],
                ['nama' => 'Pakan', 'satuan_default' => 'kg'],
                ['nama' => 'Obat/vitamin'],
                ['nama' => 'Gaji', 'klasifikasi' => 'operasional'],
                ['nama' => 'Listrik/air', 'klasifikasi' => 'operasional'],
                ['nama' => 'Lain-lain', 'klasifikasi' => 'operasional'],
                ['nama' => 'Kolam/peralatan', 'klasifikasi' => 'investasi'],
                ['nama' => 'Penjualan panen', 'arah' => 'pemasukan', 'satuan_default' => 'kg'],
            ],
        ];

        DB::transaction(function () use ($templateCategories): void {
            foreach ($templateCategories as $templateCode => $categories) {
                $template = TemplateUsaha::query()->where('kode', $templateCode)->firstOrFail();

                foreach ($categories as $index => $category) {
                    $direction = $category['arah'] ?? 'pengeluaran';
                    $systemCode = $category['kode_sistem'] ?? null;
                    $identity = ['template_usahas_id' => $template->id];

                    if ($systemCode !== null) {
                        $identity['kode_sistem'] = $systemCode;
                    } else {
                        $identity['arah'] = $direction;
                        $identity['nama'] = $category['nama'];
                    }

                    TemplateKategori::query()->firstOrCreate($identity, [
                        'nama' => $category['nama'],
                        'arah' => $direction,
                        'klasifikasi' => $direction === 'pemasukan' ? null : ($category['klasifikasi'] ?? 'operasional'),
                        'satuan_default' => $category['satuan_default'] ?? null,
                        'pakai_kuantitas' => isset($category['satuan_default']),
                        'kode_sistem' => $systemCode,
                        'urutan' => $index + 1,
                    ]);
                }
            }
        });
    }
}

<?php

namespace Database\Seeders;

use App\Models\TemplatePenanda;
use App\Models\TemplateUsaha;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TemplatePenandaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(TemplateUsahaSeeder::class);

        /**
         * Hari ke adalah umur sejak menetas, bukan durasi sejak masuk siklus.
         * Nilai awal ini perlu divalidasi dengan peternak sebelum digunakan.
         */
        $templateMilestones = [
            'ayam-petelur' => [
                ['nama' => 'Mulai bertelur', 'jenis' => 'mulai_produksi', 'hari_ke' => 126],
                ['nama' => 'Puncak produksi', 'jenis' => 'puncak', 'hari_ke' => 210],
                ['nama' => 'Perkiraan afkir', 'jenis' => 'afkir', 'hari_ke' => 560],
            ],
            'lele-pendederan' => [
                ['nama' => 'Perkiraan jual', 'jenis' => 'panen', 'hari_ke' => 60],
            ],
            'lele-pembesaran' => [
                ['nama' => 'Perkiraan panen', 'jenis' => 'panen', 'hari_ke' => 120],
            ],
        ];

        DB::transaction(function () use ($templateMilestones): void {
            foreach ($templateMilestones as $templateCode => $milestones) {
                $template = TemplateUsaha::query()->where('kode', $templateCode)->firstOrFail();

                foreach ($milestones as $index => $milestone) {
                    TemplatePenanda::query()->firstOrCreate(
                        [
                            'template_usahas_id' => $template->id,
                            'jenis' => $milestone['jenis'],
                        ],
                        [
                            'nama' => $milestone['nama'],
                            'hari_ke' => $milestone['hari_ke'],
                            'pengingat_hari_sebelum' => null,
                            'urutan' => $index + 1,
                        ],
                    );
                }
            }
        });
    }
}

<?php

namespace Database\Seeders;

use App\Actions\CreateSiklus;
use App\Actions\CreateUsaha;
use App\Models\Usaha;
use App\Models\User;
use Database\Factories\TransaksiFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PerformanceSeeder extends Seeder
{
    private const USER_EMAIL = 'darul@gmail.com';

    private const NAMA_USAHA = 'Pariwangi Farm';

    private const JUMLAH_SIKLUS = 10;

    private const TRANSAKSI_PER_SIKLUS = 1_000;

    private const BATCH_SIZE = 500;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'Seeder performa hanya boleh dijalankan di local atau testing.'
            );
        }

        $user = User::query()
            ->where('email', self::USER_EMAIL)
            ->firstOrFail();

        $sudahAda = Usaha::withTrashed()
            ->where('user_id', $user->id)
            ->where('nama', self::NAMA_USAHA)
            ->exists();

        if ($sudahAda) {
            throw new RuntimeException(
                'Dataset dummy sudah ada untuk user ini.'
            );
        }

        DB::transaction(function () use ($user): void {
            $usaha = app(CreateUsaha::class)->handle($user, [
                'nama' => self::NAMA_USAHA,
                'jenis_usaha' => 'ayam-petelur',
            ]);

            $kategoris = $usaha->kategoris()
                ->where('aktif', true)
                ->get()
                ->reject(
                    fn ($kategori): bool => $kategori->kode_sistem === 'bibit'
                )
                ->values();

            $timestamp = now()->toDateTimeString();

            for ($nomor = 1; $nomor <= self::JUMLAH_SIKLUS; $nomor++) {
                $selesai = $nomor <= 5;

                $mulai = today()->subDays(
                    $selesai ? 180 + ($nomor * 10) : $nomor * 5
                );

                $akhir = $selesai
                    ? $mulai->copy()->addDays(120)
                    : today();

                $siklus = app(CreateSiklus::class)->handle(
                    $user,
                    $usaha,
                    [
                        'nama' => "Siklus dummy {$nomor}",
                        'tanggal_mulai' => $mulai->toDateString(),
                        'umur_masuk_hari' => 0,
                        'populasi_awal' => 1_000,
                        'harga_bibit' => 300,
                        'catatan' => '[DUMMY-PERFORMA-v1]',
                    ],
                );

                $rows = [];

                for (
                    $nomorTransaksi = 1;
                    $nomorTransaksi <= self::TRANSAKSI_PER_SIKLUS;
                    $nomorTransaksi++
                ) {
                    $kategori = $kategoris->random();

                    $rows[] = TransaksiFactory::new()
                        ->untukKategori($kategori)
                        ->raw([
                            'sikluses_id' => $siklus->id,
                            'tanggal' => fake()->dateTimeBetween(
                                $mulai->toDateString(),
                                $akhir->toDateString(),
                            )->format('Y-m-d'),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);

                    if (count($rows) >= self::BATCH_SIZE) {
                        DB::table('transaksis')->insert($rows);
                        $rows = [];
                    }
                }

                if ($rows !== []) {
                    DB::table('transaksis')->insert($rows);
                }

                if ($selesai) {
                    $siklus->forceFill([
                        'status' => 'selesai',
                        'tanggal_selesai' => $akhir->toDateString(),
                    ])->save();
                }
            }
        });

        $this->command?->info(
            'Selesai: 1 usaha, 10 siklus, dan 10.010 transaksi dummy.'
        );
    }
}

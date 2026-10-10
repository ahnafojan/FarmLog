<?php

namespace Database\Factories;

use App\Models\Kategori;
use App\Models\Transaksi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaksi>
 */
class TransaksiFactory extends Factory
{
    protected $model = Transaksi::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tanggal' => today()->toDateString(),
            'sikluses_id' => null,
            'qty' => null,
            'satuan' => null,
            'harga_satuan' => null,
            'total' => fake()->numberBetween(10_000, 500_000),
            'pembeli' => null,
            'catatan' => '[DUMMY-PERFORMA-v1]',
        ];
    }

    public function untukKategori(Kategori $kategori): static
    {
        return $this->state(function () use ($kategori): array {
            $qty = $kategori->pakai_kuantitas
                ? fake()->numberBetween(1, 100)
                : null;

            $harga = $qty !== null
                ? fake()->numberBetween(1_000, 50_000)
                : null;

            return [
                'usahas_id' => $kategori->usahas_id,
                'kategoris_id' => $kategori->id,
                'arah' => $kategori->arah,
                'qty' => $qty,
                'satuan' => $qty !== null
                    ? $kategori->satuan_default
                    : null,
                'harga_satuan' => $harga,
                'total' => $qty !== null
                    ? $qty * $harga
                    : fake()->numberBetween(10_000, 500_000),
                'pembeli' => $kategori->arah === 'pemasukan'
                    ? fake()->name()
                    : null,
            ];
        });
    }
}

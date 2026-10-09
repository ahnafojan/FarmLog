<?php

namespace App\Actions;

use App\Models\Siklus;
use App\Models\Usaha;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateSiklus
{
    /**
     * @param  array{nama: string, tanggal_mulai: string, umur_masuk_hari: int|string, populasi_awal: int|string, harga_bibit: int|string, catatan?: string|null}  $data
     */
    public function handle(User $user, Usaha $usaha, array $data): Siklus
    {
        Gate::forUser($user)->authorize('update', $usaha);
        $data = Validator::make($data, [
            'nama' => ['required', 'string', 'max:150'],
            'tanggal_mulai' => ['required', 'date'],
            'umur_masuk_hari' => ['required', 'integer', 'min:0', 'max:36500'],
            'populasi_awal' => ['required', 'integer', 'min:1', 'max:100000000'],
            'harga_bibit' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($usaha, $data): Siklus {
            $category = $usaha->kategoris()->where('kode_sistem', 'bibit')
                ->where('arah', 'pengeluaran')->where('klasifikasi', 'operasional')->where('aktif', true)->first();

            if (! $category) {
                throw ValidationException::withMessages(['harga_bibit' => 'Kategori bibit aktif belum tersedia pada usaha ini.']);
            }

            $siklus = $usaha->sikluses()->create(collect($data)->except('harga_bibit')->all());

            foreach (config('usaha.jenis.'.$usaha->jenis_usaha.'.penandas', []) as $milestone) {
                $days = $milestone['hari_ke'] - $siklus->umur_masuk_hari;

                if ($days < 0) {
                    continue;
                }

                $copy = $siklus->penandas()->make();
                $copy->forceFill([
                    'nama' => $milestone['nama'],
                    'jenis' => $milestone['jenis'],
                    'tanggal' => $siklus->tanggal_mulai->copy()->addDays($days),
                    'pengingat_hari_sebelum' => $milestone['pengingat_hari_sebelum'] ?? null,
                ])->save();
            }

            $transaksi = $usaha->transaksis()->make([
                'arah' => 'pengeluaran',
                'tanggal' => $siklus->tanggal_mulai,
                'qty' => $siklus->populasi_awal,
                'satuan' => 'ekor',
                'harga_satuan' => $data['harga_bibit'],
                'total' => $siklus->populasi_awal * $data['harga_bibit'],
            ]);
            $transaksi->kategori()->associate($category);
            $transaksi->siklus()->associate($siklus);
            $transaksi->save();

            return $siklus;
        });
    }
}

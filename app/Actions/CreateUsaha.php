<?php

namespace App\Actions;

use App\Models\Usaha;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateUsaha
{
    /**
     * @param  array{nama: string, jenis_usaha: string}  $data
     */
    public function handle(User $user, array $data): Usaha
    {
        $data = Validator::make($data, [
            'nama' => ['required', 'string', 'max:150', Rule::unique('usahas', 'nama')->where('user_id', $user->id)->whereNull('deleted_at')],
            'jenis_usaha' => ['required', 'string', Rule::in(array_keys(config('usaha.jenis')))],
        ])->validate();

        return DB::transaction(function () use ($user, $data): Usaha {
            $defaults = config('usaha.jenis.'.$data['jenis_usaha']);
            $usaha = new Usaha(['nama' => $data['nama'], 'rekap_dasar' => $defaults['rekap_default']]);
            $usaha->user()->associate($user);
            $usaha->jenis_usaha = $data['jenis_usaha'];
            $usaha->save();

            foreach ($defaults['kategoris'] as $index => $category) {
                $direction = $category['arah'] ?? 'pengeluaran';
                $copy = $usaha->kategoris()->make([
                    'nama' => $category['nama'],
                    'arah' => $direction,
                    'klasifikasi' => $direction === 'pemasukan' ? null : ($category['klasifikasi'] ?? 'operasional'),
                    'satuan_default' => $category['satuan_default'] ?? null,
                    'pakai_kuantitas' => isset($category['satuan_default']),
                    'urutan' => $index + 1,
                ]);
                $copy->kode_sistem = $category['kode_sistem'] ?? null;
                $copy->save();
            }

            return $usaha;
        });
    }
}

<?php

namespace App\Actions;

use App\Models\Transaksi;
use App\Models\Usaha;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveTransaksi
{
    /**
     * @param  array{arah: string, tanggal: string, kategoris_id: int|string, sikluses_id?: int|string|null, qty?: numeric-string|float|null, satuan?: string|null, harga_satuan?: int|string|null, total: int|string, pembeli?: string|null, catatan?: string|null}  $data
     */
    public function handle(User $user, Usaha $usaha, array $data, ?Transaksi $record = null): Transaksi
    {
        Gate::forUser($user)->authorize('update', $usaha);

        if ($record !== null) {
            Gate::forUser($user)->authorize('update', $record);
            abort_unless((int) $record->usahas_id === (int) $usaha->id, 404);
        }

        return DB::transaction(function () use ($usaha, $data, $record): Transaksi {
            $data = Validator::make($data, [
                'arah' => ['required', Rule::in(['pengeluaran', 'pemasukan'])],
                'tanggal' => ['required', 'date'],
                'kategoris_id' => ['required', 'integer'],
                'sikluses_id' => ['nullable', 'integer', 'required_if:arah,pemasukan'],
                'qty' => ['nullable', 'numeric', 'gt:0', 'max:999999999.999', 'decimal:0,3'],
                'satuan' => ['nullable', 'string', 'max:20'],
                'harga_satuan' => ['nullable', 'integer', 'min:0', 'max:1000000000000'],
                'total' => ['required', 'integer', 'min:0', 'max:1000000000000000'],
                'pembeli' => ['nullable', 'string', 'max:150'],
                'catatan' => ['nullable', 'string', 'max:5000'],
            ])->validate();

            $category = $usaha->kategoris()->whereKey($data['kategoris_id'])
                ->where('arah', $data['arah'])->first();

            if (! $category || (! $category->aktif && (int) $record?->kategoris_id !== (int) $category->id)) {
                throw ValidationException::withMessages(['kategoris_id' => 'Pilih kategori aktif dari usaha ini.']);
            }

            $siklus = null;

            if (! empty($data['sikluses_id'])) {
                $siklus = $usaha->sikluses()->whereKey($data['sikluses_id'])->lockForUpdate()->first();

                if (! $siklus || ($siklus->status !== 'berjalan' && (int) $record?->sikluses_id !== (int) $siklus->id)) {
                    throw ValidationException::withMessages(['sikluses_id' => 'Pilih siklus berjalan dari usaha ini.']);
                }
            }

            if ($data['arah'] === 'pemasukan' || $category->pakai_kuantitas) {
                Validator::make($data, [
                    'qty' => ['required'],
                    'satuan' => ['required'],
                    'harga_satuan' => ['required'],
                ])->validate();
            } else {
                $data['qty'] = $data['satuan'] = $data['harga_satuan'] = null;
            }

            if ($data['arah'] === 'pengeluaran') {
                $data['pembeli'] = null;
            }

            $record ??= new Transaksi;
            $record->fill(collect($data)->except(['kategoris_id', 'sikluses_id'])->all());
            $record->usaha()->associate($usaha);
            $record->kategori()->associate($category);
            $record->siklus()->associate($siklus);
            $record->save();

            return $record;
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kategori extends Model
{
    protected $fillable = ['nama', 'arah', 'klasifikasi', 'satuan_default', 'pakai_kuantitas', 'aktif', 'urutan'];

    protected function casts(): array
    {
        return ['pakai_kuantitas' => 'boolean', 'aktif' => 'boolean'];
    }

    public function usaha(): BelongsTo
    {
        return $this->belongsTo(Usaha::class, 'usahas_id');
    }

    public function transaksis(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'kategoris_id');
    }
}

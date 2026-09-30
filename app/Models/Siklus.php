<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Siklus extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama', 'tanggal_mulai', 'umur_masuk_hari', 'populasi_awal', 'catatan'];

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'date', 'tanggal_selesai' => 'date', 'umur_masuk_hari' => 'integer', 'populasi_awal' => 'integer'];
    }

    public function usaha(): BelongsTo
    {
        return $this->belongsTo(Usaha::class, 'usahas_id');
    }

    public function penandas(): HasMany
    {
        return $this->hasMany(SiklusPenanda::class, 'sikluses_id');
    }

    public function transaksis(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'sikluses_id');
    }
}

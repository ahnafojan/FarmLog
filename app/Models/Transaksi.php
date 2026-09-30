<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaksi extends Model
{
    use SoftDeletes;

    protected $fillable = ['arah', 'tanggal', 'qty', 'satuan', 'harga_satuan', 'total', 'pembeli', 'catatan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'qty' => 'decimal:3', 'harga_satuan' => 'integer', 'total' => 'integer'];
    }

    public function usaha(): BelongsTo
    {
        return $this->belongsTo(Usaha::class, 'usahas_id');
    }

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'kategoris_id');
    }

    public function siklus(): BelongsTo
    {
        return $this->belongsTo(Siklus::class, 'sikluses_id');
    }
}

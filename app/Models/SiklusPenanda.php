<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiklusPenanda extends Model
{
    protected $fillable = ['tanggal'];

    protected $table = 'sikluses_penandas';

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'selesai_at' => 'datetime'];
    }

    public function siklus(): BelongsTo
    {
        return $this->belongsTo(Siklus::class, 'sikluses_id');
    }
}

<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Usaha extends Model implements HasName
{
    use SoftDeletes;

    protected $fillable = ['nama', 'rekap_dasar', 'catatan'];

    public function getFilamentName(): string
    {
        return $this->nama;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TemplateUsaha::class, 'template_usahas_id');
    }

    public function kategoris(): HasMany
    {
        return $this->hasMany(Kategori::class, 'usahas_id');
    }

    public function sikluses(): HasMany
    {
        return $this->hasMany(Siklus::class, 'usahas_id');
    }

    public function transaksis(): HasMany
    {
        return $this->hasMany(Transaksi::class, 'usahas_id');
    }
}

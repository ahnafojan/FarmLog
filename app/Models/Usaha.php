<?php

namespace App\Models;

use Filament\Models\Contracts\HasName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Usaha extends Model implements HasName
{
    use SoftDeletes;

    protected $fillable = ['nama', 'rekap_dasar', 'catatan'];

    protected static function booted(): void
    {
        static::creating(function (Usaha $usaha): void {
            $baseSlug = Str::slug($usaha->nama) ?: 'usaha';
            $slug = $baseSlug;

            for ($suffix = 2; in_array($slug, ['login', 'logout', 'new', 'password-reset', 'profile', 'register'], true) || static::withTrashed()->where('slug', $slug)->exists(); $suffix++) {
                $slug = $baseSlug.'-'.$suffix;
            }

            $usaha->slug = $slug;
        });
    }

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

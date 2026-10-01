<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReviewQr extends Model
{
    protected $fillable = ['name', 'place_id', 'place_name', 'place_address', 'maps_url', 'review_url', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $qr): void {
            do {
                $id = Str::upper(Str::random(8));
            } while (self::where('public_id', $id)->exists());
            $qr->public_id = $id;
        });
    }

    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

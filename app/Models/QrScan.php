<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrScan extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $fillable = ['user_agent', 'ip_hash', 'scanned_at'];

    protected function casts(): array
    {
        return ['scanned_at' => 'datetime'];
    }

    public function reviewQr(): BelongsTo
    {
        return $this->belongsTo(ReviewQr::class);
    }
}

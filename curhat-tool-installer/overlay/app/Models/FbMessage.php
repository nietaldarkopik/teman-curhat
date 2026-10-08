<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FbMessage extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['created_time' => 'datetime'];

    public function curhat(): BelongsTo
    {
        return $this->belongsTo(Curhat::class);
    }
}

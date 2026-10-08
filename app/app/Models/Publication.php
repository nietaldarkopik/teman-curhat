<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    public const STATUSES = [
        'menunggu' => 'Menunggu upload',
        'mengunggah' => 'Sedang mengunggah',
        'terjadwal' => 'Terjadwal tayang',
        'tayang' => 'Sudah tayang',
        'gagal' => 'Gagal',
    ];

    public const PLATFORMS = ['youtube' => 'YouTube', 'facebook' => 'Facebook'];

    protected $guarded = ['id'];

    protected $casts = [
        'publish_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function platformLabel(): string
    {
        return self::PLATFORMS[$this->platform] ?? $this->platform;
    }
}

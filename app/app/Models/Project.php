<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUSES = [
        'draf' => 'Draf',
        'siap_rekam' => 'Siap direkam',
        'sudah_rekam' => 'Sudah direkam',
        'dijadwalkan' => 'Dijadwalkan',
        'selesai' => 'Selesai',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'planned_date' => 'date',
        'title_options' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class)->orderBy('position');
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class)->orderBy('publish_at');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}

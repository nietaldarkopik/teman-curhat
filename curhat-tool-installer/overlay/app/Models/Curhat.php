<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curhat extends Model
{
    public const STATUSES = [
        'baru' => 'Baru',
        'dibaca' => 'Sudah dibaca',
        'dipilih' => 'Masuk project',
        'dibacakan' => 'Sudah dibacakan',
        'arsip' => 'Diarsip',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'is_sensitive' => 'boolean',
        'is_starred' => 'boolean',
        'received_at' => 'datetime',
        'last_message_at' => 'datetime',
        'read_at' => 'datetime',
        'used_at' => 'datetime',
        'tts_generated_at' => 'datetime',
    ];

    public function ttsUrl(): ?string
    {
        return $this->tts_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->tts_path) : null;
    }

    public function fbMessages(): HasMany
    {
        return $this->hasMany(FbMessage::class);
    }

    public function projectItems(): HasMany
    {
        return $this->hasMany(ProjectItem::class);
    }

    public function readableText(): string
    {
        return $this->anon_text ?: $this->original_text;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Susun ulang teks curhat dari potongan-potongan pesan DM. */
    public function rebuildText(): void
    {
        $msgs = $this->fbMessages()->orderBy('created_time')->get();
        if ($msgs->isEmpty()) {
            return;
        }
        $text = $msgs->pluck('message')->implode("\n");
        $changed = $this->original_text !== '' && $this->original_text !== $text;

        $this->original_text = $text;
        $this->char_count = mb_strlen($text);
        $this->last_message_at = $msgs->last()->created_time;
        $this->received_at = $this->received_at ?? $msgs->first()->created_time;

        if ($changed && $this->anon_text && $this->status !== 'dipilih') {
            // Ada pesan susulan: versi anonim lama tidak lagi lengkap.
            $this->anon_text = null;
            $this->summary = null;
            $this->status = 'baru';
        }
        $this->save();
    }
}

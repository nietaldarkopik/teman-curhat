<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectItem extends Model
{
    protected $guarded = ['id'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function curhat(): BelongsTo
    {
        return $this->belongsTo(Curhat::class);
    }

    /**
     * Pecah read_text menjadi gelembung chat (dipakai layar dan teleprompter agar sama).
     *
     * @return list<string>
     */
    public function bubbles(): array
    {
        $out = [];
        foreach (preg_split('/\n+/', trim((string) $this->read_text)) ?: [] as $para) {
            $buf = '';
            foreach (preg_split('/(?<=[.!?])\s+/', $para) ?: [$para] as $s) {
                if (mb_strlen($buf.' '.$s) > 220 && $buf !== '') {
                    $out[] = trim($buf);
                    $buf = '';
                }
                $buf .= ' '.$s;
            }
            if (trim($buf) !== '') {
                $out[] = trim($buf);
            }
        }

        return $out;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Curhat;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/** Mencatat izin pengirim sebelum curhat boleh dibacakan di video. */
class ConsentController extends Controller
{
    public function index(Request $r)
    {
        $status = $r->query('status');
        if (! array_key_exists((string) $status, Curhat::CONSENTS)) {
            $status = null;
        }

        $q = Curhat::query()->where('status', '!=', 'arsip');
        if ($status) {
            $q->where('consent_status', $status);
        }

        $counts = Curhat::query()->where('status', '!=', 'arsip')
            ->selectRaw('consent_status, COUNT(*) AS n')
            ->groupBy('consent_status')
            ->pluck('n', 'consent_status');

        return view('consent.index', [
            'curhats' => $q->orderByDesc('received_at')->orderByDesc('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => $counts,
            'template' => self::requestTemplate(),
        ]);
    }

    public function update(Request $r, Curhat $curhat)
    {
        $data = $r->validate([
            'consent_status' => ['required', Rule::in(array_keys(Curhat::CONSENTS))],
            'consent_note' => 'nullable|string|max:1000',
        ]);

        $old = $curhat->consent_status;
        $curhat->consent_status = $data['consent_status'];
        $curhat->consent_note = $data['consent_note'] ?? null;
        $curhat->consent_at = now();
        $curhat->save();

        // Hanya ID dan status yang dicatat, bukan isi curhat.
        Log::info('consent.changed', ['curhat_id' => $curhat->id, 'from' => $old, 'to' => $curhat->consent_status]);

        return back()->with('ok', 'Status izin disimpan.');
    }

    public static function requestTemplate(): string
    {
        $ch = Setting::val('channel_name') ?: 'Teman Curhat';

        return "Halo, makasih ya udah percaya cerita ke {$ch}.\n\n"
            ."Boleh nggak ceritamu dibacakan di YouTube dan Facebook {$ch}? "
            ."Nama dan semua hal yang bisa bikin kamu dikenali bakal disamarkan.\n\n"
            ."Kalau nggak boleh juga nggak apa-apa, ceritamu tetap aman di sini. "
            .'Cukup balas BOLEH atau JANGAN ya.';
    }
}

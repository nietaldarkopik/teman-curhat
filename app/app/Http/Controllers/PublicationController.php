<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use Illuminate\Support\Facades\Artisan;

class PublicationController extends Controller
{
    public function index()
    {
        return view('uploads.index', [
            'pubs' => Publication::with('project')->orderByDesc('publish_at')->paginate(30),
        ]);
    }

    public function retry(Publication $publication)
    {
        if ($publication->status === 'gagal') {
            $publication->update(['status' => 'menunggu', 'error' => null]);
        }

        return back()->with('ok', 'Dimasukkan lagi ke antrean upload.');
    }

    public function destroy(Publication $publication)
    {
        if ($publication->status === 'mengunggah') {
            return back()->with('err', 'Sedang mengunggah, tunggu sampai selesai.');
        }
        $publication->delete();

        return back()->with('ok', 'Jadwal dihapus dari daftar. Video yang sudah terunggah tetap ada di platformnya.');
    }

    /** Untuk saat scheduler Windows belum dipasang. */
    public function runNow()
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
        Artisan::call('curhat:process-uploads', ['--limit' => 5]);

        return back()->with('ok', 'Antrean diproses. '.trim(Artisan::output()));
    }
}

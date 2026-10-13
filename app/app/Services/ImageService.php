<?php

namespace App\Services;

use App\Support\Net;

/**
 * Ilustrasi latar Short lewat Pollinations (gratis, tanpa API key).
 *
 * Yang dikirim ke layanan ini hanya deskripsi adegan berbahasa Inggris yang dibuat AI
 * dari teks yang SUDAH dianonimkan. Gagal/lambat/bukan gambar -> null, dan video
 * tetap dirender tanpa ilustrasi.
 */
class ImageService
{
    private const STYLE = 'Soft flat illustration, painterly, deep navy and warm amber color palette, calm melancholic cinematic mood, '
        .'no text, no letters, no logo, no watermark, no close-up of faces. Scene: ';

    /** @return string|null path file jpg sementara; hapus sendiri setelah dipakai. */
    public function fetch(string $scene, int $seed): ?string
    {
        $scene = trim($scene);
        if ($scene === '') {
            return null;
        }
        $url = 'https://image.pollinations.ai/prompt/'.rawurlencode(self::STYLE.$scene)
            .'?'.http_build_query(['width' => 1024, 'height' => 1024, 'model' => 'flux', 'nologo' => 'true', 'seed' => $seed]);

        // Layanan gratis kadang antre/sibuk: coba 2 kali.
        for ($try = 0; $try < 2; $try++) {
            try {
                $res = Net::http()->timeout(90)->get($url);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            $type = (string) $res->header('Content-Type');
            if ($res->successful() && str_starts_with($type, 'image/') && strlen($res->body()) > 5000) {
                $file = tempnam(sys_get_temp_dir(), 'curhat-ill-');
                $jpg = $file.'.jpg';
                rename($file, $jpg);
                file_put_contents($jpg, $res->body());

                return $jpg;
            }
        }

        return null;
    }
}

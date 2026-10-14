<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Publication;
use App\Models\Setting;
use App\Support\Net;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class YouTubeService
{
    private const SCOPES = 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly https://www.googleapis.com/auth/youtube.force-ssl';

    public function appConfigured(): bool
    {
        return Setting::val('google_client_id') && Setting::val('google_client_secret');
    }

    public function connected(): bool
    {
        return (bool) Setting::val('yt_refresh_token');
    }

    public function redirectUri(): string
    {
        return route('oauth.google.callback');
    }

    public function authUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => Setting::val('google_client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connectWithCode(string $code): void
    {
        $res = Net::http()->asForm()->timeout(60)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => Setting::val('google_client_id'),
            'client_secret' => Setting::val('google_client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ]);
        if ($res->failed()) {
            throw new RuntimeException('Google: '.($res->json('error_description') ?: $res->body()));
        }
        if (! $res->json('refresh_token')) {
            throw new RuntimeException('Google tidak mengirim refresh token. Cabut akses aplikasi ini di myaccount.google.com/permissions, lalu hubungkan ulang.');
        }
        Setting::put('yt_refresh_token', $res->json('refresh_token'));
        $this->storeAccess($res->json('access_token'), (int) $res->json('expires_in'));

        $ch = Net::http()->withToken($this->accessToken())->timeout(60)
            ->get('https://www.googleapis.com/youtube/v3/channels', ['part' => 'snippet', 'mine' => 'true']);
        Setting::put('yt_channel_title', $ch->json('items.0.snippet.title') ?: '(channel tidak terbaca)');
    }

    /** Teks komentar dari channel: pertanyaan ke penonton + ajakan subscribe. */
    public function engagementComment(Project $project): string
    {
        $qs = $project->items->pluck('audience_question')->filter()->values();
        if ($qs->count() === 1) {
            $body = $qs[0];
        } elseif ($qs->count() > 1) {
            $body = "Menurut kamu bagaimana?\n".$qs->map(fn ($q, $i) => ($i + 1).'. '.$q)->implode("\n");
        } else {
            $body = 'Menurut kamu bagaimana? Tulis pendapatmu di komentar ya.';
        }

        return $body."\n\nCurhatan baru tayang setiap hari. Subscribe dan nyalakan lonceng supaya tidak ketinggalan.";
    }

    /** Posting komentar ke video yang sudah tayang. Pin-nya tetap manual: API YouTube tidak menyediakannya. */
    public function postComment(string $videoId, string $text): void
    {
        $res = Net::http()->withToken($this->accessToken())->timeout(60)
            ->post('https://www.googleapis.com/youtube/v3/commentThreads?part=snippet', [
                'snippet' => [
                    'videoId' => $videoId,
                    'topLevelComment' => ['snippet' => ['textOriginal' => mb_substr($text, 0, 9000)]],
                ],
            ]);
        if (! $res->successful()) {
            throw new RuntimeException('YouTube komentar: '.($res->json('error.message') ?: ('HTTP '.$res->status())));
        }
    }

    private function storeAccess(string $token, int $expiresIn): void
    {
        Setting::put('yt_access_token', $token);
        Setting::put('yt_token_expires', (string) (time() + max(60, $expiresIn) - 120));
    }

    public function accessToken(): string
    {
        $token = Setting::val('yt_access_token');
        if ($token && (int) Setting::val('yt_token_expires', 0) > time()) {
            return $token;
        }
        $res = Net::http()->asForm()->timeout(60)->post('https://oauth2.googleapis.com/token', [
            'client_id' => Setting::val('google_client_id'),
            'client_secret' => Setting::val('google_client_secret'),
            'refresh_token' => Setting::val('yt_refresh_token'),
            'grant_type' => 'refresh_token',
        ]);
        if ($res->failed()) {
            throw new RuntimeException('Google: gagal memperbarui token ('.($res->json('error_description') ?: $res->status()).'). Hubungkan ulang YouTube di Pengaturan.');
        }
        $this->storeAccess($res->json('access_token'), (int) $res->json('expires_in'));

        return $res->json('access_token');
    }

    private function tags(?string $csv): array
    {
        $out = [];
        $total = 0;
        foreach (array_filter(array_map('trim', explode(',', (string) $csv))) as $tag) {
            $len = mb_strlen($tag) + (str_contains($tag, ' ') ? 2 : 0) + 1;
            if ($total + $len > 450) {
                break;
            }
            $out[] = $tag;
            $total += $len;
        }

        return $out;
    }

    private function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'webm' => 'video/webm',
            'avi' => 'video/x-msvideo',
            default => 'video/mp4',
        };
    }

    /** Gagal pasang thumbnail (mis. channel belum terverifikasi) tidak membatalkan upload. */
    private function setThumbnail(string $videoId, ?string $rel): void
    {
        $file = $rel ? Storage::disk('local')->path($rel) : null;
        if (! $file || ! is_file($file)) {
            return;
        }
        try {
            Net::http()->withToken($this->accessToken())->timeout(120)
                ->withBody(file_get_contents($file), str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg')
                ->post('https://www.googleapis.com/upload/youtube/v3/thumbnails/set?videoId='.$videoId);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Upload resumable. Jadwal tayang ditangani YouTube (publishAt), jadi komputer tidak perlu menyala saat jam tayang. */
    public function upload(Publication $pub): array
    {
        if (! $this->connected()) {
            throw new RuntimeException('YouTube belum terhubung.');
        }
        $project = $pub->project;
        $path = $project->video_path;
        if (! $path || ! is_file($path)) {
            throw new RuntimeException('File video tidak ditemukan: '.$path);
        }
        $size = filesize($path);
        $mime = $this->mime($path);

        $scheduled = $pub->privacy === 'public' && $pub->publish_at && $pub->publish_at->gt(now()->addMinutes(5));
        $status = [
            'privacyStatus' => $scheduled ? 'private' : $pub->privacy,
            'selfDeclaredMadeForKids' => false,
        ];
        if ($scheduled) {
            $status['publishAt'] = $pub->publish_at->copy()->utc()->format('Y-m-d\TH:i:s\Z');
        }

        $init = Net::http()
            ->withToken($this->accessToken())
            ->withOptions(['allow_redirects' => false])
            ->withHeaders([
                'X-Upload-Content-Length' => (string) $size,
                'X-Upload-Content-Type' => $mime,
            ])
            ->timeout(90)
            ->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', [
                'snippet' => [
                    'title' => mb_substr((string) $project->yt_title, 0, 100),
                    'description' => mb_strcut((string) $project->yt_description, 0, 4900, 'UTF-8'),
                    'tags' => $this->tags($project->yt_tags),
                    'categoryId' => '22',
                    'defaultLanguage' => 'id',
                    'defaultAudioLanguage' => 'id',
                ],
                'status' => $status,
            ]);

        if (! $init->successful() || ! $init->header('Location')) {
            throw new RuntimeException('YouTube: '.($init->json('error.message') ?: ('HTTP '.$init->status())));
        }
        $location = $init->header('Location');

        $chunkSize = 16 * 1024 * 1024; // kelipatan 256 KB
        $offset = 0;
        $fh = fopen($path, 'rb');
        try {
            while ($offset < $size) {
                fseek($fh, $offset);
                $data = fread($fh, $chunkSize);
                $end = $offset + strlen($data) - 1;

                $res = Net::http()
                    ->withToken($this->accessToken())
                    ->withOptions(['allow_redirects' => false])
                    ->withHeaders(['Content-Range' => "bytes {$offset}-{$end}/{$size}"])
                    ->withBody($data, $mime)
                    ->timeout(900)
                    ->put($location);

                if (in_array($res->status(), [200, 201], true)) {
                    $id = $res->json('id');

                    $this->setThumbnail($id, $project->thumbnail_path);

                    return [$id, 'https://youtu.be/'.$id, $scheduled];
                }
                if ($res->status() === 308) {
                    $range = $res->header('Range');
                    $offset = $range ? ((int) substr($range, strrpos($range, '-') + 1)) + 1 : 0;
                    continue;
                }
                throw new RuntimeException('YouTube: '.($res->json('error.message') ?: ('HTTP '.$res->status())));
            }
        } finally {
            fclose($fh);
        }

        throw new RuntimeException('YouTube: upload selesai tanpa balasan dari server.');
    }
}

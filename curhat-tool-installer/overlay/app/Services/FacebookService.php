<?php

namespace App\Services;

use App\Models\Curhat;
use App\Models\FbMessage;
use App\Models\Publication;
use App\Models\Setting;
use App\Support\Net;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use RuntimeException;

class FacebookService
{
    public function version(): string
    {
        return Setting::val('fb_graph_version');
    }

    private function base(): string
    {
        return 'https://graph.facebook.com/'.$this->version();
    }

    public function appConfigured(): bool
    {
        return Setting::val('fb_app_id') && Setting::val('fb_app_secret');
    }

    public function connected(): bool
    {
        return Setting::val('fb_page_id') && Setting::val('fb_page_token');
    }

    public function redirectUri(): string
    {
        return route('oauth.facebook.callback');
    }

    public function authUrl(string $state): string
    {
        $params = [
            'client_id' => Setting::val('fb_app_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
        ];

        // Login Configuration (config_id) menggantikan `scope` sepenuhnya — Meta
        // menolak keduanya dikirim bersamaan. Ini juga yang membuat izin Advanced
        // Access seperti pages_messaging tidak kena "Invalid Scopes" untuk akun
        // yang bukan Admin/Developer/Tester App, karena sudah disetujui lewat
        // Login Configuration di App Dashboard alih-alih dicek per-scope.
        $configId = Setting::val('fb_login_config_id');
        if ($configId) {
            $params['config_id'] = $configId;
        } else {
            $params['scope'] = Setting::val('fb_scopes');
        }

        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query($params);
    }

    private function check(Response $res): array
    {
        $j = $res->json() ?? [];
        if ($res->failed() || isset($j['error'])) {
            throw new RuntimeException('Facebook: '.($j['error']['message'] ?? ('HTTP '.$res->status())));
        }

        return $j;
    }

    private function get(string $url, ?array $params = []): array
    {
        // null (bukan []) untuk URL "next" dari paging: Laravel mengirim array
        // kosong sebagai override querystring, yang menghapus access_token yang
        // sudah menempel di URL tersebut. null berarti "pakai apa adanya".
        return $this->check(Net::http()->timeout(90)->get($url, $params));
    }

    /** Tukar kode OAuth menjadi daftar Page beserta token Page yang tidak kedaluwarsa. */
    public function pagesFromCode(string $code): array
    {
        $short = $this->get($this->base().'/oauth/access_token', [
            'client_id' => Setting::val('fb_app_id'),
            'client_secret' => Setting::val('fb_app_secret'),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ])['access_token'];

        $long = $this->get($this->base().'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => Setting::val('fb_app_id'),
            'client_secret' => Setting::val('fb_app_secret'),
            'fb_exchange_token' => $short,
        ])['access_token'];

        $pages = $this->get($this->base().'/me/accounts', [
            'fields' => 'id,name,access_token',
            'limit' => 100,
            'access_token' => $long,
        ]);

        return $pages['data'] ?? [];
    }

    /** Tarik DM baru dari Messenger Page dan kelompokkan menjadi curhat. */
    public function sync(): array
    {
        if (! $this->connected()) {
            throw new RuntimeException('Page Facebook belum terhubung.');
        }
        $pageId = Setting::val('fb_page_id');
        $token = Setting::val('fb_page_token');
        $last = Setting::val('fb_last_sync');
        $since = $last
            ? Carbon::parse($last)->subMinutes(30)
            : now()->subDays((int) Setting::val('fb_sync_days'));
        $gap = max(1, (int) Setting::val('fb_group_hours'));

        $url = $this->base()."/{$pageId}/conversations";
        $params = ['platform' => 'messenger', 'fields' => 'id,updated_time', 'limit' => 50, 'access_token' => $token];
        $conversations = 0;
        $messages = 0;
        $rounds = 0;

        while ($url && $rounds < 40) {
            $j = $this->get($url, $params);
            $params = null;
            $rounds++;
            $stop = false;
            foreach ($j['data'] ?? [] as $conv) {
                if (Carbon::parse($conv['updated_time'])->lt($since)) {
                    $stop = true;
                    break;
                }
                $conversations++;
                $messages += $this->syncConversation($conv['id'], $pageId, $token, $gap);
            }
            if ($stop) {
                break;
            }
            $url = $j['paging']['next'] ?? null;
        }

        Setting::put('fb_last_sync', now()->toDateTimeString());

        return ['conversations' => $conversations, 'messages' => $messages];
    }

    private function syncConversation(string $convId, string $pageId, string $token, int $gapHours): int
    {
        $j = $this->get($this->base()."/{$convId}/messages", [
            'fields' => 'id,message,from,created_time',
            'limit' => 50,
            'access_token' => $token,
        ]);

        $incoming = collect($j['data'] ?? [])
            ->filter(fn ($m) => ($m['from']['id'] ?? '') !== $pageId && trim((string) ($m['message'] ?? '')) !== '')
            ->sortBy('created_time');

        $added = 0;
        foreach ($incoming as $m) {
            if (FbMessage::where('fb_message_id', $m['id'])->exists()) {
                continue;
            }
            $time = Carbon::parse($m['created_time'])->setTimezone(config('app.timezone'));

            $curhat = Curhat::where('fb_conversation_id', $convId)
                ->whereIn('status', ['baru', 'dibaca'])
                ->where('last_message_at', '>=', $time->copy()->subHours($gapHours))
                ->orderByDesc('last_message_at')
                ->first();

            if (! $curhat) {
                $curhat = Curhat::create([
                    'source' => 'facebook',
                    'fb_conversation_id' => $convId,
                    'sender_name' => $m['from']['name'] ?? null,
                    'sender_id' => $m['from']['id'] ?? null,
                    'original_text' => '',
                    'status' => 'baru',
                    'received_at' => $time,
                    'last_message_at' => $time,
                ]);
            }

            FbMessage::create([
                'curhat_id' => $curhat->id,
                'fb_message_id' => $m['id'],
                'fb_conversation_id' => $convId,
                'from_id' => $m['from']['id'] ?? null,
                'message' => trim($m['message']),
                'created_time' => $time,
            ]);
            $curhat->rebuildText();
            $added++;
        }

        return $added;
    }

    /** Upload video ke Page (resumable), dengan jadwal tayang bila diisi. */
    public function upload(Publication $pub): array
    {
        if (! $this->connected()) {
            throw new RuntimeException('Page Facebook belum terhubung.');
        }
        $project = $pub->project;
        $path = $project->video_path;
        if (! $path || ! is_file($path)) {
            throw new RuntimeException('File video tidak ditemukan: '.$path);
        }
        $pageId = Setting::val('fb_page_id');
        $token = Setting::val('fb_page_token');
        $size = filesize($path);
        $url = 'https://graph-video.facebook.com/'.$this->version()."/{$pageId}/videos";

        $start = $this->check(Net::http()->timeout(90)->asForm()->post($url, [
            'upload_phase' => 'start',
            'file_size' => $size,
            'access_token' => $token,
        ]));
        $session = $start['upload_session_id'];
        $videoId = $start['video_id'];
        $so = (int) $start['start_offset'];
        $eo = (int) $start['end_offset'];

        $fh = fopen($path, 'rb');
        try {
            while ($so < $eo) {
                fseek($fh, $so);
                $chunk = fread($fh, $eo - $so);
                $r = $this->check(Net::http()->timeout(900)
                    ->attach('video_file_chunk', $chunk, 'chunk.bin')
                    ->post($url, [
                        'upload_phase' => 'transfer',
                        'upload_session_id' => $session,
                        'start_offset' => $so,
                        'access_token' => $token,
                    ]));
                $so = (int) $r['start_offset'];
                $eo = (int) $r['end_offset'];
            }
        } finally {
            fclose($fh);
        }

        $finish = [
            'upload_phase' => 'finish',
            'upload_session_id' => $session,
            'access_token' => $token,
            'title' => mb_substr((string) ($project->fb_title ?: $project->yt_title), 0, 250),
            'description' => (string) ($project->fb_description ?: $project->yt_description),
        ];
        // Facebook butuh jeda minimal ~10 menit untuk jadwal tayang.
        $scheduled = $pub->publish_at && $pub->publish_at->gt(now()->addMinutes(12));
        if ($scheduled) {
            $finish['published'] = 'false';
            $finish['scheduled_publish_time'] = $pub->publish_at->timestamp;
        }
        $thumb = $project->thumbnail_path ? Storage::disk('local')->path($project->thumbnail_path) : null;
        $req = Net::http()->timeout(120);
        if ($thumb && is_file($thumb)) {
            $req = $req->attach('thumb', file_get_contents($thumb), basename($thumb));
        } else {
            $req = $req->asForm();
        }
        $this->check($req->post($url, $finish));

        return [$videoId, "https://www.facebook.com/{$pageId}/videos/{$videoId}", $scheduled];
    }
}

<?php

namespace App\Services;

use App\Models\Curhat;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Semua prompt AI ada di sini, supaya mudah disesuaikan.
 */
class AiWriter
{
    public function __construct(private ClaudeService $claude)
    {
    }

    private function channel(): string
    {
        return Setting::val('channel_name');
    }

    private function style(): string
    {
        return Setting::val('host_style');
    }

    /** Anonimkan + beri topik + ringkasan + cek topik sensitif. Nama pengirim tidak pernah dikirim ke AI. */
    public function analyze(Curhat $c): Curhat
    {
        $prompt = <<<P
Kamu membantu admin page Facebook "{$this->channel()}" yang membacakan curhat kiriman orang secara anonim di YouTube. Pengirim biasanya meminta namanya tidak disebut.

Tugas 1: Anonimkan curhat di bawah agar pengirim dan orang yang disebut tidak bisa dikenali.
- Ganti setiap nama orang dengan nama samaran Indonesia yang umum, konsisten untuk orang yang sama. Sebutan umum seperti "ibuku", "pacarku", "bosku" tidak perlu diganti.
- Ganti nama kota atau daerah, sekolah, kampus, kantor, perusahaan, akun media sosial, nomor telepon, alamat, dan tanggal spesifik dengan versi umum, misalnya "sebuah kota di Jawa" atau "kantor tempat aku kerja".
- Hapus salam pembuka kepada admin seperti "min, tolong jangan sebut namaku ya" bila tidak penting untuk cerita.
- Jangan mengubah alur cerita, perasaan, atau gaya bahasa pengirim. Rapikan salah ketik hanya bila mengganggu pembacaan.

Tugas 2: Beri topik singkat (1-3 kata, contoh: "rumah tangga", "pacaran", "keluarga", "pekerjaan", "pertemanan", "keuangan", "kuliah") dan ringkasan satu kalimat.

Tugas 3: Nilai apakah curhat berisi keinginan bunuh diri atau menyakiti diri, kekerasan yang masih berlangsung, kekerasan seksual, atau anak di bawah umur sebagai korban. Bila ya, sensitif = true dan jelaskan singkat.

Format JSON:
{"teks_anonim":"...","topik":"...","ringkasan":"...","sensitif":false,"catatan_sensitif":""}

Curhat:
"""
{$c->original_text}
"""
P;
        $j = $this->claude->json($prompt, 6000);

        $c->anon_text = trim((string) ($j['teks_anonim'] ?? '')) ?: $c->anon_text;
        $c->topic = Str::limit(Str::lower(trim((string) ($j['topik'] ?? ''))), 90, '');
        $c->summary = trim((string) ($j['ringkasan'] ?? ''));
        $c->is_sensitive = (bool) ($j['sensitif'] ?? false);
        $c->sensitive_note = trim((string) ($j['catatan_sensitif'] ?? '')) ?: null;
        $c->save();

        return $c;
    }

    /** Naskah untuk satu curhat: pengantar, balasan/solusi, lemparan ke penonton. */
    public function reply(string $text, ?string $hostNotes = null, ?int $number = null, ?int $total = null): array
    {
        $pos = $number ? " (curhat ke-{$number}".($total ? " dari {$total}" : '').' dalam video)' : '';
        $notes = $hostNotes ? "\nCatatan atau pendapat host yang WAJIB dijadikan dasar balasan:\n\"\"\"{$hostNotes}\"\"\"\n" : '';

        $prompt = <<<P
Kamu penulis naskah untuk host channel YouTube "{$this->channel()}". Dalam satu video, host membacakan beberapa curhat anonim dari DM page Facebook, memberi tanggapan dan solusi, lalu melempar pertanyaan ke penonton supaya mereka memberi opini dan masukan di kolom komentar.

Gaya bicara host: {$this->style()}

Tulis bagian naskah untuk curhat di bawah{$pos}:
- "pengantar": 1-2 kalimat untuk memperkenalkan curhat sebelum dibacakan, tanpa membocorkan inti cerita dan tanpa menyebut nama pengirim.
- "balasan": tanggapan dan solusi host, 150-250 kata, bahasa lisan yang natural untuk diucapkan. Empatik, praktis, tidak menghakimi, tidak mendiagnosis, tidak mengaku psikolog. Kalau masalahnya berat, sarankan mencari bantuan profesional.
- "lemparan": 1-2 kalimat pertanyaan yang spesifik ke cerita ini, ditujukan ke penonton untuk dijawab di kolom komentar.
Jangan menambah fakta di luar cerita.{$notes}

Format JSON:
{"pengantar":"...","balasan":"...","lemparan":"..."}

Curhat:
"""
{$text}
"""
P;
        $j = $this->claude->json($prompt, 3000);

        return [
            'intro' => trim((string) ($j['pengantar'] ?? '')),
            'reply' => trim((string) ($j['balasan'] ?? '')),
            'question' => trim((string) ($j['lemparan'] ?? '')),
        ];
    }

    /** Pembuka dan penutup sesi. */
    public function frame(Project $p): array
    {
        $list = $p->items->values()->map(function ($it, $i) {
            $c = $it->curhat;
            $sum = $c->summary ?: Str::limit($it->read_text ?: $c->readableText(), 200);

            return ($i + 1).'. ['.($c->topic ?: 'umum').'] '.$sum;
        })->implode("\n");

        $prompt = <<<P
Kamu penulis naskah untuk host channel YouTube "{$this->channel()}". Video ini adalah satu sesi membaca {$p->items->count()} curhat anonim dari DM page Facebook "{$this->channel()}", masing-masing diberi tanggapan dan solusi, lalu penonton diajak memberi opini di komentar.

Gaya bicara host: {$this->style()}

Daftar curhat di sesi ini:
{$list}

Tulis:
- "pembuka": sekitar 150-200 kata. Sapa penonton, perkenalkan sesi, singgung tema-tema curhat hari ini tanpa membocorkan detail, sebutkan bahwa semua cerita dibacakan secara anonim, dan ajak subscribe secara singkat dan tidak memaksa.
- "penutup": sekitar 100-150 kata. Rangkum pelajaran dari sesi ini, ajak penonton menjawab pertanyaan-pertanyaan tadi di kolom komentar (sebutkan bahwa tanggapan terbaik bisa dibacakan di video berikutnya), ajak yang ingin curhat untuk mengirim DM ke page Facebook "{$this->channel()}", lalu salam penutup.

Format JSON:
{"pembuka":"...","penutup":"..."}
P;
        $j = $this->claude->json($prompt, 3000);

        return [
            'opening' => trim((string) ($j['pembuka'] ?? '')),
            'closing' => trim((string) ($j['penutup'] ?? '')),
        ];
    }

    /** Teks pendek untuk thumbnail. */
    public function thumbTexts(Project $p): array
    {
        $items = $p->items->values()->map(fn ($it, $i) => ($i + 1).'. '.($it->curhat->topic ?: 'umum').': '.($it->curhat->summary ?: Str::limit($it->read_text, 150)))->implode("
");

        $prompt = <<<P
Kamu membuat teks thumbnail YouTube untuk channel "{$this->channel()}". Video berisi sesi membaca curhat anonim dengan tanggapan host.

Judul video: {$p->yt_title}
Isi sesi:
{$items}

Buat 6 pilihan teks thumbnail dalam bahasa Indonesia. Tiap pilihan berisi:
- "utama": teks besar, maksimal 5 kata, memancing penasaran tapi jujur, tidak menyesatkan, tidak membocorkan identitas siapa pun.
- "kecil": teks pendukung maksimal 6 kata (boleh kosong).

Format JSON:
{"pilihan":[{"utama":"...","kecil":"..."}]}
P;
        $j = $this->claude->json($prompt, 1500);

        return array_values(array_filter(array_map(fn ($o) => [
            'main' => trim((string) ($o['utama'] ?? '')),
            'sub' => trim((string) ($o['kecil'] ?? '')),
        ], (array) ($j['pilihan'] ?? [])), fn ($o) => $o['main'] !== ''));
    }

    /** Judul, deskripsi, tag untuk YouTube dan caption Facebook, dari isi video. */
    public function meta(Project $p): array
    {
        $items = $p->items->values()->map(function ($it, $i) {
            $c = $it->curhat;

            return ($i + 1).'. Topik: '.($c->topic ?: 'umum').'. Ringkasan: '.($c->summary ?: Str::limit($it->read_text, 200)).' Pertanyaan ke penonton: '.$it->audience_question;
        })->implode("\n");

        $transcript = $p->transcript
            ? "Transkrip video (bisa berbeda dari naskah karena host berimprovisasi):\n\"\"\"".Str::limit($p->transcript, 20000)."\"\"\"\n"
            : "Naskah pembuka video:\n\"\"\"".Str::limit((string) $p->opening, 3000)."\"\"\"\n";

        $chapters = $p->chapters
            ? "Daftar bab dengan waktu dari host (salin persis ke deskripsi, jangan diubah):\n{$p->chapters}\n"
            : "Host tidak memberi daftar waktu. JANGAN membuat timestamp sendiri.\n";

        $prompt = <<<P
Kamu mengurus upload video untuk channel "{$this->channel()}". Video berisi sesi membaca curhat anonim dari DM page Facebook, dengan tanggapan host dan ajakan penonton untuk beropini.

Isi sesi:
{$items}

{$transcript}
{$chapters}
Buat:
- "judul": 3 pilihan judul YouTube dalam bahasa Indonesia, maksimal 90 karakter, menarik tapi jujur dan tidak menyesatkan.
- "deskripsi": deskripsi YouTube. Paragraf pembuka singkat, daftar topik yang dibahas, daftar bab bila ada, ajakan menjawab pertanyaan di komentar, ajakan mengirim curhat lewat DM page Facebook "{$this->channel()}", lalu catatan bahwa semua cerita dibacakan secara anonim dan tanggapan host adalah pendapat pribadi, bukan pengganti bantuan profesional.
- "tag": 10-15 tag YouTube berupa kata atau frasa pendek.
- "judul_facebook": judul video untuk Facebook, maksimal 90 karakter.
- "caption_facebook": caption Facebook 2-4 kalimat dengan 3-5 hashtag di akhir.

Format JSON:
{"judul":["...","...","..."],"deskripsi":"...","tag":["..."],"judul_facebook":"...","caption_facebook":"..."}
P;
        $j = $this->claude->json($prompt, 4000);

        $titles = array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) ($j['judul'] ?? []))));

        return [
            'titles' => $titles,
            'description' => trim((string) ($j['deskripsi'] ?? '')),
            'tags' => implode(', ', array_map(fn ($t) => trim((string) $t, " #\t\n"), (array) ($j['tag'] ?? []))),
            'fb_title' => trim((string) ($j['judul_facebook'] ?? '')),
            'fb_description' => trim((string) ($j['caption_facebook'] ?? '')),
        ];
    }
}

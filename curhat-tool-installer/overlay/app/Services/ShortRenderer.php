<?php

namespace App\Services;

use RuntimeException;

/**
 * Merender video short vertikal (1080x1920) dari narasi suara + teks, murni lewat FFmpeg.
 *
 * Layout tetap supaya jadi ciri khas channel: logo + watermark di atas, judul hook,
 * kartu teks (label CURHATAN lalu BALASAN) yang berganti halaman mengikuti suara,
 * gelombang suara di bawah kartu, dan ajakan kirim DM di bagian bawah.
 *
 * Semua file yang dipakai FFmpeg disalin ke folder kerja dan dipanggil dengan path
 * relatif (cwd = folder kerja), jadi tidak ada masalah escape "C:\..." di filter FFmpeg
 * pada Windows.
 */
class ShortRenderer
{
    public const W = 1080;
    public const H = 1920;

    private const LEAD = 0.4;   // detik hening sebelum suara mulai
    private const GAP = 0.7;    // jeda antara curhatan dan balasan
    private const TAIL = 1.8;   // layar penutup setelah balasan

    private const CARD = ['x' => 70, 'y' => 520, 'w' => 940, 'h' => 840];
    private const CHARS_PER_LINE = 27;
    private const LINES_PER_PAGE = 7;

    public function __construct(
        private string $ffmpeg = 'ffmpeg',
        private ?string $ffprobe = null,
        private string $brandDir = '',
        private ?string $logo = null,
        private ?string $bgm = null,
        private string $watermark = '@temancurhat',
        private string $channel = 'Teman Curhat',
        private string $cta = 'Mau curhat? Kirim DM ke Page Teman Curhat',
    ) {
        $this->brandDir = $brandDir ?: dirname(__DIR__, 2).'/resources/brand';
        $this->ffprobe ??= $this->deriveProbe($ffmpeg);
    }

    /**
     * @param  array{title:string,label_read?:string,label_reply?:string,read_text:string,reply_text:string,read_audio:string,reply_audio:string}  $spec
     * @return array{duration:float,read:float,reply:float}
     */
    public function render(array $spec, string $output): array
    {
        $dRead = $this->duration($spec['read_audio']);
        $dReply = $this->duration($spec['reply_audio']);
        $tReadStart = self::LEAD;
        $tReplyStart = $tReadStart + $dRead + self::GAP;
        $total = $tReplyStart + $dReply + self::TAIL;

        $work = $this->makeWorkDir();
        try {
            copy($this->brandDir.'/Inter-Bold.otf', $work.'/Inter-Bold.otf');
            copy($this->brandDir.'/Inter-Regular.otf', $work.'/Inter-Regular.otf');
            $logoSrc = ($this->logo && is_file($this->logo)) ? $this->logo : $this->brandDir.'/logo.png';
            copy($logoSrc, $work.'/logo.png');
            copy($spec['read_audio'], $work.'/read.'.pathinfo($spec['read_audio'], PATHINFO_EXTENSION));
            copy($spec['reply_audio'], $work.'/reply.'.pathinfo($spec['reply_audio'], PATHINFO_EXTENSION));
            $readFile = 'read.'.pathinfo($spec['read_audio'], PATHINFO_EXTENSION);
            $replyFile = 'reply.'.pathinfo($spec['reply_audio'], PATHINFO_EXTENSION);

            $hasBgm = $this->bgm && is_file($this->bgm);
            if ($hasBgm) {
                copy($this->bgm, $work.'/bgm.'.pathinfo($this->bgm, PATHINFO_EXTENSION));
                $bgmFile = 'bgm.'.pathinfo($this->bgm, PATHINFO_EXTENSION);
            }

            // Latar gradien dibuat sekali per render (1 frame), murah.
            $this->run([
                $this->ffmpeg, '-y', '-v', 'error', '-f', 'lavfi',
                '-i', 'color=c=black:s='.self::W.'x'.self::H,
                '-vf', "geq=r='lerp(27,64,Y/H)':g='lerp(31,40,Y/H)':b='lerp(59,110,Y/H)'",
                '-frames:v', '1', 'bg.png',
            ], $work);

            file_put_contents($work.'/captions.ass', $this->ass($spec, $tReadStart, $dRead, $tReplyStart, $dReply, $total));

            $inputs = [
                '-loop', '1', '-framerate', '30', '-t', $this->t($total), '-i', 'bg.png',
                '-i', 'logo.png',
                '-i', $readFile,
                '-i', $replyFile,
            ];
            if ($hasBgm) {
                array_push($inputs, '-stream_loop', '-1', '-i', $bgmFile);
            }

            $lead = (int) round(self::LEAD * 1000);
            $audio = "[2:a]aresample=44100,aformat=channel_layouts=stereo,adelay={$lead}|{$lead},apad=pad_dur=".self::GAP.'[a0];'
                .'[3:a]aresample=44100,aformat=channel_layouts=stereo,apad=pad_dur='.self::TAIL.'[a1];'
                .'[a0][a1]concat=n=2:v=0:a=1,loudnorm=I=-16:TP=-1.5:LRA=11[nar];';
            if ($hasBgm) {
                $audio .= '[nar]asplit[n1][n2];[4:a]volume=0.07,aresample=44100,aformat=channel_layouts=stereo[b];'
                    .'[n1][b]amix=inputs=2:duration=first:normalize=0[aout];';
                $waveSrc = '[n2]';
            } else {
                $audio .= '[nar]asplit[aout][n2];';
                $waveSrc = '[n2]';
            }

            $card = self::CARD;
            $waveY = $card['y'] + $card['h'] + 30;
            $video = '[0:v]format=yuv420p[bg];'
                .'[1:v]scale=150:-1[lg];'
                .'[bg][lg]overlay=60:60[v1];'
                .$waveSrc.'showwaves=s=900x110:mode=cline:rate=30:colors=0xFBBF24|0xFBBF24:scale=sqrt:draw=full,colorkey=0x000000:0.15:0.1,format=rgba[wv];'
                .'[v1][wv]overlay=90:'.$waveY.':shortest=0[v2];'
                .'[v2]ass=captions.ass:fontsdir=.[vout]';

            $this->run(array_merge(
                [$this->ffmpeg, '-y', '-v', 'error'],
                $inputs,
                [
                    '-filter_complex', $audio.$video,
                    '-map', '[vout]', '-map', '[aout]',
                    '-t', $this->t($total),
                    '-r', '30', '-c:v', 'libx264', '-preset', 'medium', '-crf', '21', '-pix_fmt', 'yuv420p',
                    '-c:a', 'aac', '-b:a', '160k', '-movflags', '+faststart',
                    'out.mp4',
                ]
            ), $work);

            if (! is_dir(dirname($output))) {
                mkdir(dirname($output), 0777, true);
            }
            if (! copy($work.'/out.mp4', $output)) {
                throw new RuntimeException('Gagal menyimpan video ke '.$output);
            }
        } finally {
            $this->rmrf($work);
        }

        return ['duration' => $total, 'read' => $dRead, 'reply' => $dReply];
    }

    public function duration(string $file): float
    {
        $out = $this->run([$this->ffprobe, '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $file]);
        $d = (float) trim($out);
        if ($d <= 0) {
            throw new RuntimeException('Durasi audio tidak terbaca: '.$file);
        }

        return $d;
    }

    // ---------------------------------------------------------------- ASS

    private function ass(array $spec, float $t0, float $d0, float $t1, float $d1, float $total): string
    {
        $c = self::CARD;
        $cx = $c['x'] + intdiv($c['w'], 2);
        $cy = $c['y'] + intdiv($c['h'], 2) + 30;
        $amber = '&H0024BFFB'; // FBBF24 dalam BGR
        $white = '&H00FFFFFF';

        $h = "[Script Info]\nScriptType: v4.00+\nPlayResX: ".self::W."\nPlayResY: ".self::H."\nWrapStyle: 2\nScaledBorderAndShadow: yes\n\n"
            ."[V4+ Styles]\nFormat: Name,Fontname,Fontsize,PrimaryColour,SecondaryColour,OutlineColour,BackColour,Bold,Italic,Underline,StrikeOut,ScaleX,ScaleY,Spacing,Angle,BorderStyle,Outline,Shadow,Alignment,MarginL,MarginR,MarginV,Encoding\n"
            ."Style: Title,Inter,66,{$white},{$white},&H00000000,&H00000000,1,0,0,0,100,100,0,0,1,0,0,8,60,60,0,1\n"
            ."Style: Label,Inter,40,{$amber},{$amber},&H00000000,&H00000000,1,0,0,0,100,100,4,0,1,0,0,8,60,60,0,1\n"
            ."Style: Body,Inter,54,{$white},{$white},&H00000000,&H00000000,1,0,0,0,100,100,0,0,1,0,0,5,60,60,0,1\n"
            ."Style: Mark,Inter,34,&H70FFFFFF,&H70FFFFFF,&H00000000,&H00000000,1,0,0,0,100,100,2,0,1,0,0,9,40,40,0,1\n"
            ."Style: Cta,Inter,38,{$white},{$white},&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,0,0,8,60,60,0,1\n"
            ."Style: Shape,Inter,20,{$white},{$white},&H00000000,&H00000000,0,0,0,0,100,100,0,0,1,0,0,7,0,0,0,1\n\n"
            ."[Events]\nFormat: Layer,Start,End,Style,Name,MarginL,MarginR,MarginV,Effect,Text\n";

        $all = $this->ts(0).','.$this->ts($total);
        $ev = [];
        // Kartu: latar gelap transparan + garis aksen di sisi kiri.
        $ev[] = "Dialogue: 0,{$all},Shape,,0,0,0,,{\\pos(0,0)\\1c&H3B1F1B&\\1a&H38&\\p1}".$this->rect($c['x'], $c['y'], $c['w'], $c['h']).'{\\p0}';
        $ev[] = "Dialogue: 1,{$all},Shape,,0,0,0,,{\\pos(0,0)\\1c{$amber}&\\1a&H00&\\p1}".$this->rect($c['x'], $c['y'], 12, $c['h']).'{\\p0}';
        // Judul hook, watermark, ajakan.
        $title = $this->wrap($this->esc(mb_strtoupper($spec['title'])), 24, 2);
        $ev[] = "Dialogue: 2,{$all},Title,,0,0,0,,{\\pos(540,250)\\an8}".$title;
        $ev[] = "Dialogue: 2,{$all},Mark,,0,0,0,,{\\pos(1020,70)\\an9}".$this->esc($this->watermark);
        $ev[] = "Dialogue: 2,{$all},Cta,,0,0,0,,{\\pos(540,1610)\\an8}".$this->esc($this->cta)
            ."\\N{\\fs30\\1c{$amber}}".$this->esc('Follow '.$this->channel.' untuk curhatan berikutnya');

        $segments = [
            [$spec['label_read'] ?? 'CURHATAN', $spec['read_text'], $t0, $d0],
            [$spec['label_reply'] ?? 'BALASAN', $spec['reply_text'], $t1, $d1],
        ];
        foreach ($segments as [$label, $text, $start, $dur]) {
            $end = $start + $dur;
            $labelEnd = $label === ($spec['label_read'] ?? 'CURHATAN') ? $t1 - 0.05 : $total;
            $labelStart = $label === ($spec['label_read'] ?? 'CURHATAN') ? 0 : $t1 - self::GAP + 0.05;
            $ev[] = 'Dialogue: 3,'.$this->ts($labelStart).','.$this->ts($labelEnd).",Label,,0,0,0,,{\\pos(540,".($c['y'] + 36).")\\an8}".$this->esc($label);
            foreach ($this->pages($text, $start, $dur) as [$a, $b, $body]) {
                // Halaman terakhir segmen bertahan sampai segmen berikutnya mulai.
                $b = ($b >= $end - 0.01) ? ($label === ($spec['label_read'] ?? 'CURHATAN') ? $t1 - 0.05 : $total) : $b;
                $ev[] = 'Dialogue: 3,'.$this->ts($a).','.$this->ts($b).",Body,,0,0,0,,{\\pos({$cx},{$cy})\\an5}".$body;
            }
        }

        return $h.implode("\n", $ev)."\n";
    }

    /** Pecah teks jadi halaman; durasi tiap halaman sebanding dengan jumlah hurufnya. */
    private function pages(string $text, float $start, float $dur): array
    {
        $sentences = preg_split('/(?<=[.!?…])\s+|\n+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $lines = [];
        foreach ($sentences as $s) {
            foreach ($this->wrapLines($s, self::CHARS_PER_LINE) as $l) {
                $lines[] = $l;
            }
        }
        $pages = array_chunk($lines, self::LINES_PER_PAGE);
        // Hindari halaman yatim (1 baris) di akhir: gabung ke halaman sebelumnya bila muat.
        $weights = array_map(fn ($p) => max(1, mb_strlen(implode(' ', $p))), $pages);
        $sum = array_sum($weights) ?: 1;

        $out = [];
        $cursor = $start;
        foreach ($pages as $i => $p) {
            $len = $dur * $weights[$i] / $sum;
            $out[] = [$cursor, $cursor + $len, implode('\\N', array_map([$this, 'esc'], $p))];
            $cursor += $len;
        }

        return $out;
    }

    private function wrapLines(string $s, int $width): array
    {
        $lines = [];
        $cur = '';
        foreach (preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) as $w) {
            if ($cur !== '' && mb_strlen($cur) + 1 + mb_strlen($w) > $width) {
                $lines[] = $cur;
                $cur = $w;
            } else {
                $cur = $cur === '' ? $w : $cur.' '.$w;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return $lines;
    }

    /** Wrap untuk teks yang sudah di-escape (judul). */
    private function wrap(string $escaped, int $width, int $maxLines): string
    {
        $lines = $this->wrapLines($escaped, $width);
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1], ' ,.').'…';
        }

        return implode('\\N', $lines);
    }

    private function rect(int $x, int $y, int $w, int $h): string
    {
        $x2 = $x + $w;
        $y2 = $y + $h;

        return "m {$x} {$y} l {$x2} {$y} {$x2} {$y2} {$x} {$y2}";
    }

    private function esc(string $s): string
    {
        // Kurung kurawal membuka override tag di ASS; garis miring terbalik jadi kode.
        return str_replace(['\\', '{', '}', "\n"], ['', '(', ')', ' '], $s);
    }

    private function ts(float $s): string
    {
        $s = max(0, $s);
        $cs = (int) round(($s - floor($s)) * 100);
        if ($cs === 100) {
            $cs = 99;
        }

        return sprintf('%d:%02d:%02d.%02d', intdiv((int) $s, 3600), intdiv((int) $s % 3600, 60), (int) $s % 60, $cs);
    }

    private function t(float $s): string
    {
        return number_format($s, 3, '.', '');
    }

    // ---------------------------------------------------------------- proses

    private function deriveProbe(string $ffmpeg): string
    {
        $base = basename($ffmpeg);
        if (stripos($base, 'ffmpeg') === 0) {
            return dirname($ffmpeg) === '.' && ! str_contains($ffmpeg, '/') && ! str_contains($ffmpeg, '\\')
                ? 'ffprobe'
                : dirname($ffmpeg).DIRECTORY_SEPARATOR.str_ireplace('ffmpeg', 'ffprobe', $base);
        }

        return 'ffprobe';
    }

    private function run(array $cmd, ?string $cwd = null): string
    {
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (! is_resource($p)) {
            throw new RuntimeException('Tidak bisa menjalankan '.$cmd[0].'. Isi path ffmpeg di Pengaturan.');
        }
        // Kosongkan stderr di proses terpisah agar tidak deadlock bila keluarannya besar.
        stream_set_blocking($pipes[2], false);
        $out = '';
        $err = '';
        while (! feof($pipes[1])) {
            $out .= (string) fread($pipes[1], 8192);
            $err .= (string) fread($pipes[2], 8192);
        }
        $err .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if ($code !== 0) {
            throw new RuntimeException('FFmpeg gagal ('.basename($cmd[0])."): ".mb_substr(trim($err), -1200));
        }

        return $out;
    }

    private function makeWorkDir(): string
    {
        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'curhat-short-'.bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);

        return $dir;
    }

    private function rmrf(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $f) {
            is_dir($f) ? $this->rmrf($f) : @unlink($f);
        }
        @rmdir($dir);
    }
}

# Teman Curhat

Aplikasi internal untuk mengelola alur konten channel "Teman Curhat": menarik DM
curhat dari Facebook Messenger, menganonimkan dan menulis naskah balasan dengan
AI, membuat narasi suara (TTS), lalu menjadwalkan video ke YouTube dan Facebook.

## Struktur

- [`app/`](app/) — aplikasi Laravel utama (dijalankan di `C:\wamp64\www\curhat-tool\app`).
- [`curhat-tool-installer/`](curhat-tool-installer/) — installer satu-klik untuk WAMP
  (`install.bat`) beserta `overlay/`, salinan sumber dari `app/` yang disalin installer
  saat instalasi/pembaruan.

## Instalasi

Lihat [`curhat-tool-installer/PANDUAN.md`](curhat-tool-installer/PANDUAN.md).

## Catatan

- `app/.env` tidak ikut ter-commit (lihat `.gitignore`). Salin dari
  `curhat-tool-installer/overlay/.env.curhat` dan isi kredensial sendiri (Facebook,
  Google, Anthropic/9Router, dll) lewat menu Pengaturan setelah instalasi.
- Setiap perubahan di `app/` yang juga relevan untuk instalasi baru perlu disalin
  manual ke `curhat-tool-installer/overlay/` (keduanya belum di-symlink).

## Short harian otomatis

`php artisan curhat:make-short` membuat satu video short 9:16 dari satu DM curhat: curhatan
(anonim) dibacakan, lalu balasan host, dengan logo, watermark, judul, gelombang suara, dan
ajakan kirim DM. Hasilnya masuk antrean upload YouTube + Facebook yang sudah ada
(`curhat:process-uploads`).

- Jalan otomatis lewat scheduler (tiap 10 menit, hanya satu video per hari, setelah jam
  "Dibuat setelah jam" di Pengaturan > Short Harian Otomatis). Gagal? dicoba ulang maksimal 3x/hari.
- Curhat yang belum dianonimkan dianonimkan dulu; yang ditandai sensitif atau berizin
  "Tidak boleh" dilewati. Yang berizin "Boleh" didahulukan. Balasan dibuat AI bila belum ada.
- Suara: TTS 9Router; bila gagal/kuota habis otomatis ke edge-tts (`pip install edge-tts`).
- Butuh FFmpeg (isi path-nya di Pengaturan bila tidak ada di PATH).
- Coba manual: `php artisan curhat:make-short --force` (tambahkan `--no-upload` untuk render saja,
  `--curhat=ID` untuk memilih curhat tertentu). Video tersimpan di `storage/app/shorts/`.
- Setelah `git pull` jalankan `php artisan migrate`.


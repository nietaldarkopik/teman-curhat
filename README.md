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

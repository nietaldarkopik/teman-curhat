# Studio Teman Curhat — Panduan

Aplikasi Laravel lokal untuk mengelola DM curhat dari page Facebook, menyusun project video
berisi 5–10 curhat dengan naskah dari AI, lalu menjadwalkan upload ke YouTube dan Facebook.

## 1. Instalasi

Prasyarat: WAMP menyala (ikon hijau), PHP 8.2+ ada di PATH, dan Composer sudah terpasang.
Database memakai MySQL bawaan WAMP (user `root`, tanpa password). Kalau berbeda, ubah `DB_*`
di `C:\wamp64\www\curhat-tool\.env` lalu jalankan `php artisan curhat:install`.

1. Ekstrak zip ini ke mana saja.
2. Klik dua kali `install.bat`.
3. Buka **http://localhost/curhat-tool/public**

`install.bat` aman dijalankan ulang untuk memperbarui aplikasi. File `.env` dan data tidak akan tertimpa.

## 2. Pengaturan

### Claude AI
Buat API key di console.anthropic.com, lalu tempel di **Pengaturan > Claude AI**.

### Facebook Page
1. Buka developers.facebook.com, buat App baru (tipe Business).
2. Tambahkan produk **Facebook Login** (dan **Messenger** bila diminta untuk akses percakapan Page).
3. Di Facebook Login > Settings, isi *Valid OAuth Redirect URIs* dengan alamat yang tertera di halaman Pengaturan.
4. Salin App ID dan App Secret ke Pengaturan, simpan, lalu klik **Login dengan Facebook** dan pilih page Teman Curhat.

Selama App dalam *Development mode*, izin Page bisa dipakai oleh akun yang menjadi admin App
sekaligus admin Page, tanpa App Review. Ini cukup untuk alat pribadi seperti ini.

Catatan: Facebook hanya memberi pesan-pesan terbaru di setiap percakapan, jadi DM yang sangat lama
mungkin tidak ikut tersinkron. Pesan beruntun dari orang yang sama dalam 6 jam digabung menjadi satu curhat
(bisa diubah di pengaturan lanjutan).

### YouTube
1. Buka console.cloud.google.com, buat project, aktifkan **YouTube Data API v3**.
2. Atur **OAuth consent screen** (External), tambahkan email Anda sebagai test user.
3. Buat **Credentials > OAuth client ID** tipe *Web application*, isi *Authorized redirect URI* dengan alamat di halaman Pengaturan.
4. Salin Client ID dan Secret ke Pengaturan, simpan, lalu klik **Login dengan Google**.

Dua hal penting:

- **Status "Testing" membuat login kedaluwarsa tiap 7 hari.** Setelah semuanya jalan, ubah status consent
  screen ke *In production*. Google akan menampilkan layar peringatan "aplikasi belum diverifikasi" saat login;
  klik lanjutkan. Untuk pemakaian sendiri ini tidak masalah.
- **Video dari project API yang belum diaudit dikunci menjadi Private.** Ini kebijakan YouTube untuk
  upload lewat API. Ajukan *YouTube API Services Audit* dari halaman bantuan YouTube API. Sampai disetujui,
  video tetap terunggah tapi harus Anda ubah ke publik secara manual di YouTube Studio.

### Folder video
Isi dengan folder tempat OBS menyimpan rekaman, misalnya `C:\Users\Opik\Videos`.
File terbaru akan muncul sebagai pilihan di halaman upload.

## 3. Alur kerja

1. **Inbox curhat.** DM tersinkron otomatis tiap 15 menit (atau klik *Sinkron DM sekarang* di Dashboard).
   Curhat yang dibuka otomatis ditandai *sudah dibaca*. Filter, urutkan, beri bintang, dan jalankan AI
   untuk menganonimkan serta memberi topik secara massal.
2. **Project video.** Buat project, centang 5–10 curhat, atur urutannya, lalu klik
   *Generate naskah yang belum ada*. AI menulis pembuka, pengantar + balasan + lemparan ke penonton
   untuk tiap curhat, dan penutup. Semua bisa diedit. Isi kolom *Pendapat Anda* di curhat bila ingin
   balasan AI mengikuti sudut pandang Anda.
3. **Rekam.** Buka *Teleprompter* untuk dibaca saat on-cam, atau *Tampilan layar* untuk direkam OBS
   (curhat muncul sebagai gelembung chat; tekan spasi / panah kanan untuk lanjut, F untuk layar penuh).
4. **Upload.** Pilih file video, isi daftar bab dan transkrip bila ada, klik *Generate dengan AI*
   untuk judul, deskripsi, tag, dan caption Facebook. Pilih jam tayang, lalu *Jadwalkan upload*.
   Scheduler mengunggah dalam satu menit dan platform menayangkannya pada jam yang dipilih.

## 4. Scheduler

`install.bat` membuat tugas **CurhatTool Scheduler** di Task Scheduler Windows yang berjalan tiap menit
tanpa jendela. Log ada di `storage\logs\scheduler.log`. Scheduler butuh komputer menyala dan WAMP aktif
saat upload berlangsung, tapi tidak saat jam tayang.

Tanpa scheduler, antrean bisa diproses manual lewat tombol *Proses antrean sekarang*
(untuk video besar lebih aman lewat scheduler karena browser bisa time out).

## 5. Keamanan

Aplikasi ini tidak memakai login dan ditujukan untuk localhost saja. Jangan aktifkan mode *Online*
di WAMP. Token Facebook, Google, dan API key disimpan terenkripsi di database memakai `APP_KEY`.
Nama pengirim DM tidak pernah dikirim ke AI.

## 6. Kalau ada masalah

- **cURL error 60**: aplikasi sudah membawa sertifikat sendiri di `resources/cacert.pem`. Kalau masih muncul, jalankan ulang `install.bat`.
- **Error MySQL saat instalasi**: pastikan WAMP hijau, lalu cek `DB_*` di `.env`.
- **Upload gagal**: pesan error tampil di halaman *Jadwal upload*. Perbaiki penyebabnya, lalu klik *Coba lagi*.
- **Ubah gaya tulisan AI**: edit *Gaya bicara host* di Pengaturan. Prompt lengkap ada di `app/Services/AiWriter.php`.

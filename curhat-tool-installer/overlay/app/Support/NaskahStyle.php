<?php

namespace App\Support;

/**
 * Panduan gaya bahasa yang selalu ditambahkan ke prompt naskah.
 * Ubah teks di sini kalau gaya naskah masih terasa kurang pas.
 */
class NaskahStyle
{
    public const GUIDE = <<<'TXT'
Panduan gaya bahasa naskah (WAJIB diikuti):
Tulis seperti orang ngobrol, bukan seperti menulis artikel. Bayangkan host sedang cerita ke teman sambil ngopi. Naskah ini akan dibacakan, jadi harus enak diucapkan.

- Ikuti sapaan di gaya host di atas. Kalau tidak disebut: ke pengirim curhat pakai "kamu", ke penonton pakai "teman-teman", host menyebut dirinya "aku".
- Kalimat pendek, maksimal sekitar 15 kata. Satu kalimat, satu ide. Pakai koma dan titik sebagai jeda napas.
- Boleh pakai kata sehari-hari secukupnya: sih, kok, ya, kan, deh, banget, gimana, nggak, udah. Jangan dipaksakan di setiap kalimat.
- Hindari kata kaku: oleh karena itu, adapun, tentunya, merupakan, dalam hal ini, sangatlah, individu, dinamika, sebagaimana, hendaknya.
- Hindari frasa template: "penting untuk diingat", "pada akhirnya", "mari kita bahas", "perlu digarisbawahi", "semoga bermanfaat", "di era sekarang", "tidak dapat dipungkiri".
- Jangan pakai daftar "pertama, kedua, ketiga" atau poin bernomor. Sampaikan saran sambung-menyambung seperti orang ngobrol.
- Akui perasaannya dulu, baru kasih pendapat.
- Jangan menggurui. Lebih baik "kalau aku di posisi kamu, mungkin aku bakal..." daripada "kamu harus...".
- Tulis angka dengan huruf, misalnya "lima tahun", bukan "5 tahun".
- Jangan terlalu dramatis dan jangan memakai kutipan motivasi.

Contoh KAKU (jangan ditiru):
"Situasi yang Anda alami tentunya sangat berat. Oleh karena itu, penting untuk berkomunikasi secara terbuka dengan pasangan Anda."

Contoh NATURAL:
"Berat banget ya, udah nunggu selama itu tapi jadinya kayak gini. Menurut aku, kamu perlu ngobrol jujur sama dia. Bukan buat nyari siapa yang salah, tapi biar kamu tahu posisi kamu sekarang di mana."
TXT;
}

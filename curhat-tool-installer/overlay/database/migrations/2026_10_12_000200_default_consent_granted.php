<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kirim DM curhat dianggap sudah menyiratkan izin dipakai (kebijakan channel) —
 * DM baru tidak perlu ditandai manual lagi di menu Izin sebelum bisa dipakai
 * short-maker. Nama tetap disamarkan kecuali pengirim jelas memperkenalkan diri
 * (lihat AiWriter::analyze). Pakai SQL mentah (bukan ->change()) supaya tidak
 * butuh paket doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE curhats ALTER COLUMN consent_status SET DEFAULT 'granted'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE curhats ALTER COLUMN consent_status SET DEFAULT 'unknown'");
    }
};

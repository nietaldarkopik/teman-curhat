<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curhats', function (Blueprint $t) {
            // pria | wanita | tidak_diketahui (default, sebelum dianalisis / tidak jelas dari teks)
            $t->string('sender_gender', 20)->nullable()->after('topic');
        });
    }

    public function down(): void
    {
        Schema::table('curhats', function (Blueprint $t) {
            $t->dropColumn('sender_gender');
        });
    }
};

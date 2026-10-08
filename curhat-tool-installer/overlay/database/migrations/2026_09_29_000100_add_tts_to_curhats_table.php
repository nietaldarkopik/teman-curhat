<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curhats', function (Blueprint $t) {
            $t->string('tts_path')->nullable()->after('audience_question');
            $t->timestamp('tts_generated_at')->nullable()->after('tts_path');
        });
    }

    public function down(): void
    {
        Schema::table('curhats', function (Blueprint $t) {
            $t->dropColumn(['tts_path', 'tts_generated_at']);
        });
    }
};

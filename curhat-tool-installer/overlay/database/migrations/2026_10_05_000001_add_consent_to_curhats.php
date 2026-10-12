<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('curhats', 'consent_status')) {
            return;
        }

        Schema::table('curhats', function (Blueprint $t) {
            // unknown = belum ditanya, requested = sudah ditanya, granted = boleh, denied = tidak boleh
            $t->string('consent_status', 20)->default('unknown')->index()->after('status');
            $t->text('consent_note')->nullable()->after('consent_status');
            $t->timestamp('consent_at')->nullable()->after('consent_note');
        });
    }

    public function down(): void
    {
        Schema::table('curhats', function (Blueprint $t) {
            $t->dropIndex(['consent_status']);
            $t->dropColumn(['consent_status', 'consent_note', 'consent_at']);
        });
    }
};

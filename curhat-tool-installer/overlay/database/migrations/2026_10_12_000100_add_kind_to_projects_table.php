<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('projects', 'kind')) {
            return;
        }

        Schema::table('projects', function (Blueprint $t) {
            // session = sesi panjang direkam host; short = video short otomatis (curhat:make-short)
            $t->string('kind', 20)->default('session')->index()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->dropIndex(['kind']);
            $t->dropColumn('kind');
        });
    }
};

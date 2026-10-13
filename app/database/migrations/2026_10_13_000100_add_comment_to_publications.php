<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $t) {
            $t->timestamp('comment_posted_at')->nullable();
            $t->unsignedTinyInteger('comment_attempts')->default(0);
            $t->string('comment_error', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('publications', function (Blueprint $t) {
            $t->dropColumn(['comment_posted_at', 'comment_attempts', 'comment_error']);
        });
    }
};

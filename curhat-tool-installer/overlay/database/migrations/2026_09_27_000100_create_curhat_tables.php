<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 100)->unique();
            $t->longText('value')->nullable();
            $t->timestamps();
        });

        Schema::create('curhats', function (Blueprint $t) {
            $t->id();
            $t->string('source', 20)->default('facebook');
            $t->string('fb_conversation_id', 100)->nullable()->index();
            $t->string('sender_name', 191)->nullable();
            $t->string('sender_id', 100)->nullable();
            $t->longText('original_text');
            $t->longText('anon_text')->nullable();
            $t->text('summary')->nullable();
            $t->string('topic', 100)->nullable()->index();
            $t->boolean('is_sensitive')->default(false);
            $t->text('sensitive_note')->nullable();
            $t->string('status', 20)->default('baru')->index();
            $t->boolean('is_starred')->default(false);
            $t->longText('reply_text')->nullable();
            $t->text('audience_question')->nullable();
            $t->text('notes')->nullable();
            $t->unsignedInteger('char_count')->default(0);
            $t->timestamp('received_at')->nullable()->index();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });

        Schema::create('fb_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('curhat_id')->nullable()->constrained('curhats')->nullOnDelete();
            $t->string('fb_message_id', 191)->unique();
            $t->string('fb_conversation_id', 100)->index();
            $t->string('from_id', 100)->nullable();
            $t->text('message');
            $t->timestamp('created_time')->nullable();
            $t->timestamps();
        });

        Schema::create('projects', function (Blueprint $t) {
            $t->id();
            $t->string('title', 191);
            $t->date('planned_date')->nullable();
            $t->string('status', 20)->default('draf')->index();
            $t->longText('opening')->nullable();
            $t->longText('closing')->nullable();
            $t->string('video_path', 500)->nullable();
            $t->longText('transcript')->nullable();
            $t->text('chapters')->nullable();
            $t->text('title_options')->nullable();
            $t->string('yt_title', 191)->nullable();
            $t->longText('yt_description')->nullable();
            $t->text('yt_tags')->nullable();
            $t->string('fb_title', 191)->nullable();
            $t->longText('fb_description')->nullable();
            $t->timestamps();
        });

        Schema::create('project_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->foreignId('curhat_id')->constrained('curhats')->cascadeOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->longText('read_text')->nullable();
            $t->text('intro_text')->nullable();
            $t->longText('reply_text')->nullable();
            $t->text('audience_question')->nullable();
            $t->timestamps();
            $t->unique(['project_id', 'curhat_id']);
        });

        Schema::create('publications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $t->string('platform', 20);
            $t->string('status', 20)->default('menunggu')->index();
            $t->string('privacy', 20)->default('public');
            $t->dateTime('publish_at')->nullable();
            $t->string('remote_id', 191)->nullable();
            $t->string('remote_url', 500)->nullable();
            $t->text('error')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->dateTime('uploaded_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
        Schema::dropIfExists('project_items');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('fb_messages');
        Schema::dropIfExists('curhats');
        Schema::dropIfExists('settings');
    }
};

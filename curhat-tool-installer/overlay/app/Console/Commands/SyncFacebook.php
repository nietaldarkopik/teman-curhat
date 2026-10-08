<?php

namespace App\Console\Commands;

use App\Services\FacebookService;
use Illuminate\Console\Command;

class SyncFacebook extends Command
{
    protected $signature = 'curhat:sync-facebook';

    protected $description = 'Tarik DM baru dari page Facebook';

    public function handle(FacebookService $fb): int
    {
        if (! $fb->connected()) {
            $this->line('Facebook belum terhubung, dilewati.');

            return self::SUCCESS;
        }
        try {
            $r = $fb->sync();
            $this->info("Percakapan diperiksa: {$r['conversations']}, pesan baru: {$r['messages']}");
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

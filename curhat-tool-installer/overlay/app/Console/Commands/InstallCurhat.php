<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PDO;

class InstallCurhat extends Command
{
    protected $signature = 'curhat:install';

    protected $description = 'Buat database MySQL (bila belum ada) lalu jalankan migrasi';

    public function handle(): int
    {
        $c = config('database.connections.'.config('database.default'));
        if (($c['driver'] ?? '') === 'mysql' || ($c['driver'] ?? '') === 'mariadb') {
            try {
                $pdo = new PDO("mysql:host={$c['host']};port={$c['port']}", $c['username'], $c['password']);
                $db = str_replace('`', '', $c['database']);
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $this->info("Database {$db} siap.");
            } catch (\Throwable $e) {
                $this->error('Tidak bisa terhubung ke MySQL: '.$e->getMessage());
                $this->line('Pastikan WAMP menyala (ikon hijau) dan isi DB_* di file .env sudah benar.');

                return self::FAILURE;
            }
        }
        $this->call('migrate', ['--force' => true]);
        $this->info('Instalasi selesai.');

        return self::SUCCESS;
    }
}

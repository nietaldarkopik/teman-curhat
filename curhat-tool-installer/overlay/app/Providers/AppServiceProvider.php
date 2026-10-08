<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // WAMP kadang memakai MyISAM sebagai engine default (batas index 1000 byte).
        // Paksa InnoDB supaya index gabungan di migrasi tidak gagal.
        foreach (['mysql', 'mariadb'] as $conn) {
            if (! config("database.connections.{$conn}.engine")) {
                config(["database.connections.{$conn}.engine" => 'InnoDB']);
            }
        }
    }

    public function boot(): void
    {
        // Aman untuk MySQL 5.x / MariaDB lama.
        Schema::defaultStringLength(191);

        $tz = config('curhat.timezone', 'Asia/Jakarta');
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);
        \Illuminate\Support\Carbon::setLocale('id');

        Paginator::defaultSimpleView('pagination::simple-default');
        Paginator::defaultView('pagination::default');
    }
}

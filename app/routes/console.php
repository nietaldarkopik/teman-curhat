<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('curhat:process-uploads')->everyMinute()->withoutOverlapping(240);
Schedule::command('curhat:sync-facebook')->everyFifteenMinutes()->withoutOverlapping(30);
// Short harian: dicek tiap 10 menit, tapi hanya membuat satu video per hari setelah jam
// "short_make_time" (Pengaturan). Kalau komputer mati di jam itu, dibuat begitu menyala lagi.
Schedule::command('curhat:make-short')->everyTenMinutes()->withoutOverlapping(60);

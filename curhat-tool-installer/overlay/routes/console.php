<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('curhat:process-uploads')->everyMinute()->withoutOverlapping(240);
Schedule::command('curhat:sync-facebook')->everyFifteenMinutes()->withoutOverlapping(30);

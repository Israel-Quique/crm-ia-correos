<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('crm:sync-api')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('postal:sync nacional --months=12')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('postal:sync internacional --months=12')->everyFiveMinutes()->withoutOverlapping();

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

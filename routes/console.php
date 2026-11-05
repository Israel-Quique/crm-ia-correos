<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Schedule::command('crm:sync-api')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('postal:sync nacional --months=12')->everyThirtyMinutes()->withoutOverlapping()->when(fn () => (bool) ((int) DB::table('app_settings')->where('key', 'postal_sync_enabled')->value('value')));
Schedule::command('postal:sync internacional --months=12')->everyThirtyMinutes()->withoutOverlapping()->when(fn () => (bool) ((int) DB::table('app_settings')->where('key', 'postal_sync_enabled')->value('value')));
Schedule::command('cds:sync')->everyThirtyMinutes()->withoutOverlapping()->when(fn () => (bool) ((int) DB::table('app_settings')->where('key', 'postal_sync_enabled')->value('value')));

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

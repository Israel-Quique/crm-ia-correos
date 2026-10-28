<?php
require __DIR__.'/vendor/autoload.php'; $app=require __DIR__.'/bootstrap/app.php'; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::table('postal_packages'); echo 'total='.$db->count().PHP_EOL.'sitra='.$db->where('source','sitra')->count().PHP_EOL.'siop='.$db->where('source','siop')->count().PHP_EOL;

<?php
namespace App\Console\Commands;
use App\Services\ApiRecordSyncService;
use Illuminate\Console\Command;
use Throwable;
class SyncApiRecords extends Command
{
    protected $signature = 'crm:sync-api {source=crm} {--url=}';
    protected $description = 'Sincroniza hasta 1000 registros de una API y evita duplicados';
    public function handle(ApiRecordSyncService $sync): int
    {
        try { $result = $sync->sync($this->argument('source'), $this->option('url')); $this->info(json_encode($result)); return self::SUCCESS; }
        catch (Throwable $e) { $this->error($e->getMessage()); return self::FAILURE; }
    }
}

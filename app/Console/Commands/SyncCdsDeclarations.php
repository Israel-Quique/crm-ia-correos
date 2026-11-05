<?php
namespace App\Console\Commands;
use App\Services\CdsDeclarationSyncService;
use Illuminate\Console\Command;
class SyncCdsDeclarations extends Command { protected $signature='cds:sync {--tracking=* : Sincroniza únicamente estas guías}'; protected $description='Sincroniza declaraciones CDS con los paquetes postales'; public function handle(CdsDeclarationSyncService $sync): int { try{$tracking=array_values(array_filter(array_map(fn($value)=>strtoupper(trim($value)),(array)$this->option('tracking'))));$this->info(json_encode($sync->sync($tracking?:null),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));return self::SUCCESS;}catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;} } }

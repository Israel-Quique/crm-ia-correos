<?php
namespace App\Console\Commands;
use App\Services\PostalApiSyncService;
use Illuminate\Console\Command;
use Throwable;
class SyncPostalPackages extends Command
{
    protected $signature='postal:sync {scope : nacional o internacional} {--months=12 : Cantidad de meses calendario a consultar}'; protected $description='Sincroniza paquetes y eventos desde SIOP o SITRA';
    public function handle(PostalApiSyncService $sync): int { $scope=$this->argument('scope'); $months=max(1,(int)$this->option('months')); if(!in_array($scope,['nacional','internacional'],true)){ $this->error('El alcance debe ser nacional o internacional.'); return self::FAILURE; } try{$this->info(json_encode($sync->sync($scope,$months),JSON_PRETTY_PRINT));return self::SUCCESS;}catch(Throwable $e){$this->error($e->getMessage());return self::FAILURE;} }
}

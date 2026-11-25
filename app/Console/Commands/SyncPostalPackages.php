<?php
namespace App\Console\Commands;
use App\Services\PostalApiSyncService;
use Illuminate\Console\Command;
use Throwable;
class SyncPostalPackages extends Command
{
    protected $signature='postal:sync {scope : nacional o internacional} {--months=12 : Cantidad de meses calendario a consultar}'; protected $description='Sincroniza paquetes y eventos desde SIOP o SITRA';
    public function handle(PostalApiSyncService $sync): int
    {
        $scope = $this->argument('scope');
        $months = max(1, (int) $this->option('months'));
        if (! in_array($scope, ['nacional', 'internacional'], true)) {
            $this->error('El alcance debe ser nacional o internacional.');
            return self::FAILURE;
        }

        $this->info(sprintf('Iniciando sincronización %s (%d meses)...', strtoupper($scope), $months));
        try {
            $result = $sync->sync($scope, $months);
            $this->newLine();
            $this->info('Sincronización completada correctamente.');
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Alcance', strtoupper((string) ($result['scope'] ?? $scope))],
                    ['Fuente', strtoupper((string) ($result['source'] ?? ''))],
                    ['Periodo desde', (string) ($result['from'] ?? 'No informado')],
                    ['Periodo hasta', (string) ($result['to'] ?? 'No informado')],
                    ['Registros recibidos', number_format((int) ($result['received'] ?? 0))],
                    ['Páginas importadas', number_format((int) ($result['pages'] ?? 0))],
                    ['Registros creados', number_format((int) ($result['created'] ?? 0))],
                    ['Registros actualizados', number_format((int) ($result['updated'] ?? 0))],
                    ['Última página reportada', (string) ($result['reported_last_page'] ?? 'N/D')],
                    ['Cobertura omitida', ! empty($result['skipped']) ? 'Sí, ya estaba cubierta' : 'No'],
                ],
            );
            $this->info(sprintf(
                'Importado %s hasta %s: %s recibidos, %s creados y %s actualizados.',
                strtoupper($scope),
                $result['to'] ?? 'fecha no informada',
                number_format((int) ($result['received'] ?? 0)),
                number_format((int) ($result['created'] ?? 0)),
                number_format((int) ($result['updated'] ?? 0)),
            ));
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('La sincronización falló: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}

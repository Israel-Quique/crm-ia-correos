<?php

namespace App\Livewire;

use App\Models\PostalEvent;
use App\Models\PostalPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class ProjectTestPanel extends Component
{
    public array $checks = [];
    public ?string $lastRun = null;

    public function runChecks(): void
    {
        $this->checks = [
            'database' => $this->checkDatabase(),
            'siop' => $this->checkPostalApi('siop'),
            'sitra' => $this->checkPostalApi('sitra'),
            'packages' => $this->checkCounts(),
        ];
        $this->lastRun = now()->format('d/m/Y H:i:s');
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('select 1');
            return ['ok' => true, 'label' => 'PostgreSQL conectado', 'detail' => 'Conexión disponible'];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'label' => 'PostgreSQL con error', 'detail' => 'No se pudo conectar'];
        }
    }

    private function checkPostalApi(string $source): array
    {
        $url = config("services.postal.{$source}_url");
        $token = config("services.postal.{$source}_token");
        try {
            $request = Http::withOptions(['verify' => config('services.postal.verify_ssl')])
                ->timeout(10)->acceptJson();
            if ($token) $request = $request->withToken($token);
            $response = $request->get($url, ['page' => 1, 'per_page' => 1]);
            if ($response->unauthorized()) {
                $credential = $token ? 'El servicio rechazó el token configurado.' : 'Falta configurar el token.';
                $setting = $source === 'siop' ? 'PAQUETES_EVENTOS_API_TOKEN' : 'TRACKING_SQLSERVER_TOKEN';

                return [
                    'ok' => false,
                    'label' => strtoupper($source).' rechazó autenticación',
                    'detail' => "HTTP 401 · {$credential} Revisa {$setting} y solicita al administrador de la API un token vigente con acceso a este endpoint.",
                ];
            }

            return [
                'ok' => $response->successful(),
                'label' => strtoupper($source).($response->successful() ? ' responde' : ' no disponible'),
                'detail' => $response->status().' · '.$url,
            ];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'label' => strtoupper($source).' no disponible', 'detail' => 'No se pudo consultar el servicio'];
        }
    }

    private function checkCounts(): array
    {
        try {
            $national = PostalPackage::where('scope', 'nacional')->count();
            $international = PostalPackage::where('scope', 'internacional')->count();
            $events = PostalEvent::count();
            return ['ok' => true, 'label' => 'Datos postales cargados', 'detail' => "Nacional: {$national} · Internacional: {$international} · Eventos: {$events}"];
        } catch (\Throwable $e) {
            report($e);
            return ['ok' => false, 'label' => 'Tablas postales con error', 'detail' => 'No se pudieron consultar los datos'];
        }
    }

    public function render()
    {
        return view('livewire.project-test-panel');
    }
}

<?php

namespace App\Services;

use App\Models\PostalPackage;
use Illuminate\Support\Facades\Http;

class CdsDeclarationSyncService
{
    public function sync(?array $requestedTrackings = null, ?string $scope = null): array
    {
        $trackings = $requestedTrackings ?: PostalPackage::query()
            ->when($scope, fn ($query) => $query->where('scope', $scope))
            ->whereNotNull('tracking_number')->whereRaw("trim(tracking_number) <> ''")
            ->pluck('tracking_number')->map(fn ($value) => strtoupper(trim($value)))->unique()->values()->all();
        if ($trackings === []) return ['registered'=>0,'received'=>0,'matched'=>0,'updated'=>0,'pages'=>0];

        $registered = array_fill_keys($trackings, true); $seen = []; $updated = 0; $matched = 0; $batches = 0;
        foreach (array_chunk($trackings, 100) as $batch) {
            $batches++;
            $response = Http::retry(2, 1500)->timeout((int) config('services.cds.timeout', 30))
                ->withOptions(['verify'=>(bool) config('services.cds.verify_ssl', false)])->acceptJson()
                ->withToken((string) config('services.cds.token'))->post(config('services.cds.url'), [
                    'codes' => array_values($batch),
                ]);
            if ($response->status() === 429) throw new \RuntimeException('CDS rechazó la consulta por límite de solicitudes. Intenta nuevamente más tarde.');
            $response->throw(); $payload = $response->json(); $items = $this->items($payload);
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $tracking = strtoupper(trim((string) ($item['codigo'] ?? '')));
                if ($tracking === '' || !isset($registered[$tracking]) || isset($seen[$tracking])) continue;
                $seen[$tracking] = true;
                $package = PostalPackage::query()
                    ->when($scope, fn ($query) => $query->where('scope', $scope))
                    ->whereRaw('upper(tracking_number) = ?', [$tracking])
                    ->first();
                if (!$package) continue; $matched++;
                $declaration = $this->declaration($item);
                $package->update([
                    'cds_sender'=>$this->value($declaration, ['remitente_declarado.nombre','campos.SNm']),
                    'cds_recipient'=>$this->value($declaration, ['destinatario_declarado.nombre','campos.RNm']),
                    'cds_phone'=>$this->value($declaration, ['destinatario_declarado.telefono','campos.RTel']),
                    'cds_synced_at'=>now(),
                ]); $updated++;
            }
            usleep(350000);
        }
        return ['registered'=>count($trackings),'received'=>count($seen),'matched'=>$matched,'updated'=>$updated,'batches'=>$batches];
    }

    private function items(mixed $payload): array
    {
        if (is_array($payload) && isset($payload['codigo'])) return [$payload];
        if (is_array($payload) && array_is_list($payload)) return $payload;
        $items = data_get($payload, 'data', []);
        if (is_array($items) && array_is_list($items)) return $items;
        return is_array($items) && isset($items['codigo']) ? [$items] : [];
    }

    private function lastPage(mixed $payload): ?int
    {
        foreach (['meta.last_page','pagination.last_page','last_page'] as $path) {
            $value = data_get($payload, $path); if (is_numeric($value)) return (int) $value;
        }
        return null;
    }

    private function declaration(array $item): array
    {
        $declaration = data_get($item, 'declaracion_cds.objetos.0.declaraciones.0', []);
        return is_array($declaration) ? $declaration : [];
    }

    private function value(array $data, array $paths): ?string
    {
        foreach ($paths as $path) { $value = data_get($data, $path); if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value); }
        return null;
    }
}

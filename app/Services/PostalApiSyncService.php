<?php
namespace App\Services;

use App\Models\PostalEvent;
use App\Models\PostalPackage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PostalApiSyncService
{
    public function sync(string $scope, int $months = 12, ?string $customFrom = null, ?string $customTo = null, bool $force = false): array
    {
        $isNational = $scope === 'nacional';
        $source = $isNational ? 'siop' : 'sitra';
        $items = [];
        $months = max(1, $months);
        // Cuatro meses desde el 27/10/2026 significa 01/07/2026 hasta hoy.
        $from = $customFrom ? Carbon::parse($customFrom)->startOfDay() : now()->startOfMonth()->subMonths($months - 1)->startOfDay();
        $to = $customTo ? Carbon::parse($customTo)->endOfDay() : now()->endOfDay();
        if ($from->gt($to)) throw new RuntimeException('La fecha desde no puede ser posterior a la fecha hasta.');
        if ($customFrom && $customTo && ! $force && $this->hasLocalCoverage($source, $from, $to)) {
            return [
                'source' => $source,
                'scope' => $scope,
                'created' => 0,
                'updated' => 0,
                'months' => $months,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'received' => 0,
                'skipped' => true,
            ];
        }
        $url = config($isNational ? 'services.postal.siop_url' : 'services.postal.sitra_url');
        $token = config($isNational ? 'services.postal.siop_token' : 'services.postal.sitra_token');
        $request = Http::timeout((int) config('services.postal.timeout', 90))->withOptions(['verify' => config('services.postal.verify_ssl', false)])->acceptJson();
        if ($token) $request = $request->withToken($token);
        // El proveedor puede ignorar los filtros de fecha; este límite evita
        // que una ejecución del scheduler quede bloqueada indefinidamente.
        // SITRA entrega 200 registros por página; 100 páginas permiten
        // recorrer hasta 20.000 registros para alcanzar el rango histórico.
        for ($page = 1; $page <= 100; $page++) {
            $payload = $request->retry(4, 5000)->get($url, [
                'page' => $page,
                'per_page' => 200,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'fecha_desde' => $from->toDateString(),
                'fecha_hasta' => $to->toDateString(),
            ])->throw()->json();
            $pageItems = data_get($payload, 'data', $payload);
            if (! is_array($pageItems)) throw new RuntimeException("La API {$source} no devolvio una lista de paquetes.");
            foreach ($pageItems as $pageItem) $items[] = $pageItem;
            if (! $this->hasNextPage($payload, $page, count($pageItems))) break;
            // SITRA aplica rate limiting cuando se consultan muchas páginas.
            if ($page < 100) usleep(350000);
        }
        if (! is_array($items)) throw new RuntimeException("La API {$source} no devolvio una lista de paquetes.");
        $created = $updated = 0;
        foreach ($items as $item) {
            if (! is_array($item)) continue;
            $tracking = (string) ($item['tracking_number'] ?? $item['codigo_s10'] ?? $item['codigo'] ?? $item['package_code'] ?? $item['numero_guia'] ?? '');
            $externalId = (string) ($item['id'] ?? $item['uuid'] ?? $tracking);
            if ($externalId === '') continue;
            $package = PostalPackage::firstOrNew(['source' => $source, 'external_id' => $externalId]);
            $new = ! $package->exists;
            $events = $item['eventos'] ?? $item['events'] ?? $item['movimientos'] ?? data_get($item, 'tracking.events', data_get($item, 'history', []));
            if (is_array($events) && isset($events['data']) && is_array($events['data'])) $events = $events['data'];
            $latest = $this->latestEvent($events);
            $updatedAt = $this->date($latest['date'] ?? ($item['updated_at'] ?? $item['fecha_actualizacion'] ?? null));
            // Las consultas manuales respetan estrictamente el rango. El
            // scheduler conserva la actualización de guías existentes aunque
            // el proveedor devuelva un evento fuera de las fechas solicitadas.
            if ($updatedAt && ($updatedAt->lt($from) || $updatedAt->gt($to))) {
                if ($new || ($customFrom && $customTo)) continue;
            }
            $phone = $this->firstValue($item, [
                'destinatario.telefono', 'destinatario.celular', 'destinatario.mobile',
                'destinatario.phone', 'destinatario.telefono_celular', 'destinatario.contacto.telefono',
                'destinatario.contacto.celular', 'contacto.telefono', 'contacto.celular', 'telefono',
                'celular', 'mobile', 'phone', 'telefono_destinatario', 'celular_destinatario',
            ]);
            $originPhone = $this->firstValue($item, ['remitente.telefono', 'remitente.celular', 'remitente.mobile', 'remitente.phone', 'remitente.contacto.telefono', 'remitente.contacto.celular', 'telefono_remitente', 'celular_remitente']);
            $package->fill(['scope' => $scope, 'tracking_number' => $tracking ?: $externalId, 'sender' => data_get($item, 'remitente.nombre', $item['sender'] ?? null), 'recipient' => data_get($item, 'destinatario.nombre', $item['recipient'] ?? null), 'origin_phone' => $originPhone, 'phone' => $phone, 'current_event' => $latest['name'] ?? ($item['estado_postal'] ?? $item['status'] ?? null), 'destination_branch' => data_get($item, 'destino.nombre', $item['destination_branch'] ?? null), 'updated_at_api' => $updatedAt, 'raw_payload' => $item, 'last_synced_at' => now()]);
            $package->save(); $new ? $created++ : $updated++;
            foreach ($events as $event) { if (! is_array($event)) continue; PostalEvent::updateOrCreate(['postal_package_id'=>$package->id,'source_event'=>(string)($event['evento_id']??$event['codigo_evento']??$event['event_code']??$event['code']??'unknown'),'event_at'=>$this->date($event['fecha']??$event['date']??null)], ['event_name'=>(string)($event['nombre']??$event['evento_api']??$event['event_name']??$event['status']??'Evento postal'),'raw_payload'=>$event]); }
        }
        return compact('source', 'scope', 'created', 'updated') + ['months' => $months, 'from' => $from->toDateString(), 'to' => $to->toDateString(), 'received' => count($items)];
    }
    private function hasLocalCoverage(string $source, Carbon $from, Carbon $to): bool
    {
        $dates = PostalPackage::query()
            ->where('source', $source)
            ->whereBetween('updated_at_api', [$from, $to])
            ->whereNotNull('updated_at_api')
            ->pluck('updated_at_api')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->flip();

        for ($date = $from->copy()->startOfDay(); $date->lte($to); $date->addDay()) {
            if (! $dates->has($date->toDateString())) return false;
        }

        return true;
    }
    private function hasNextPage(mixed $payload, int $page, int $pageCount): bool
    {
        $lastPage = data_get($payload, 'meta.last_page', data_get($payload, 'last_page'));
        if (is_numeric($lastPage)) return $page < (int) $lastPage;

        $links = data_get($payload, 'links');
        if (is_array($links) && array_key_exists('next', $links)) return $links['next'] !== null;
        if (is_array($payload) && array_key_exists('next_page_url', $payload)) return $payload['next_page_url'] !== null;

        return $pageCount >= 200;
    }
    private function latestEvent(mixed $events): array
    {
        if (!is_array($events) || count($events) === 0) return [];
        $events = array_values(array_filter($events, 'is_array'));
        usort($events, function (array $a, array $b): int {
            $dateA = $this->date($a['fecha'] ?? $a['date'] ?? $a['fecha_evento'] ?? $a['event_date'] ?? $a['created_at'] ?? null)?->timestamp ?? 0;
            $dateB = $this->date($b['fecha'] ?? $b['date'] ?? $b['fecha_evento'] ?? $b['event_date'] ?? $b['created_at'] ?? null)?->timestamp ?? 0;
            return $dateB <=> $dateA;
        });
        $event = $events[0] ?? [];
        return [
            'name' => $event['nombre'] ?? $event['evento_api'] ?? $event['event_name'] ?? $event['status'] ?? $event['descripcion'] ?? $event['description'] ?? null,
            'date' => $event['fecha'] ?? $event['date'] ?? $event['fecha_evento'] ?? $event['event_date'] ?? $event['created_at'] ?? null,
        ];
    }
    private function date(mixed $value): ?Carbon { return $value ? rescue(fn()=>Carbon::parse($value), null) : null; }
    private function firstValue(array $item, array $paths): ?string
    {
        foreach ($paths as $path) {
            $value = data_get($item, $path);
            if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value);
        }
        return null;
    }
}

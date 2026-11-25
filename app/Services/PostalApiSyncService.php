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
                'pages' => 0,
                'reported_last_page' => null,
                'skipped' => true,
            ];
        }
        $url = config($isNational ? 'services.postal.siop_url' : 'services.postal.sitra_url');
        $token = config($isNational ? 'services.postal.siop_token' : 'services.postal.sitra_token');
        $request = Http::timeout((int) config('services.postal.timeout', 90))->withOptions(['verify' => config('services.postal.verify_ssl', false)])->acceptJson();
        if ($token) $request = $request->withToken($token);
        $created = $updated = $received = $pagesFetched = 0;
        $reportedLastPage = null;
        $seenPageSignatures = [];
        $pageDelayMs = max(0, (int) config('services.postal.page_delay_ms', 350));

        for ($page = 1; ; $page++) {
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
            $signature = hash('sha256', serialize($pageItems));
            if (isset($seenPageSignatures[$signature])) {
                throw new RuntimeException("La API {$source} repitio los datos de la pagina {$page}; se detuvo la sincronizacion para evitar un bucle.");
            }
            $seenPageSignatures[$signature] = true;
            $pagesFetched++;
            $received += count($pageItems);

            foreach ($pageItems as $item) {
                if (! is_array($item)) continue;

                $result = $this->syncPackage($item, $source, $scope, $from, $to, $customFrom, $customTo);
                $created += $result === 'created' ? 1 : 0;
                $updated += $result === 'updated' ? 1 : 0;
            }

            $lastPage = data_get($payload, 'meta.last_page', data_get($payload, 'last_page'));
            if (is_numeric($lastPage)) {
                $reportedLastPage = (int) $lastPage;
            }
            if (! $this->hasNextPage($payload, $page, count($pageItems))) break;

            if ($pageDelayMs > 0) usleep($pageDelayMs * 1000);
        }

        return compact('source', 'scope', 'created', 'updated')
            + [
                'months' => $months,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'received' => $received,
                'pages' => $pagesFetched,
                'reported_last_page' => $reportedLastPage,
            ];
    }

    private function syncPackage(
        array $item,
        string $source,
        string $scope,
        Carbon $from,
        Carbon $to,
        ?string $customFrom,
        ?string $customTo,
    ): ?string {
        $tracking = (string) ($item['tracking_number'] ?? $item['codigo_s10'] ?? $item['codigo'] ?? $item['package_code'] ?? $item['numero_guia'] ?? '');
        $externalId = (string) ($item['id'] ?? $item['uuid'] ?? $tracking);
        if ($externalId === '') return null;

        $package = PostalPackage::firstOrNew(['source' => $source, 'external_id' => $externalId]);
        $new = ! $package->exists;
        $events = $item['eventos'] ?? $item['events'] ?? $item['movimientos'] ?? data_get($item, 'tracking.events', data_get($item, 'history', []));
        if (is_array($events) && isset($events['data']) && is_array($events['data'])) $events = $events['data'];
        $latest = $this->latestEvent($events);
        $updatedAt = $this->date($latest['date'] ?? ($item['updated_at'] ?? $item['fecha_actualizacion'] ?? null));
        // Manual syncs respect the requested date range; scheduled syncs still
        // refresh existing guides if the provider returns an older event.
        if ($updatedAt && ($updatedAt->lt($from) || $updatedAt->gt($to))) {
            if ($new || ($customFrom && $customTo)) return null;
        }

        $phone = $this->firstValue($item, [
            'destinatario.telefono', 'destinatario.celular', 'destinatario.mobile',
            'destinatario.phone', 'destinatario.telefono_celular', 'destinatario.contacto.telefono',
            'destinatario.contacto.celular', 'contacto.telefono', 'contacto.celular', 'telefono',
            'celular', 'mobile', 'phone', 'telefono_destinatario', 'celular_destinatario',
        ]);
        $originPhone = $this->firstValue($item, [
            'remitente.telefono', 'remitente.celular', 'remitente.mobile', 'remitente.phone',
            'remitente.contacto.telefono', 'remitente.contacto.celular',
            'telefono_remitente', 'celular_remitente',
        ]);
        $package->fill([
            'scope' => $scope,
            'tracking_number' => $tracking ?: $externalId,
            'sender' => data_get($item, 'remitente.nombre', $item['sender'] ?? null),
            'recipient' => data_get($item, 'destinatario.nombre', $item['recipient'] ?? null),
            'origin_phone' => $originPhone,
            'phone' => $phone,
            'current_event' => $latest['name'] ?? ($item['estado_postal'] ?? $item['status'] ?? null),
            'destination_branch' => $this->firstValue($item, [
                'destino.departamento',
                'destino.nombre_destino',
                'destino.nombre',
                'destino.ciudad',
                'destino',
                'destination_branch',
                'destination',
            ]),
            'updated_at_api' => $updatedAt,
            'raw_payload' => $item,
            'last_synced_at' => now(),
        ]);
        $package->save();
        foreach ($events as $event) {
            if (! is_array($event)) continue;
            PostalEvent::updateOrCreate(
                [
                    'postal_package_id' => $package->id,
                    'source_event' => (string) ($event['evento_id'] ?? $event['codigo_evento'] ?? $event['event_code'] ?? $event['code'] ?? 'unknown'),
                    'event_at' => $this->date($event['fecha'] ?? $event['date'] ?? null),
                ],
                [
                    'event_name' => (string) ($event['nombre'] ?? $event['evento_api'] ?? $event['event_name'] ?? $event['status'] ?? 'Evento postal'),
                    'raw_payload' => $event,
                ],
            );
        }

        return $new ? 'created' : 'updated';
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

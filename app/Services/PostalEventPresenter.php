<?php

namespace App\Services;

use App\Models\PostalEvent;
use App\Models\PostalPackage;
use Illuminate\Support\Str;

class PostalEventPresenter
{
    private const CATEGORY_NATIONAL_ORIGIN = 'Recibido Origen';

    private const CATEGORY_NATIONAL_TRANSIT = 'En Camino';

    private const CATEGORY_NATIONAL_DESTINATION = 'Recibido Destino';

    private const CATEGORY_NATIONAL_COURIER = 'Cartero';

    private const CATEGORY_NATIONAL_DELIVERED = 'Entregado';

    private const CATEGORY_NATIONAL_CANCELLED = 'Cancelado';

    private const CATEGORY_INTERNATIONAL_ORIGIN = 'Origen y llegada';

    private const CATEGORY_INTERNATIONAL_DISTRIBUTION = 'Distribución nacional';

    private const CATEGORY_INTERNATIONAL_CUSTOMS = 'Aduana';

    private const CATEGORY_INTERNATIONAL_LAST_MILE = 'Última milla';

    private const CATEGORY_INTERNATIONAL_DELIVERED = 'Entregado';

    private const CATEGORY_INTERNATIONAL_IMPORT_STOPPED = 'Importación detenida';

    private const CATEGORY_UNCLASSIFIED = 'Sin movimientos o sin clasificación confiable';

    private const CATEGORY_PICKUP = 'Ventanilla';

    private const NATIONAL_CATEGORIES = [
        self::CATEGORY_NATIONAL_ORIGIN,
        self::CATEGORY_NATIONAL_TRANSIT,
        self::CATEGORY_NATIONAL_DESTINATION,
        self::CATEGORY_NATIONAL_COURIER,
        self::CATEGORY_PICKUP,
        self::CATEGORY_NATIONAL_DELIVERED,
        self::CATEGORY_NATIONAL_CANCELLED,
        self::CATEGORY_UNCLASSIFIED,
    ];

    private const INTERNATIONAL_CATEGORIES = [
        self::CATEGORY_INTERNATIONAL_ORIGIN,
        self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
        self::CATEGORY_INTERNATIONAL_CUSTOMS,
        self::CATEGORY_INTERNATIONAL_LAST_MILE,
        self::CATEGORY_PICKUP,
        self::CATEGORY_INTERNATIONAL_DELIVERED,
        self::CATEGORY_INTERNATIONAL_IMPORT_STOPPED,
        self::CATEGORY_UNCLASSIFIED,
    ];

    private const EVENT_CATEGORIES = [
        'nacional' => [
            '168' => self::CATEGORY_NATIONAL_TRANSIT,
            '172' => self::CATEGORY_UNCLASSIFIED,
            '173' => self::CATEGORY_UNCLASSIFIED,
            '183' => self::CATEGORY_NATIONAL_DESTINATION,
            '2103' => self::CATEGORY_NATIONAL_DESTINATION,
            '2238' => self::CATEGORY_UNCLASSIFIED,
            '2239' => self::CATEGORY_NATIONAL_TRANSIT,
            '2240' => self::CATEGORY_NATIONAL_TRANSIT,
            '2241' => self::CATEGORY_NATIONAL_TRANSIT,
            '2242' => self::CATEGORY_NATIONAL_ORIGIN,
            '240' => self::CATEGORY_NATIONAL_TRANSIT,
            '295' => self::CATEGORY_NATIONAL_ORIGIN,
            '296' => self::CATEGORY_NATIONAL_TRANSIT,
            '297' => self::CATEGORY_NATIONAL_ORIGIN,
            '310' => self::CATEGORY_NATIONAL_DESTINATION,
            '312' => self::CATEGORY_NATIONAL_DESTINATION,
            '315' => self::CATEGORY_NATIONAL_COURIER,
            '316' => self::CATEGORY_NATIONAL_DELIVERED,
            '318' => self::CATEGORY_NATIONAL_ORIGIN,
            '3345' => self::CATEGORY_NATIONAL_CANCELLED,
            '4400' => self::CATEGORY_NATIONAL_TRANSIT,
            '4401' => self::CATEGORY_NATIONAL_TRANSIT,
            '4402' => self::CATEGORY_NATIONAL_CANCELLED,
            '4403' => self::CATEGORY_UNCLASSIFIED,
            '4404' => self::CATEGORY_NATIONAL_CANCELLED,
            '4405' => self::CATEGORY_NATIONAL_CANCELLED,
            '4407' => self::CATEGORY_UNCLASSIFIED,
            '4408' => self::CATEGORY_UNCLASSIFIED,
            '4409' => self::CATEGORY_NATIONAL_COURIER,
            '4410' => self::CATEGORY_NATIONAL_DESTINATION,
            '4468' => self::CATEGORY_UNCLASSIFIED,
            '4469' => self::CATEGORY_UNCLASSIFIED,
            '4470' => self::CATEGORY_NATIONAL_CANCELLED,
            '4471' => self::CATEGORY_UNCLASSIFIED,
            '4472' => self::CATEGORY_NATIONAL_CANCELLED,
            '4473' => self::CATEGORY_NATIONAL_CANCELLED,
            '4475' => self::CATEGORY_UNCLASSIFIED,
            '4476' => self::CATEGORY_UNCLASSIFIED,
            '4477' => self::CATEGORY_UNCLASSIFIED,
            '4478' => self::CATEGORY_UNCLASSIFIED,
            '4479' => self::CATEGORY_UNCLASSIFIED,
            '4481' => self::CATEGORY_NATIONAL_CANCELLED,
        ],
        'internacional' => [
            '1' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '2' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '3' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '8' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '9' => self::CATEGORY_UNCLASSIFIED,
            '10' => self::CATEGORY_UNCLASSIFIED,
            '13' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '14' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '30' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '31' => self::CATEGORY_INTERNATIONAL_CUSTOMS,
            '32' => self::CATEGORY_INTERNATIONAL_LAST_MILE,
            '34' => self::CATEGORY_INTERNATIONAL_CUSTOMS,
            '35' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '36' => self::CATEGORY_INTERNATIONAL_LAST_MILE,
            '37' => self::CATEGORY_INTERNATIONAL_DELIVERED,
            '38' => self::CATEGORY_INTERNATIONAL_IMPORT_STOPPED,
            '39' => self::CATEGORY_UNCLASSIFIED,
            '60' => self::CATEGORY_UNCLASSIFIED,
            '61' => self::CATEGORY_UNCLASSIFIED,
            '73' => self::CATEGORY_UNCLASSIFIED,
            '100' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '101' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '102' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '105' => self::CATEGORY_UNCLASSIFIED,
            '1251' => self::CATEGORY_INTERNATIONAL_CUSTOMS,
            '1252' => self::CATEGORY_UNCLASSIFIED,
            '130' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '131' => self::CATEGORY_INTERNATIONAL_ORIGIN,
            '132' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '133' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '160' => self::CATEGORY_UNCLASSIFIED,
            '600' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '601' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '602' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '700' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '701' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
            '702' => self::CATEGORY_INTERNATIONAL_DISTRIBUTION,
        ],
    ];

    private const NATIONAL_TRANSIT_EVENT_CODES = ['168', '2239', '2240', '2241', '240', '296', '4400', '4401'];

    private const CATEGORY_DESCRIPTIONS = [
        self::CATEGORY_NATIONAL_ORIGIN => 'El paquete fue recibido en la oficina de origen y continuará su despacho.',
        self::CATEGORY_NATIONAL_TRANSIT => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
        self::CATEGORY_NATIONAL_DESTINATION => 'El paquete llegó a la oficina o destino para entrega o recojo, según el evento.',
        self::CATEGORY_NATIONAL_COURIER => 'El paquete fue asignado a reparto o hubo un intento de entrega.',
        self::CATEGORY_NATIONAL_DELIVERED => 'El sistema registra que el paquete fue entregado.',
        self::CATEGORY_NATIONAL_CANCELLED => 'El paquete fue cancelado o devuelto, según el registro.',
        self::CATEGORY_INTERNATIONAL_ORIGIN => 'El envío está en proceso postal internacional desde origen o en una oficina de cambio.',
        self::CATEGORY_INTERNATIONAL_DISTRIBUTION => 'El envío continúa su distribución dentro del país de destino.',
        self::CATEGORY_INTERNATIONAL_CUSTOMS => 'El envío está en proceso, revisión u observación aduanera.',
        self::CATEGORY_INTERNATIONAL_LAST_MILE => 'El envío llegó a la oficina de entrega o tuvo un intento de entrega.',
        self::CATEGORY_INTERNATIONAL_DELIVERED => 'El sistema registra que el envío fue entregado.',
        self::CATEGORY_INTERNATIONAL_IMPORT_STOPPED => 'El envío fue devuelto desde Aduana o la importación está detenida.',
        self::CATEGORY_UNCLASSIFIED => 'El detalle disponible no permite clasificar este movimiento con confianza.',
    ];

    private const LABELS = [
        'nacional' => [
            '168' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '2239' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '2240' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '2241' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '2242' => 'El paquete fue recibido en la oficina de origen y continuará su despacho.',
            '240' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '295' => 'Correos recibió el paquete.',
            '296' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '297' => 'El paquete fue recibido en la oficina de origen y continuará su despacho.',
            '310' => 'El paquete llegó a la oficina o regional de destino.',
            '312' => 'Tu paquete ya llegó a la oficina de entrega o destino y está listo para entregar o recoger, según indique el sistema.',
            '315' => 'Se intentó entregar el paquete, pero no se pudo completar la entrega.',
            '316' => 'El sistema marca el paquete como entregado.',
            '318' => 'El paquete fue registrado en la oficina donde se dejó y continuará su despacho.',
            '3345' => 'El registro indica que el envío fue cancelado.',
            '4402' => 'El sistema registró una cancelación o devolución del envío.',
            '4404' => 'El sistema registró una cancelación o devolución del envío.',
            '4405' => 'El sistema registró una cancelación o devolución del envío.',
            '4400' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '4401' => 'El paquete sigue en tránsito entre sucursales o centros de distribución.',
            '4409' => 'El paquete fue asignado a un cartero para intentar la entrega.',
            '4410' => 'El paquete llegó a la oficina o regional de destino.',
            '183' => 'El paquete llegó a la oficina o regional de destino.',
            '2103' => 'El paquete llegó a la oficina o regional de destino.',
        ],
        'internacional' => [
            '1' => 'Correos recibió el envío para iniciar su traslado internacional.',
            '2' => 'El envío continúa su distribución dentro del país de destino.',
            '13' => 'El envío continúa su distribución dentro del país de destino.',
            '14' => 'El envío continúa su distribución dentro del país de destino.',
            '35' => 'El envío continúa su distribución dentro del país de destino.',
            '132' => 'El envío continúa su distribución dentro del país de destino.',
            '133' => 'El envío continúa su distribución dentro del país de destino.',
            '3' => 'El envío llegó a la oficina postal internacional de salida.',
            '8' => 'El envío fue gestionado en origen para continuar su traslado internacional.',
            '100' => 'El envío fue gestionado en origen para continuar su traslado internacional.',
            '101' => 'El envío fue gestionado en origen para continuar su traslado internacional.',
            '102' => 'El envío fue gestionado en origen para continuar su traslado internacional.',
            '130' => 'El envío fue gestionado en origen para continuar su traslado internacional.',
            '131' => 'El envío fue gestionado en origen para continuar su traslado internacional.',
            '600' => 'El envío continúa su distribución dentro del país de destino.',
            '601' => 'El envío continúa su distribución dentro del país de destino.',
            '602' => 'El envío continúa su distribución dentro del país de destino.',
            '700' => 'El envío continúa su distribución dentro del país de destino.',
            '701' => 'El envío continúa su distribución dentro del país de destino.',
            '702' => 'El envío continúa su distribución dentro del país de destino.',
            '30' => 'El envío llegó al centro postal internacional del país de destino.',
            '31' => 'El envío fue enviado a revisión de Aduana.',
            '32' => 'El envío llegó a una oficina postal de entrega.',
            '34' => 'Aduana registró información relacionada con el envío.',
            '36' => 'Se intentó entregar el envío, pero no se pudo completar la entrega.',
            '37' => 'El sistema marca el envío como entregado en el país de destino.',
            '38' => 'El envío fue devuelto desde Aduana hacia el país de origen.',
            '1251' => 'Aduana registró una observación sobre el envío; el sistema no indica el motivo.',
        ],
    ];

    public function currentStatus(PostalPackage $package): ?string
    {
        $event = $this->latestEvent($package);
        if ($event && $this->hasEventCode($event->source_event)) {
            if ($package->scope === 'nacional' && trim((string) $event->source_event) === '297') {
                $category = $this->currentCategory($package) ?? self::CATEGORY_UNCLASSIFIED;

                return self::CATEGORY_DESCRIPTIONS[$category];
            }

            return $this->describe($package->scope, $event->source_event, $event->event_name);
        }

        $status = trim((string) $package->current_event);
        if ($status !== '' && ! $this->isEmptyStatus($status)) {
            return $this->describe($package->scope, null, $status);
        }

        return $event ? $this->describe($package->scope, $event->source_event, $event->event_name) : null;
    }

    public function packageStatus(string $status): string
    {
        return str_ireplace(
            ['envíos', 'envío', 'envios', 'envio'],
            ['paquetes', 'paquete', 'paquetes', 'paquete'],
            $status,
        );
    }

    public function currentCategory(PostalPackage $package): ?string
    {
        $event = $this->latestEvent($package);
        if ($event && $this->hasEventCode($event->source_event)) {
            return $this->categoryForEvent($package, $event);
        }

        $status = trim((string) $package->current_event);
        if ($status !== '' && ! $this->isEmptyStatus($status)) {
            return $this->category($package->scope, null, $status);
        }

        return $event ? $this->category($package->scope, $event->source_event, $event->event_name) : null;
    }

    public function categories(string $scope): array
    {
        return $scope === 'nacional' ? self::NATIONAL_CATEGORIES : self::INTERNATIONAL_CATEGORIES;
    }

    public function categoryCodes(string $scope, string $category): array
    {
        return array_keys(array_filter(
            self::EVENT_CATEGORIES[$scope] ?? [],
            fn (string $eventCategory, string $code): bool => $eventCategory === $category
                && ! ($scope === 'nacional' && $code === '297'),
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    public function knownEventCodes(string $scope): array
    {
        return array_keys(self::EVENT_CATEGORIES[$scope] ?? []);
    }

    public function ventanillaEventCodes(string $scope): array
    {
        return match ($scope) {
            'nacional' => ['312'],
            'internacional' => ['32'],
            default => [],
        };
    }

    public function describeEvent(string $scope, PostalEvent $event): string
    {
        return $this->describe($scope, $event->source_event, $event->event_name);
    }

    public function categoryForEvent(PostalPackage $package, PostalEvent $event): string
    {
        $category = $this->category($package->scope, $event->source_event, $event->event_name);
        if (
            $package->scope === 'nacional'
            && trim((string) $event->source_event) === '297'
            && $category === self::CATEGORY_NATIONAL_ORIGIN
            && $this->hasPriorNationalTransit($package, $event)
        ) {
            return self::CATEGORY_NATIONAL_DESTINATION;
        }

        return $category;
    }

    public function category(string $scope, ?string $sourceEvent, ?string $description): string
    {
        $code = trim((string) $sourceEvent);
        if ($this->hasEventCode($code)) {
            if ($scope === 'nacional' && $code === '297') {
                return $this->national297Category($description);
            }

            return self::EVENT_CATEGORIES[$scope][$code] ?? self::CATEGORY_UNCLASSIFIED;
        }

        return $this->categoryFromDescription($scope, $description);
    }

    public function describe(string $scope, ?string $sourceEvent, ?string $description): string
    {
        $code = trim((string) $sourceEvent);
        if ($this->hasEventCode($code)) {
            if ($scope === 'nacional' && $code === '297') {
                $category = $this->national297Category($description);

                return $category === self::CATEGORY_NATIONAL_ORIGIN
                    ? (self::LABELS[$scope][$code] ?? self::CATEGORY_DESCRIPTIONS[$category])
                    : self::CATEGORY_DESCRIPTIONS[$category];
            }

            $category = self::EVENT_CATEGORIES[$scope][$code] ?? self::CATEGORY_UNCLASSIFIED;
            if ($category === self::CATEGORY_UNCLASSIFIED) {
                return self::CATEGORY_DESCRIPTIONS[$category];
            }

            if (isset(self::LABELS[$scope][$code])) {
                return self::LABELS[$scope][$code];
            }

            return self::CATEGORY_DESCRIPTIONS[$category];
        }

        if (isset(self::LABELS[$scope][$code])) {
            return self::LABELS[$scope][$code];
        }

        $category = $this->categoryFromDescription($scope, $description);
        if ($category !== self::CATEGORY_UNCLASSIFIED) {
            return self::CATEGORY_DESCRIPTIONS[$category];
        }

        return self::CATEGORY_DESCRIPTIONS[self::CATEGORY_UNCLASSIFIED];
    }

    private function categoryFromDescription(string $scope, ?string $description): string
    {
        $normalized = Str::ascii(mb_strtolower(trim((string) $description)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if ($normalized === '' || $this->isEmptyStatus($normalized)) {
            return self::CATEGORY_UNCLASSIFIED;
        }
        $delivered = preg_match('/\b(entregado|entregada|delivered)\b/u', $normalized) === 1;
        $cancelled = str_contains($normalized, 'cancelad') || str_contains($normalized, 'anulad');
        $returned = str_contains($normalized, 'devolv') || str_contains($normalized, 'devuelt') || str_contains($normalized, 'retorno');
        $failedDelivery = (
            (str_contains($normalized, 'intent') && (str_contains($normalized, 'fallid') || str_contains($normalized, 'no se pudo')))
            || str_contains($normalized, 'no entreg')
            || (str_contains($normalized, 'fallid') && str_contains($normalized, 'entreg'))
            || str_contains($normalized, 'delivery failed')
        );
        $destinationOffice = str_contains($normalized, 'oficina de destino')
            || str_contains($normalized, 'oficina de entrega')
            || str_contains($normalized, 'delivery office')
            || str_contains($normalized, 'ventanilla')
            || str_contains($normalized, 'recojo')
            || str_contains($normalized, 'retiro')
            || str_contains($normalized, 'pickup');
        $courier = str_contains($normalized, 'cartero')
            || str_contains($normalized, 'en reparto')
            || str_contains($normalized, 'entrega fisica')
            || (str_contains($normalized, 'asignad') && str_contains($normalized, 'reparto'));

        if ($scope === 'nacional') {
            if ($delivered) {
                return self::CATEGORY_NATIONAL_DELIVERED;
            }
            if ($cancelled || $returned) {
                return self::CATEGORY_NATIONAL_CANCELLED;
            }
            if ($failedDelivery || $courier) {
                return self::CATEGORY_NATIONAL_COURIER;
            }
            if ($destinationOffice || str_contains($normalized, 'destino')) {
                return self::CATEGORY_NATIONAL_DESTINATION;
            }
            if (str_contains($normalized, 'transito') || str_contains($normalized, 'transit') || str_contains($normalized, 'en camino') || str_contains($normalized, 'saca') || str_contains($normalized, 'despacho')) {
                return self::CATEGORY_NATIONAL_TRANSIT;
            }
            if (str_contains($normalized, 'recibid') || str_contains($normalized, 'received') || str_contains($normalized, 'origen')) {
                return self::CATEGORY_NATIONAL_ORIGIN;
            }

            return self::CATEGORY_UNCLASSIFIED;
        }

        if ($delivered) {
            return self::CATEGORY_INTERNATIONAL_DELIVERED;
        }
        if (($returned && (str_contains($normalized, 'aduana') || str_contains($normalized, 'customs') || str_contains($normalized, 'import'))) || str_contains($normalized, 'importacion detenida')) {
            return self::CATEGORY_INTERNATIONAL_IMPORT_STOPPED;
        }
        if (
            str_contains($normalized, 'aduana')
            || str_contains($normalized, 'customs')
            || str_contains($normalized, 'revision')
            || str_contains($normalized, 'observacion')
        ) {
            return self::CATEGORY_INTERNATIONAL_CUSTOMS;
        }
        if ($failedDelivery || $destinationOffice || $courier) {
            return self::CATEGORY_INTERNATIONAL_LAST_MILE;
        }
        if (str_contains($normalized, 'distribucion') || str_contains($normalized, 'transito') || str_contains($normalized, 'transit') || str_contains($normalized, 'en camino')) {
            return self::CATEGORY_INTERNATIONAL_DISTRIBUTION;
        }
        if (str_contains($normalized, 'origen') || str_contains($normalized, 'recibid') || str_contains($normalized, 'received') || str_contains($normalized, 'oficina internacional')) {
            return self::CATEGORY_INTERNATIONAL_ORIGIN;
        }

        return self::CATEGORY_UNCLASSIFIED;
    }

    private function national297Category(?string $description): string
    {
        $normalized = Str::ascii(mb_strtolower(trim((string) $description)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        if (
            str_contains($normalized, 'destino')
            || str_contains($normalized, 'oficina de entrega')
            || str_contains($normalized, 'ventanilla')
            || str_contains($normalized, 'recojo')
            || str_contains($normalized, 'retiro')
        ) {
            return self::CATEGORY_NATIONAL_DESTINATION;
        }
        if (str_contains($normalized, 'transito') || str_contains($normalized, 'transit') || str_contains($normalized, 'en camino')) {
            return self::CATEGORY_NATIONAL_TRANSIT;
        }

        return self::CATEGORY_NATIONAL_ORIGIN;
    }

    private function hasPriorNationalTransit(PostalPackage $package, PostalEvent $latestEvent): bool
    {
        if ($package->relationLoaded('events')) {
            return $package->events->contains(function (PostalEvent $event) use ($latestEvent): bool {
                if (! in_array(trim((string) $event->source_event), self::NATIONAL_TRANSIT_EVENT_CODES, true)) {
                    return false;
                }

                $eventTimestamp = $event->event_at?->getTimestamp() ?? PHP_INT_MIN;
                $latestTimestamp = $latestEvent->event_at?->getTimestamp() ?? PHP_INT_MIN;

                return $eventTimestamp < $latestTimestamp
                    || ($eventTimestamp === $latestTimestamp && ($event->id ?? 0) < ($latestEvent->id ?? 0));
            });
        }

        return PostalEvent::query()
            ->where('postal_package_id', $package->id)
            ->whereIn('source_event', self::NATIONAL_TRANSIT_EVENT_CODES)
            ->exists();
    }

    private function latestEvent(PostalPackage $package): ?PostalEvent
    {
        if ($package->relationLoaded('events')) {
            return $package->events
                ->sort(function (PostalEvent $first, PostalEvent $second): int {
                    $firstTimestamp = $first->event_at?->getTimestamp() ?? PHP_INT_MIN;
                    $secondTimestamp = $second->event_at?->getTimestamp() ?? PHP_INT_MIN;

                    return ($secondTimestamp <=> $firstTimestamp) ?: (($second->id ?? 0) <=> ($first->id ?? 0));
                })
                ->first();
        }

        if ($package->relationLoaded('latestEvent')) {
            return $package->getRelation('latestEvent');
        }

        return $package->latestEvent;
    }

    public function isEmptyStatus(string $status): bool
    {
        return in_array(Str::ascii(mb_strtolower(trim($status))), ['', 'sin evento', 'sin eventos', 'no event'], true);
    }

    private function hasEventCode(?string $sourceEvent): bool
    {
        $code = trim((string) $sourceEvent);

        return $code !== '' && mb_strtolower($code) !== 'unknown';
    }
}

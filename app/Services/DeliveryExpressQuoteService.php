<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DeliveryExpressQuoteService
{
    public function quote(float $weight, string $destination, ?string $mode = null): array
    {
        if ($weight <= 0 || $weight > 5) {
            throw new RuntimeException('Delivery Express solo admite paquetes de hasta 5 kg.');
        }

        $response = Http::timeout((int) config('services.delivery_express.timeout', 15))
            ->acceptJson()
            ->withOptions(['verify' => (bool) config('services.delivery_express.verify_ssl', false)])
            ->withToken((string) config('services.delivery_express.token'))
            ->get(config('services.delivery_express.url'));

        if ($response->failed()) {
            throw new RuntimeException('El tarifario de Delivery Express no respondió.');
        }

        $payload = $response->json();
        $rates = $this->extractRates($payload);
        if (!is_array($rates)) throw new RuntimeException('El tarifario devolvió un formato inválido.');
        if (array_is_list($rates) === false) $rates = [$rates];

        $rate = collect($rates)->first(function ($item) use ($weight, $destination, $mode) {
            $min = (float) ($item['min_weight_kg'] ?? $item['peso_minimo'] ?? 0);
            $max = (float) ($item['max_weight_kg'] ?? $item['peso_maximo'] ?? 999999);
            $origin = mb_strtolower((string) data_get($item, 'origen.nombre_origen', $item['origen'] ?? ''), 'UTF-8');
            $target = mb_strtolower((string) data_get($item, 'destino.nombre_destino', $item['destino'] ?? ''), 'UTF-8');
            $service = mb_strtolower((string) data_get($item, 'servicio_extra.nombre', $item['servicio_extra'] ?? ''), 'UTF-8');
            $destinationName = str_replace('_', ' ', preg_replace('/^nacional_/', '', $destination));
            $text = mb_strtolower(json_encode($item, JSON_UNESCAPED_UNICODE));
            return $weight >= $min && $weight <= $max
                && (!$destination || str_contains($target, $destinationName) || str_contains($text, str_replace('_', ' ', $destination)))
                && (!$mode || str_contains($service, mb_strtolower(str_replace('_', ' ', $mode))));
        });
        if (!$rate) throw new RuntimeException('No hay una tarifa activa para esos datos.');

        // Delivery Express entrega el precio del paquete en peso1. Los campos
        // peso2, peso3 y peso_extra no participan en esta cotización.
        $price = $rate['peso1'] ?? null;
        if (!is_numeric($price)) throw new RuntimeException('La tarifa no contiene un precio válido.');
        return ['service' => 'Delivery Express', 'price' => (float) $price,
            'currency' => $rate['currency'] ?? $rate['moneda'] ?? 'BOB', 'rate' => $rate];
    }

    private function extractRates(mixed $payload): array
    {
        if (!is_array($payload)) return [];
        if (isset($payload['peso1'])) return [$payload];
        $result = [];
        foreach ($payload as $value) {
            if (is_array($value)) $result = [...$result, ...$this->extractRates($value)];
        }
        return $result;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente de la calculadora EMS de Postar. */
class PostarQuoteService
{
    private const INTERNATIONAL_DESTINATION_CODES = [
        'argentina' => 'dest_a_argentina',
        'brasil' => 'dest_a_brasil',
        'brazil' => 'dest_a_brasil',
        'chile' => 'dest_a_chile',
        'colombia' => 'dest_a_colombia',
        'ecuador' => 'dest_a_ecuador',
        'paraguay' => 'dest_a_paraguay',
        'peru' => 'dest_a_peru',
        'uruguay' => 'dest_a_uruguay',
        'venezuela' => 'dest_a_venezuela',
        'bolivia' => 'dest_a_bolivia',
    ];
    private const DESTINATIONS = [
        'beni' => 'nacional_beni', 'trinidad' => 'nacional_beni', 'benni' => 'nacional_beni',
        'chuquisaca' => 'nacional_chuquisaca', 'sucre' => 'nacional_chuquisaca',
        'cochabamba' => 'nacional_cochabamba', 'cbba' => 'nacional_cochabamba', 'cocha' => 'nacional_cochabamba',
        'la paz' => 'nacional_la_paz', 'lapaz' => 'nacional_la_paz', 'la pas' => 'nacional_la_paz', 'el alto' => 'nacional_la_paz',
        'oruro' => 'nacional_oruro', 'oruroo' => 'nacional_oruro', 'pando' => 'nacional_pando', 'cobija' => 'nacional_pando',
        'potosi' => 'nacional_potosi', 'potosí' => 'nacional_potosi', 'santa cruz' => 'nacional_santa_cruz', 'santacruz' => 'nacional_santa_cruz', 'santa crus' => 'nacional_santa_cruz',
        'tarija' => 'nacional_tarija', 'tariga' => 'nacional_tarija',
    ];

    public function quote(string $category, string $destination, float $weight, ?array $dimensions = null): array
    {
        $category = strtoupper(trim($category));
        if (!in_array($category, ['EMS NAT', 'EMS INT'], true)) {
            throw new RuntimeException('La calculadora de Postar solo admite EMS NAT o EMS INT.');
        }
        if ($weight <= 0 || $weight > 999999) {
            throw new RuntimeException('El peso debe ser mayor que cero.');
        }

        $apiDestination = $this->apiDestination($category, $destination);
        $body = ['categoria' => $category, 'destino' => $apiDestination, 'peso' => $weight];
        if ($dimensions) {
            foreach (['largo', 'ancho', 'alto'] as $dimension) {
                if (!isset($dimensions[$dimension]) || (float) $dimensions[$dimension] <= 0) {
                    throw new RuntimeException('Las dimensiones deben ser positivas en centímetros.');
                }
                $body[$dimension] = (float) $dimensions[$dimension];
            }
        }

        $response = Http::timeout((int) config('services.postar.timeout', 15))
            ->acceptJson()
            ->withOptions(['verify' => filter_var(config('services.postar.verify_ssl', false), FILTER_VALIDATE_BOOLEAN)])
            ->post(config('services.postar.url', 'https://postar.correos.gob.bo/api/calcular'), $body);

        if ($response->failed()) {
            $detail = trim((string) data_get($response->json(), 'message', data_get($response->json(), 'error', '')));
            if ($detail === '') {
                $detail = trim($response->body());
            }
            throw new RuntimeException('Postar no pudo calcular la tarifa (' . $response->status() . ')' . ($detail !== '' ? ': ' . $detail : '.') );
        }
        $payload = $response->json();
        if (!is_array($payload)) {
            throw new RuntimeException('El endpoint de Postar respondió HTML en lugar de JSON. La URL configurada no es el endpoint de cálculo de la API.');
        }
        $tariff = data_get($payload, 'tarifa');
        if (data_get($payload, 'success') !== true || !is_numeric($tariff)) {
            throw new RuntimeException('Postar no devolvió una tarifa válida.');
        }

        return ['category' => $category, 'destination' => $destination, 'weight' => $weight,
            'tariff' => (float) $tariff, 'currency' => 'BOB',
            'registration_id' => data_get($payload, 'registro_id'),
            'session_id' => data_get($payload, 'sesion_consulta')];
    }

    private function apiDestination(string $category, string $destination): string
    {
        if ($category !== 'EMS INT') {
            return trim($destination);
        }

        $normalized = mb_strtolower(trim($destination), 'UTF-8');
        $normalized = strtr($normalized, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);

        return self::INTERNATIONAL_DESTINATION_CODES[$normalized] ?? trim($destination);
    }

    public function destinationCode(string $place): ?string
    {
        $key = strtr(mb_strtolower(trim($place), 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
        return self::DESTINATIONS[$key] ?? null;
    }

    public function destinationCodeFromText(string $text): ?string
    {
        $codes = $this->destinationCodesFromText($text);

        return $codes === [] ? null : $codes[array_key_last($codes)];
    }

    public function destinationCodesFromText(string $text): array
    {
        return array_values(array_unique(array_column($this->destinationMentions($text), 'code')));
    }

    public function routeFromText(string $text): ?array
    {
        $mentions = $this->destinationMentions($text);
        if (count(array_unique(array_column($mentions, 'code'))) < 2) {
            return null;
        }

        $normalized = $this->normalizeDestinationText($text);
        $fromPosition = strrpos($normalized, 'desde');
        if ($fromPosition !== false) {
            $beforeFrom = array_values(array_filter(
                $mentions,
                fn (array $mention): bool => $mention['position'] < $fromPosition,
            ));
            $afterFrom = array_values(array_filter(
                $mentions,
                fn (array $mention): bool => $mention['position'] > $fromPosition,
            ));

            if (count($afterFrom) >= 2) {
                return [
                    'origin' => $afterFrom[0]['code'],
                    'destination' => $afterFrom[array_key_last($afterFrom)]['code'],
                ];
            }

            if ($beforeFrom !== [] && $afterFrom !== []) {
                $destination = $beforeFrom[array_key_last($beforeFrom)];
                $prefix = substr($normalized, 0, $destination['position']);
                if (preg_match('/\b(?:a|hasta|hacia)\s*$/u', $prefix) === 1) {
                    return [
                        'origin' => $afterFrom[0]['code'],
                        'destination' => $destination['code'],
                    ];
                }
            }
        }

        return [
            'origin' => $mentions[0]['code'],
            'destination' => $mentions[array_key_last($mentions)]['code'],
        ];
    }

    private function destinationMentions(string $text): array
    {
        $normalized = $this->normalizeDestinationText($text);
        $matches = [];
        foreach (self::DESTINATIONS as $alias => $code) {
            preg_match_all('/(?<![a-z])' . preg_quote($alias, '/') . '(?![a-z])/u', $normalized, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] ?? [] as $match) {
                $matches[] = [
                    'position' => $match[1],
                    'end' => $match[1] + strlen($match[0]),
                    'length' => strlen($match[0]),
                    'code' => $code,
                ];
            }
        }
        usort($matches, fn (array $first, array $second): int => $first['position'] <=> $second['position']
            ?: $second['length'] <=> $first['length']);

        $mentions = [];
        foreach ($matches as $match) {
            $previous = $mentions[array_key_last($mentions)] ?? null;
            if ($previous !== null && $match['position'] < $previous['end']) {
                continue;
            }

            $mentions[] = $match;
        }

        return $mentions;
    }

    private function normalizeDestinationText(string $text): string
    {
        $normalized = mb_strtolower($text, 'UTF-8');

        return strtr($normalized, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
    }
}

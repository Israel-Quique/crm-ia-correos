<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente de la calculadora EMS de Postar. */
class PostarQuoteService
{
    private const DESTINATIONS = [
        'beni' => 'nacional_beni', 'trinidad' => 'nacional_beni',
        'chuquisaca' => 'nacional_chuquisaca', 'sucre' => 'nacional_chuquisaca',
        'cochabamba' => 'nacional_cochabamba', 'cbba' => 'nacional_cochabamba', 'cocha' => 'nacional_cochabamba',
        'la paz' => 'nacional_la_paz', 'lapaz' => 'nacional_la_paz', 'el alto' => 'nacional_la_paz',
        'oruro' => 'nacional_oruro', 'pando' => 'nacional_pando', 'cobija' => 'nacional_pando',
        'potosi' => 'nacional_potosi', 'santa cruz' => 'nacional_santa_cruz', 'santacruz' => 'nacional_santa_cruz',
        'tarija' => 'nacional_tarija',
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

        $body = ['categoria' => $category, 'destino' => trim($destination), 'peso' => $weight];
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
            ->withOptions(['verify' => (bool) config('services.postar.verify_ssl', false)])
            ->post(config('services.postar.url', 'https://postar.correos.gob.bo/api/calcular'), $body);

        if ($response->failed()) {
            throw new RuntimeException('Postar no pudo calcular la tarifa (' . $response->status() . ').');
        }
        $payload = $response->json();
        $tariff = data_get($payload, 'tarifa');
        if (data_get($payload, 'success') !== true || !is_numeric($tariff)) {
            throw new RuntimeException('Postar no devolvió una tarifa válida.');
        }

        return ['category' => $category, 'destination' => $destination, 'weight' => $weight,
            'tariff' => (float) $tariff, 'currency' => 'BOB',
            'registration_id' => data_get($payload, 'registro_id'),
            'session_id' => data_get($payload, 'sesion_consulta')];
    }

    public function destinationCode(string $place): ?string
    {
        $key = strtr(mb_strtolower(trim($place), 'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
        return self::DESTINATIONS[$key] ?? null;
    }

    public function destinationCodeFromText(string $text): ?string
    {
        $normalized = mb_strtolower($text, 'UTF-8');
        $normalized = strtr($normalized, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
        $matches = [];
        foreach (self::DESTINATIONS as $alias => $code) {
            preg_match_all('/(?<![a-z])' . preg_quote($alias, '/') . '(?![a-z])/u', $normalized, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] ?? [] as $match) {
                $matches[] = ['position' => $match[1], 'code' => $code];
            }
        }
        usort($matches, fn ($first, $second) => $first['position'] <=> $second['position']);
        return $matches ? $matches[array_key_last($matches)]['code'] : null;
    }
}

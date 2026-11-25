<?php

namespace App\Services;

use Illuminate\Support\Str;

class DecisionTreeInputNormalizer
{
    private const WORDS = [
        'paquete' => 'paquete',
        'paquetes' => 'paquete',
        'cotizar' => 'cotizar',
        'cotiza' => 'cotizar',
        'cotizo' => 'cotizar',
        'cotice' => 'cotizar',
        'cotizacion' => 'cotizar',
        'rastreo' => 'rastrear',
        'rastrear' => 'rastrear',
        'rastrea' => 'rastrear',
        'seguimiento' => 'seguimiento',
        'ubicacion' => 'ubicacion',
        'donde' => 'donde',
        'esta' => 'esta',
        'estado' => 'estado',
        'envio' => 'envio',
        'enviar' => 'enviar',
        'manda' => 'mandar',
        'mandar' => 'mandar',
        'despachar' => 'despachar',
        'carta' => 'carta',
        'sobre' => 'sobre',
        'guia' => 'guia',
        'precio' => 'precio',
        'tarifa' => 'tarifa',
        'cuanto' => 'cuanto',
        'cuesta' => 'cuesta',
        'costar' => 'costar',
        'nacional' => 'nacional',
        'internacional' => 'internacional',
        'bolivia' => 'bolivia',
        'exterior' => 'exterior',
        'ems' => 'ems',
        'delivery' => 'delivery',
        'express' => 'express',
        'asesor' => 'asesor',
        'agente' => 'agente',
        'humano' => 'humano',
        'reclamo' => 'reclamo',
        'representante' => 'representante',
        'persona' => 'persona',
        'hablar' => 'hablar',
        'comunicarme' => 'comunicarme',
        'ayuda' => 'ayuda',
        'si' => 'si',
        'claro' => 'claro',
        'continuar' => 'continuar',
        'acuerdo' => 'acuerdo',
        'no' => 'no',
        'cancelar' => 'cancelar',
        'salir' => 'salir',
        'detener' => 'detener',
        'terminar' => 'terminar',
        'detalles' => 'detalle',
        'detalle' => 'detalle',
        'informacion' => 'informacion',
        'datos' => 'datos',
        'nombre' => 'nombre',
        'saber' => 'saber',
        'averiguar' => 'averiguar',
        'consulta' => 'consulta',
        'consultar' => 'consulta',
        'atencion' => 'atencion',
        'cliente' => 'cliente',
        'operador' => 'operador',
        'deseo' => 'deseo',
        'necesito' => 'necesito',
        'menu' => 'menu',
        'inicio' => 'inicio',
        'reiniciar' => 'reiniciar',
        'omitir' => 'omitir',
        'dimensiones' => 'dimensiones',
        'listo' => 'listo',
        'gracias' => 'gracias',
        'puerta' => 'puerta',
        'ventanilla' => 'ventanilla',
        'diferencia' => 'diferencia',
        'cambio' => 'cambio',
        'varia' => 'varia',
        'conviene' => 'conviene',
        'mejor' => 'mejor',
        'comparar' => 'comparar',
        'explicar' => 'explicar',
        'consisten' => 'consisten',
        'significa' => 'significa',
        'describe' => 'describe',
        'quiere' => 'quiere',
        'decir' => 'decir',
        'entiendo' => 'entiendo',
        'comprendo' => 'comprendo',
        'origen' => 'origen',
        'destino' => 'destino',
        'peso' => 'peso',
        'kilo' => 'kilo',
        'kilos' => 'kilo',
        'kilogramo' => 'kilogramo',
        'kilogramos' => 'kilogramo',
        'gramo' => 'gramo',
        'gramos' => 'gramo',
        'paz' => 'paz',
        'santa' => 'santa',
        'cruz' => 'cruz',
        'cochabamba' => 'cochabamba',
        'sucre' => 'sucre',
        'trinidad' => 'trinidad',
        'cobija' => 'cobija',
        'oruro' => 'oruro',
        'potosi' => 'potosi',
        'tarija' => 'tarija',
        'beni' => 'beni',
        'chuquisaca' => 'chuquisaca',
        'pando' => 'pando',
        'alto' => 'alto',
        'argentina' => 'argentina',
        'brasil' => 'brasil',
        'brazil' => 'brasil',
        'canada' => 'canada',
        'chile' => 'chile',
        'china' => 'china',
        'colombia' => 'colombia',
        'corea' => 'corea',
        'sur' => 'sur',
        'costa' => 'costa',
        'rica' => 'rica',
        'cuba' => 'cuba',
        'ecuador' => 'ecuador',
        'salvador' => 'salvador',
        'espana' => 'espana',
        'estados' => 'estados',
        'unidos' => 'unidos',
        'francia' => 'francia',
        'guatemala' => 'guatemala',
        'honduras' => 'honduras',
        'india' => 'india',
        'italia' => 'italia',
        'japon' => 'japon',
        'mexico' => 'mexico',
        'nicaragua' => 'nicaragua',
        'panama' => 'panama',
        'paraguay' => 'paraguay',
        'peru' => 'peru',
        'portugal' => 'portugal',
        'reino' => 'reino',
        'unido' => 'unido',
        'republica' => 'republica',
        'dominicana' => 'dominicana',
        'uruguay' => 'uruguay',
        'venezuela' => 'venezuela',
    ];

    public function normalize(string $text): string
    {
        $normalized = mb_strtolower(Str::ascii(trim($text)), 'UTF-8');
        $normalized = preg_replace_callback(
            '/(?<![a-z0-9])[a-z]+(?![a-z0-9])/u',
            fn (array $match): string => $this->normalizeWord($match[0]),
            $normalized,
        ) ?? $normalized;

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    private function normalizeWord(string $word): string
    {
        $bestDistance = PHP_INT_MAX;
        $bestCanonicalWords = [];

        foreach (self::WORDS as $candidate => $canonical) {
            $length = max(strlen($word), strlen($candidate));
            $maximumDistance = $length < 4 ? 0 : ($length <= 6 ? 1 : 2);
            $distance = $this->distance($word, $candidate);
            if ($distance === 0) {
                return $canonical;
            }
            if ($distance > $maximumDistance) {
                continue;
            }
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestCanonicalWords = [$canonical => true];
            } elseif ($distance === $bestDistance) {
                $bestCanonicalWords[$canonical] = true;
            }
        }

        return count($bestCanonicalWords) === 1
            ? (string) array_key_first($bestCanonicalWords)
            : $word;
    }

    private function distance(string $word, string $candidate): int
    {
        if (strlen($word) === strlen($candidate)) {
            $differences = [];
            for ($index = 0, $length = strlen($word); $index < $length; $index++) {
                if ($word[$index] !== $candidate[$index]) {
                    $differences[] = $index;
                }
            }

            if (
                count($differences) === 2
                && $differences[1] === $differences[0] + 1
                && $word[$differences[0]] === $candidate[$differences[1]]
                && $word[$differences[1]] === $candidate[$differences[0]]
            ) {
                return 1;
            }
        }

        return levenshtein($word, $candidate);
    }
}

<?php

namespace App\Services;

class PackageWeightParser
{
    public function parse(string $message): float|false|null
    {
        if (preg_match('/\b(?:bs\.?|bob|usd|d[oó]lares?)\b|[$€£]/iu', $message) === 1) {
            return false;
        }

        preg_match_all(
            '/(?<![\p{L}\p{N}])([+-]?\d+(?:[.,]\d+)?)\s*(kg|kilos?|kilogramos?|g|gramos?)?/iu',
            $message,
            $matches,
            PREG_SET_ORDER,
        );

        if ($matches === []) {
            return null;
        }
        if (count($matches) !== 1) {
            return false;
        }

        $unit = mb_strtolower($matches[0][2] ?? '');
        if ($unit === ''
            && preg_match('/^\s*[+-]?\d+(?:[.,]\d+)?\s*$/u', $message) !== 1
            && preg_match('/\b(peso|pesa|pesar)\b/iu', $message) !== 1) {
            return false;
        }

        $number = (float) str_replace(',', '.', $matches[0][1]);
        if (in_array($unit, ['g', 'gramo', 'gramos'], true)) {
            $number /= 1000;
        }

        return $number > 0 ? $number : false;
    }
}

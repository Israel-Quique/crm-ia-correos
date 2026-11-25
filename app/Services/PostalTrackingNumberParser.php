<?php

namespace App\Services;

class PostalTrackingNumberParser
{
    public function extract(string $message): ?string
    {
        preg_match_all(
            '/(?<![A-Z0-9])([A-Z]{1,3}[\s-]*\d{5,}[\s-]*[A-Z]{0,3}|[A-Z0-9]+(?:-[A-Z0-9]+)+|[A-Z0-9]{6,})(?![A-Z0-9])/iu',
            $message,
            $matches,
        );

        foreach ($matches[1] ?? [] as $match) {
            $number = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $match) ?? '');
            if (strlen($number) >= 6
                && preg_match('/[A-Z]/i', $number) === 1
                && preg_match('/\d/', $number) === 1) {
                return $number;
            }
        }

        return null;
    }
}

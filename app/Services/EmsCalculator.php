<?php

namespace App\Services;

use RuntimeException;

/** Equivalente Laravel de backend/services/ems-calculator.js. */
class EmsCalculator
{
    public function __construct(private readonly PostarQuoteService $postar)
    {
    }

    public function calculate(
        string $category,
        string $destination,
        float $weight,
        ?array $dimensions = null,
    ): array {
        $category = strtoupper(trim($category));
        if (!in_array($category, ['EMS NAT', 'EMS INT'], true)) {
            throw new RuntimeException('La categoría debe ser EMS NAT o EMS INT.');
        }
        if ($weight <= 0) {
            throw new RuntimeException('El peso debe ser mayor que cero.');
        }
        if ($category === 'EMS NAT' && !str_starts_with(strtolower($destination), 'nacional_')) {
            throw new RuntimeException('EMS Nacional requiere un destino nacional válido.');
        }
        if ($category === 'EMS INT' && str_starts_with(strtolower($destination), 'nacional_')) {
            throw new RuntimeException('EMS Internacional requiere un destino internacional válido.');
        }

        if ($dimensions) {
            foreach (['largo', 'ancho', 'alto'] as $dimension) {
                if (!isset($dimensions[$dimension]) || (float) $dimensions[$dimension] <= 0) {
                    throw new RuntimeException('Las dimensiones deben ser positivas en centímetros.');
                }
            }
        }

        return $this->postar->quote($category, $destination, $weight, $dimensions);
    }

    public function destinationFromText(string $text): ?string
    {
        return $this->postar->destinationCodeFromText($text);
    }
}

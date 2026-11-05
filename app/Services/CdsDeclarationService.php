<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CdsDeclarationService
{
    private array $cache = [];
    public function forTracking(string $tracking): array
    {
        if (array_key_exists($tracking, $this->cache)) return $this->cache[$tracking];
        $url = config('services.cds.url');
        if (!$url) return $this->cache[$tracking] = [];
        try {
            $response = Http::timeout((int) config('services.cds.timeout', 20))
                ->withOptions(['verify' => (bool) config('services.cds.verify_ssl', false)])
                ->acceptJson()->withToken((string) config('services.cds.token'))
                ->get($url, ['tracking_number' => $tracking, 'codigo' => $tracking, 'guia' => $tracking]);
            if ($response->failed()) return $this->cache[$tracking] = [];
            return $this->cache[$tracking] = $this->findDeclaration($response->json(), $tracking);
        } catch (\Throwable $exception) {
            Log::warning('CDS no disponible para la guía', ['tracking' => $tracking, 'message' => $exception->getMessage()]);
            return $this->cache[$tracking] = [];
        }
    }

    private function findDeclaration(mixed $payload, string $tracking): array
    {
        if (!is_array($payload)) return [];
        $nodes = [];
        $this->collectMatchingNodes($payload, strtoupper($tracking), $nodes);
        foreach ($nodes as $node) {
            if (!is_array($node)) continue;
            $declaration = data_get($node, 'declaracion_cds.objetos.0.declaraciones.0', $node);
            return [
                'sender' => $this->value($declaration, ['remitente_declarado.nombre', 'remitente.nombre', 'campos.SNm', 'remitente_nombre', 'sender.name', 'sender']),
                'recipient' => $this->value($declaration, ['destinatario_declarado.nombre', 'destinatario.nombre', 'campos.RNm', 'destinatario_nombre', 'recipient.name', 'recipient']),
                'phone' => $this->value($declaration, ['destinatario_declarado.telefono', 'destinatario.telefono', 'campos.RTel', 'telefono_destinatario', 'recipient.phone', 'phone']),
            ];
        }
        return [];
    }

    private function collectMatchingNodes(mixed $value, string $tracking, array &$matches): void
    {
        if (!is_array($value)) return;
        if (strtoupper((string) ($value['codigo'] ?? '')) === $tracking || strtoupper((string) ($value['codigo_s10'] ?? '')) === $tracking) $matches[] = $value;
        foreach ($value as $child) $this->collectMatchingNodes($child, $tracking, $matches);
    }

    private function value(array $node, array $paths): ?string
    {
        foreach ($paths as $path) { $value = data_get($node, $path); if (is_array($value)) $value = $value['nombre'] ?? $value['name'] ?? null; if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value); }
        return null;
    }
}

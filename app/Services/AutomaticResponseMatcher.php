<?php

namespace App\Services;

use App\Models\AutomaticResponse;
use Illuminate\Support\Str;

class AutomaticResponseMatcher
{
    public function match(string $message): ?AutomaticResponse
    {
        $normalizedMessage = $this->normalize($message);
        $best = null;
        $bestScore = 0;

        $responses = AutomaticResponse::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query->whereIn('type', ['quote', 'tracking'])
                    ->orWhere(function ($query): void {
                        $query->where('type', 'event')->where('scope', 'general');
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        foreach ($responses as $response) {
            $score = 0;
            foreach (array_merge([$response->key, $response->name], $response->trigger_phrases ?? []) as $term) {
                $normalizedTerm = $this->normalize(str_replace('_', ' ', trim($term)));
                if ($normalizedTerm !== '' && str_contains($normalizedMessage, $normalizedTerm)) {
                    $score = max($score, mb_strlen($normalizedTerm));
                }
            }

            if ($score > $bestScore) {
                $best = $response;
                $bestScore = $score;
            }
        }

        return $best;
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(Str::ascii($value), 'UTF-8');

        return preg_replace('/\bpauqu?ete\b/u', 'paquete', $normalized) ?? $normalized;
    }
}

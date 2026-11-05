<?php

namespace App\Services;

use App\Models\AutomaticResponse;

class AutomaticResponseMatcher
{
    public function match(string $message): ?AutomaticResponse
    {
        $normalizedMessage = mb_strtolower($message);
        $best = null;
        $bestScore = 0;

        foreach (AutomaticResponse::where('active', true)->latest()->get() as $response) {
            $score = 0;
            foreach (array_merge([$response->key, $response->name], $response->trigger_phrases ?? []) as $term) {
                if ($term !== '' && str_contains($normalizedMessage, mb_strtolower(str_replace('_', ' ', $term)))) {
                    $score += 3;
                }
            }

            if ($score > $bestScore) {
                $best = $response;
                $bestScore = $score;
            }
        }

        return $best;
    }
}

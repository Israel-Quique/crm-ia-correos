<?php

namespace App\Services;

use App\Models\AutomaticResponse;

class AutomaticResponseMatcher
{
    public function __construct(private readonly DecisionTreeInputNormalizer $inputNormalizer)
    {
    }

    public function match(string $message): ?AutomaticResponse
    {
        $normalizedMessage = $this->inputNormalizer->normalize($message);
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
                $normalizedTerm = $this->inputNormalizer->normalize(str_replace('_', ' ', trim($term)));
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
}

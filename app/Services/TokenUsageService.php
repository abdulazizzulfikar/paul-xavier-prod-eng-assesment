<?php

namespace App\Services;

class TokenUsageService
{
    public function estimateTextTokens(string $prompt): int
    {
        $charactersPerToken = config('pixelforge.tokens.characters_per_text_token');

        return (int) ceil(mb_strlen($prompt) / $charactersPerToken);
    }

    /**
     * The fewest and most tokens Pixelforge can bill for this request.
     * Output and image tokens are exact; text varies ±15% and reasoning by quality.
     *
     * @return array{min: array<string, int>, max: array<string, int>}
     */
    public function estimateUsageRange(string $prompt, string $size, string $quality, int $referenceImageCount): array
    {
        $tokens = config('pixelforge.tokens');

        $textTokens = $this->estimateTextTokens($prompt);
        $variance = $tokens['text_token_variance_percent'];
        $imageTokens = $referenceImageCount * $tokens['per_reference_image'];
        $outputTokens = $tokens['output'][$quality][$size];
        $reasoning = $tokens['reasoning'][$quality];

        return [
            'min' => [
                'input_text_tokens' => intdiv($textTokens * (100 - $variance), 100),
                'input_image_tokens' => $imageTokens,
                'output_tokens' => $outputTokens,
                'reasoning_tokens' => $reasoning['min'],
            ],
            'max' => [
                'input_text_tokens' => (int) ceil($textTokens * (100 + $variance) / 100),
                'input_image_tokens' => $imageTokens,
                'output_tokens' => $outputTokens,
                'reasoning_tokens' => $reasoning['max'],
            ],
        ];
    }
}

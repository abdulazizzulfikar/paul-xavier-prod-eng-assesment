<?php

namespace App\Services;

use Facades\App\Services\TokenUsageService;

/**
 * All money is integer nanodollars (1 USD = 1,000,000,000), never floats.
 * Pixelforge's cost is kept exact; rounding happens only once, when adding our markup.
 */
class PricingService
{
    private const NANO_PER_DOLLAR = 1_000_000_000;

    private const TOKENS_PER_RATE_UNIT = 1_000_000;

    /**
     * Pixelforge's cost for a usage, at the given rates (US dollars per 1M tokens).
     *
     * @param  array<string, int>  $usage
     * @param  array<string, string>  $rates
     */
    public function calculateProviderCost(array $usage, array $rates): int
    {
        $total = 0;

        foreach ($rates as $tokenType => $ratePerMillion) {
            $total += ($usage[$tokenType] ?? 0) * $this->toNano($ratePerMillion);
        }

        return intdiv($total, self::TOKENS_PER_RATE_UNIT);
    }

    // Rounds up so fractions of a nanodollar never come out of our margin.
    public function applyMarkup(int $providerCostNano): int
    {
        $percent = 100 + config('pixelforge.markup_percent');

        return intdiv($providerCostNano * $percent + 99, 100);
    }

    /**
     * What the user will be charged, at the least and at the most.
     *
     * @return array{min: int, max: int}
     */
    public function estimateCharge(string $prompt, string $size, string $quality, int $referenceImageCount): array
    {
        $usage = TokenUsageService::estimateUsageRange($prompt, $size, $quality, $referenceImageCount);
        $rates = config('pixelforge.rates');

        return [
            'min' => $this->applyMarkup($this->calculateProviderCost($usage['min'], $rates)),
            'max' => $this->applyMarkup($this->calculateProviderCost($usage['max'], $rates)),
        ];
    }

    // Parses an exact decimal dollar string such as "12.50" without going through floats.
    public function toNano(string $dollars): int
    {
        [$whole, $fraction] = array_pad(explode('.', $dollars, 2), 2, '');

        return (int) $whole * self::NANO_PER_DOLLAR + (int) str_pad($fraction, 9, '0');
    }
}

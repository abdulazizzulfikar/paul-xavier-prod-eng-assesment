<?php

use Facades\App\Services\PricingService;
use Facades\App\Services\TokenUsageService;

$rates = [
    'input_text_tokens' => '5.00',
    'input_image_tokens' => '10.00',
    'output_tokens' => '40.00',
    'reasoning_tokens' => '12.50',
];

it('parses dollar strings into nanodollars exactly', function () {
    expect(PricingService::toNano('12.50'))->toBe(12_500_000_000)
        ->and(PricingService::toNano('0.169080000'))->toBe(169_080_000)
        ->and(PricingService::toNano('5'))->toBe(5_000_000_000);
});

it('matches the provider cost in the usage example from the docs', function () use ($rates) {
    $usage = ['input_text_tokens' => 41, 'input_image_tokens' => 0, 'output_tokens' => 3300, 'reasoning_tokens' => 2950];

    // Docs: cost_usd "0.169080000"
    expect(PricingService::calculateProviderCost($usage, $rates))->toBe(169_080_000);
});

it('ignores total_tokens and uses the locked rates of the generation', function () {
    $usage = ['input_text_tokens' => 1_000_000, 'output_tokens' => 0, 'total_tokens' => 1_000_000];
    $lockedRates = ['input_text_tokens' => '4.00', 'output_tokens' => '40.00'];

    expect(PricingService::calculateProviderCost($usage, $lockedRates))->toBe(4_000_000_000);
});

it('adds the 25% markup and rounds up, never down', function () {
    expect(PricingService::applyMarkup(169_080_000))->toBe(211_350_000)
        ->and(PricingService::applyMarkup(1))->toBe(2)
        ->and(PricingService::applyMarkup(4))->toBe(5)
        ->and(PricingService::applyMarkup(0))->toBe(0);
});

it('estimates text tokens as ceil(characters / 4), counting unicode characters', function () {
    expect(TokenUsageService::estimateTextTokens(str_repeat('a', 400)))->toBe(100)
        ->and(TokenUsageService::estimateTextTokens('abcde'))->toBe(2)
        ->and(TokenUsageService::estimateTextTokens('ééé'))->toBe(1);
});

it('estimates the worst case from the docs as the top of the range', function () {
    $charge = PricingService::estimateCharge(str_repeat('a', 400), '1536x1024', 'high', 2);

    // Docs: the most this generation can cost Pixelforge is $0.373875000; plus 25%.
    expect($charge['max'])->toBe(467_343_750);
});

it('estimates the cheapest case as the bottom of the range', function () {
    $charge = PricingService::estimateCharge(str_repeat('a', 400), '1024x1024', 'low', 0);

    // 85 text tokens, 280 output tokens, 0 reasoning tokens
    $providerCost = 85 * 5_000 + 280 * 40_000;

    expect($charge['min'])->toBe(PricingService::applyMarkup($providerCost));
});

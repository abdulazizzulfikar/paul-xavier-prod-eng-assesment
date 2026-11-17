<?php

return [

    'base_url' => env('PIXELFORGE_BASE_URL', 'https://pixelforge.promptedit.dev/v1'),

    'api_key' => env('PIXELFORGE_API_KEY'),

    'webhook_secret' => env('PIXELFORGE_WEBHOOK_SECRET'),

    'markup_percent' => 25,

    // Copy of GET /v1/pricing, used only for the upfront estimate.
    // Actual costs use the rates Pixelforge locks onto each generation.
    'rates' => [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ],

    'tokens' => [
        'characters_per_text_token' => 4,
        'text_token_variance_percent' => 15,
        'per_reference_image' => 765,
        'output' => [
            'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
            'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
            'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
        ],
        'reasoning' => [
            'low' => ['min' => 0, 'max' => 500],
            'medium' => ['min' => 200, 'max' => 2000],
            'high' => ['min' => 1000, 'max' => 8000],
        ],
    ],

];

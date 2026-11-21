<?php

return [
    // Server-side only: never exposed to visitors.
    'data_api_token' => env('FARES_API_TOKEN'),
    'data_api_base' => env('FARES_API_BASE', 'https://api.travelpayouts.com'),
    'marker' => env('AFFILIATE_MARKER'),

    'ai' => [
        'provider' => env('AI_PROVIDER', 'anthropic'), // anthropic | openai
        'key' => env('AI_API_KEY'),
        'model' => env('AI_MODEL', 'claude-sonnet-5-5'),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 8000),
    ],
];

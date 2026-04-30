<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI services used in the application
    |
    */

    'groq' => [
        'api_key' => env('GROQ_API_KEY'),
        'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model' => env('GROQ_MODEL', 'llama3-8b-8192'),
        'max_tokens' => (int) env('GROQ_MAX_TOKENS', 1000),
        'temperature' => (float) env('GROQ_TEMPERATURE', 0.7),
    ],

    'cache' => [
        'insights_ttl' => env('AI_INSIGHTS_CACHE_MINUTES', 5), // 5 minutes default
        'rate_limit_per_user' => env('AI_RATE_LIMIT_PER_USER', 12), // 12 requests per hour per user
    ],

    'fallback' => [
        'insights' => 'Анализ данных показывает текущее состояние вашего бизнеса.',
        'recommendations' => [
            'Регулярно анализируйте ключевые показатели',
            'Следите за трендами в вашей отрасли',
            'Улучшайте качество обслуживания клиентов'
        ]
    ]
];

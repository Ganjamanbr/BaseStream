<?php

// config/streams.php - BaseStream channel/scraper configuration

return [
    /*
    |--------------------------------------------------------------------------
    | Stream Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | Quanto tempo manter streams resolvidos em cache Redis.
    | TV ao vivo: 5min (URLs mudam frequentemente)
    | VOD: 1h (mais estável)
    */
    'cache_ttl' => [
        'live' => env('STREAM_CACHE_TTL_LIVE', 300),       // 5 min
        'vod'  => env('STREAM_CACHE_TTL_VOD', 3600),       // 1 hora
    ],

    /*
    |--------------------------------------------------------------------------
    | Qualidades disponíveis
    |--------------------------------------------------------------------------
    */
    'qualities' => ['SD', 'HD', 'FHD', 'AUTO'],
    'default_quality' => env('STREAM_DEFAULT_QUALITY', 'AUTO'),

    /*
    |--------------------------------------------------------------------------
    | Categorias (Brazuca-style)
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'tv-br'    => 'TV Brasil',
        'filmes'   => 'Filmes Dublados',
        'series'   => 'Séries',
        'animes'   => 'Animes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Proxy Settings
    |--------------------------------------------------------------------------
    */
    'proxy' => [
        'timeout'    => env('STREAM_PROXY_TIMEOUT', 30),
        'user_agent' => env('STREAM_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'),
        'max_redirects' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'free'  => env('STREAM_RATE_LIMIT_FREE', 10),     // req/min
        'pro'   => env('STREAM_RATE_LIMIT_PRO', 60),      // req/min
    ],

    /*
    |--------------------------------------------------------------------------
    | Tiers
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        'free' => [
            'max_tokens' => 2,
            'categories'  => ['tv-br'],
        ],
        'pro' => [
            'max_tokens' => 10,
            'categories'  => ['tv-br', 'filmes', 'series', 'animes'],
        ],
    ],
];

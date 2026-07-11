<?php

return [
    'display-name' => env('COHISTOGRAPH_DISPLAY_NAME', 'CoHistograph'),

    'graph' => [
        'connection-name' => env('COHISTOGRAPH_GRAPH_CONNECTION_NAME', 'pgsql-age'),
        'name' => env('COHISTOGRAPH_GRAPH_NAME', 'default_graph'),
        'locales' => [
            'zh_tw' => '繁體中文',
            'ja_jp' => '日本語',
            'en_us' => 'English',
        ],
        'display_locale' => env('COHISTOGRAPH_DISPLAY_LOCALE', 'zh_tw'),
        'display_locale_fallback' => ['zh_tw', 'en_us', 'ja_jp'],
    ],
];

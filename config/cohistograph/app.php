<?php

return [
    'display-name' => env('COHISTOGRAPH_DISPLAY_NAME', 'CoHistograph'),

    'graph' => [
        'connection-name' => env('COHISTOGRAPH_GRAPH_CONNECTION_NAME', 'pgsql-age'),
        'name' => env('COHISTOGRAPH_GRAPH_NAME', 'default_graph'),
    ],
];

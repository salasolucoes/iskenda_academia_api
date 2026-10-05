<?php

return [
    'host' => env('TURN_HOST', 'coturn'),
    'port' => env('TURN_PORT', 3478),
    'tls_port' => env('TURN_TLS_PORT', 5349),
    'secret' => env('TURN_SECRET'),
    'realm' => env('TURN_REALM', 'iskenda.com'),
];

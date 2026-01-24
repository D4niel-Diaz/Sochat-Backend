<?php

return [

    'default' => env('THROTTLE_DEFAULT', '60,1'),

    'api' => env('THROTTLE_API', '60,1'),

    'limiter' => env('THROTTLE_LIMITER', 'database'),

    'limits' => [
        'guest-create' => '5,1',
        'chat-start' => '10,1',
        'send-message' => '30,1',
        'report-submit' => '3,1',
        'admin-api' => '100,1',
    ],

];

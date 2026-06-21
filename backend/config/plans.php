<?php

/*
|--------------------------------------------------------------------------
| Subscription plans
|--------------------------------------------------------------------------
|
| Per-tier limits for registered users. The admin assigns a plan to a user;
| these values then drive enforcement across the app.
|
|   max_endpoints    Max webhook URLs the user may create. null = unlimited.
|   request_limit    Max captured requests per URL. null = unlimited.
|   retention_days   Days captured requests are kept before pruning. null = forever.
|   custom_responses Whether the user may set per-endpoint custom responses.
|
| Guests are governed separately by app.guest_request_limit / guest_retention_days.
|
*/

return [

    'free' => [
        'label'            => 'Free',
        'max_endpoints'    => 3,
        'request_limit'    => 500,
        'retention_days'   => 7,
        'custom_responses' => false,
    ],

    'pro' => [
        'label'            => 'Pro',
        'max_endpoints'    => 25,
        'request_limit'    => 10000,
        'retention_days'   => 30,
        'custom_responses' => true,
    ],

    'team' => [
        'label'            => 'Team',
        'max_endpoints'    => null,   // unlimited
        'request_limit'    => null,   // unlimited
        'retention_days'   => null,   // kept forever
        'custom_responses' => true,
    ],

];

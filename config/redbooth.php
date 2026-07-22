<?php

return [
    'client_id' => env('CLIENT_ID'),
    'client_secret' => env('CLIENT_SECRET'),
    'redirect_uri' => env('REDBOOTH_REDIRECT_URI'),
    'authorize_url' => env('REDBOOTH_AUTHORIZE_URL', 'https://redbooth.com/oauth2/authorize'),
    'token_url' => env('REDBOOTH_TOKEN_URL', 'https://redbooth.com/oauth2/token'),
    'api_url' => env('REDBOOTH_API_URL', 'https://redbooth.com/api/3'),
    'external_api_key' => env('REDBOOTH_EXTERNAL_API_KEY'),
    'external_user_id' => env('REDBOOTH_EXTERNAL_USER_ID'),
];

<?php

return [
    'project_id' => env('FIREBASE_PROJECT_ID', 'kasir-40363'),
    'products_collection' => env('FIREBASE_PRODUCTS_COLLECTION', 'products'),
    'credentials' => env('FIREBASE_CREDENTIALS'),
    'credentials_base64' => env('FIREBASE_CREDENTIALS_BASE64'),
    'client_email' => env('FIREBASE_CLIENT_EMAIL'),
    'private_key' => str_replace('\\n', "\n", env('FIREBASE_PRIVATE_KEY', '')),
    'private_key_id' => env('FIREBASE_PRIVATE_KEY_ID'),
    'client_id' => env('FIREBASE_CLIENT_ID'),
    'debug_token' => env('FIREBASE_DEBUG_TOKEN'),
];

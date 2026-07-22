<?php

return [
    // Sandbox store, e.g. https://laravel-import-test.myshopify.com
    'store_url' => env('SHOPIFY_STORE_URL'),

    // Admin API access token (secret — set in .env, never commit)
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),

    // Admin API version
    'api_version' => env('SHOPIFY_API_VERSION', '2025-07'),

    // Collection that imported products must be added to
    'collection_id' => env('SHOPIFY_COLLECTION_ID'),
];

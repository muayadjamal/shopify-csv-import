<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Shopify\Contracts\ShopifyProductImporter;
use App\Services\Shopify\GraphqlShopifyImporter;
use Illuminate\Support\ServiceProvider;
use Shopify\Auth\FileSessionStorage;
use Shopify\Clients\Graphql;
use Shopify\Context;

class ShopifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShopifyProductImporter::class, function (): GraphqlShopifyImporter {
            $config = config('shopify');

            $this->initializeContext((string) $config['api_version']);

            $client = new Graphql(
                domain: (string) parse_url((string) $config['store_url'], PHP_URL_HOST),
                token: (string) $config['access_token'],
            );

            return new GraphqlShopifyImporter($client, (int) $config['collection_id']);
        });
    }

    /**
     * Initialize the Shopify SDK once for this custom (private) app. The API
     * key/secret are unused for a fixed Admin API token, so placeholders are
     * fine — the access token is what authenticates each request.
     */
    private function initializeContext(string $apiVersion): void
    {
        if (Context::$API_VERSION === $apiVersion) {
            return;
        }

        Context::initialize(
            apiKey: 'csv-shopify-import',
            apiSecretKey: 'csv-shopify-import',
            scopes: ['write_products'],
            hostName: (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            sessionStorage: new FileSessionStorage(storage_path('framework/cache/shopify')),
            apiVersion: $apiVersion,
            isEmbeddedApp: false,
            // Custom-app Admin API token auth (X-Shopify-Access-Token), not the
            // legacy private-app basic auth — so isPrivateApp must be false.
            isPrivateApp: false,
        );
    }
}

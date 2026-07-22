<?php

declare(strict_types=1);

namespace App\Services\Shopify;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Thin wrapper around the Shopify Admin REST API.
 *
 * Handles authentication, the leaky-bucket rate limit (HTTP 429 + Retry-After),
 * and turns error responses into a {@see ShopifyException}.
 */
class ShopifyClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $accessToken,
        private readonly int $collectionId,
        private readonly int $maxRetries = 3,
    ) {}

    /**
     * Create a product and return the created product payload.
     *
     * @param  array<string, mixed>  $product  A Shopify `product` resource (without the wrapping key).
     * @return array<string, mixed>
     */
    public function createProduct(array $product): array
    {
        $response = $this->request('post', 'products.json', ['product' => $product]);

        return $response->json('product', []);
    }

    /**
     * Attach a product to the configured collection.
     *
     * @return array<string, mixed>
     */
    public function addProductToCollection(int $productId): array
    {
        $response = $this->request('post', 'collects.json', [
            'collect' => [
                'product_id' => $productId,
                'collection_id' => $this->collectionId,
            ],
        ]);

        return $response->json('collect', []);
    }

    public function collectionId(): int
    {
        return $this->collectionId;
    }

    /**
     * Send a request, retrying on rate-limit (429) responses while respecting
     * the Retry-After header, and throwing on any other failure.
     *
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, string $uri, array $payload = []): Response
    {
        $attempt = 0;

        do {
            $response = $this->client()->send(strtoupper($method), $uri, ['json' => $payload]);

            $rateLimited = $response->status() === 429;

            if ($rateLimited && $attempt < $this->maxRetries) {
                Sleep::for($this->retryAfterMs($response))->milliseconds();
                $attempt++;

                continue;
            }

            break;
        } while (true);

        if ($response->failed()) {
            throw ShopifyException::fromResponse($response);
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeader('X-Shopify-Access-Token', $this->accessToken)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(30);
    }

    private function retryAfterMs(Response $response): int
    {
        $seconds = (float) ($response->header('Retry-After') ?: 2);

        return (int) ($seconds * 1000);
    }
}

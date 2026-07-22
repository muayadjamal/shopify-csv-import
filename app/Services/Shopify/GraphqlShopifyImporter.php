<?php

declare(strict_types=1);

namespace App\Services\Shopify;

use App\Services\Shopify\Contracts\ShopifyProductImporter;
use Illuminate\Support\Sleep;
use Shopify\Clients\Graphql;

/**
 * Imports products into Shopify through the Admin GraphQL API using the
 * official shopify/shopify-api SDK (which also handles 429/5xx retries).
 *
 * Products are upserted with the `productSet` mutation keyed by handle, so an
 * existing product is updated rather than duplicated.
 */
class GraphqlShopifyImporter implements ShopifyProductImporter
{
    private const MAX_THROTTLE_RETRIES = 5;

    public function __construct(
        private readonly Graphql $client,
        private readonly int $collectionId,
    ) {}

    public function importProduct(array $product): ProductImportResult
    {
        $handle = (string) ($product['handle'] ?? '');
        $existingId = $handle !== '' ? $this->findProductIdByHandle($handle) : null;

        $data = $this->mutate(
            <<<'GQL'
            mutation productSet($input: ProductSetInput!, $identifier: ProductSetIdentifiers) {
                productSet(synchronous: true, input: $input, identifier: $identifier) {
                    product { id }
                    userErrors { field message code }
                }
            }
            GQL,
            [
                'input' => self::buildProductSetInput($product),
                'identifier' => $handle !== '' ? ['handle' => $handle] : null,
            ],
            'productSet',
        );

        $id = self::gidToId($data['productSet']['product']['id'] ?? '');

        return new ProductImportResult($id, created: $existingId === null);
    }

    public function addToCollection(int $productId): void
    {
        $this->mutate(
            <<<'GQL'
            mutation collectionAddProducts($id: ID!, $productIds: [ID!]!) {
                collectionAddProducts(id: $id, productIds: $productIds) {
                    collection { id }
                    userErrors { field message }
                }
            }
            GQL,
            [
                'id' => self::gid('Collection', $this->collectionId),
                'productIds' => [self::gid('Product', $productId)],
            ],
            'collectionAddProducts',
        );
    }

    public function collectionId(): int
    {
        return $this->collectionId;
    }

    private function findProductIdByHandle(string $handle): ?int
    {
        $data = $this->query(
            'query findByHandle($q: String!) { products(first: 1, query: $q) { nodes { id } } }',
            ['q' => "handle:{$handle}"],
        );

        $gid = $data['products']['nodes'][0]['id'] ?? null;

        return $gid ? self::gidToId($gid) : null;
    }

    /**
     * Build the Shopify `ProductSetInput` from a canonical mapped product.
     * Kept pure and public so the mapping can be unit-tested without the API.
     *
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public static function buildProductSetInput(array $product): array
    {
        $variant = $product['variants'][0] ?? [];

        $input = array_filter([
            'handle' => $product['handle'] ?? null,
            'title' => $product['title'] ?? null,
            'descriptionHtml' => $product['body_html'] ?? null,
            'vendor' => $product['vendor'] ?? null,
            'productType' => $product['product_type'] ?? null,
            'status' => isset($product['status']) ? strtoupper((string) $product['status']) : null,
            'tags' => isset($product['tags']) ? array_map('trim', explode(',', (string) $product['tags'])) : null,
        ], static fn ($value) => $value !== null);

        $input['productOptions'] = [[
            'name' => 'Title',
            'position' => 1,
            'values' => [['name' => 'Default Title']],
        ]];

        $input['variants'] = [self::buildVariantInput($variant)];

        if (! empty($product['images'])) {
            $input['files'] = array_map(static fn (array $image) => array_filter([
                'originalSource' => $image['src'] ?? null,
                'alt' => $image['alt'] ?? null,
                'contentType' => 'IMAGE',
            ], static fn ($value) => $value !== null), $product['images']);
        }

        return $input;
    }

    /**
     * @param  array<string, mixed>  $variant
     * @return array<string, mixed>
     */
    private static function buildVariantInput(array $variant): array
    {
        $input = array_filter([
            'sku' => $variant['sku'] ?? null,
            'price' => isset($variant['price']) ? (string) $variant['price'] : null,
            'compareAtPrice' => isset($variant['compare_at_price']) ? (string) $variant['compare_at_price'] : null,
            'taxable' => $variant['taxable'] ?? null,
            'inventoryPolicy' => isset($variant['inventory_policy']) ? strtoupper((string) $variant['inventory_policy']) : null,
        ], static fn ($value) => $value !== null);

        $input['optionValues'] = [['optionName' => 'Title', 'name' => 'Default Title']];

        if (isset($variant['weight'], $variant['weight_unit'])) {
            $input['inventoryItem'] = [
                'measurement' => [
                    'weight' => [
                        'value' => (float) $variant['weight'],
                        'unit' => self::weightUnit((string) $variant['weight_unit']),
                    ],
                ],
            ];
        }

        return $input;
    }

    private static function weightUnit(string $unit): string
    {
        return match (strtolower($unit)) {
            'kg' => 'KILOGRAMS',
            'g' => 'GRAMS',
            'lb' => 'POUNDS',
            'oz' => 'OUNCES',
            default => 'KILOGRAMS',
        };
    }

    /**
     * Run a mutation and surface any userErrors as an exception.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function mutate(string $mutation, array $variables, string $root): array
    {
        $data = $this->query($mutation, $variables);
        $errors = $data[$root]['userErrors'] ?? [];

        if ($errors !== []) {
            $messages = array_map(
                static fn (array $error) => trim(($error['field'][0] ?? '').' '.($error['message'] ?? '')),
                $errors,
            );

            throw new ShopifyException(implode('; ', $messages));
        }

        return $data;
    }

    /**
     * Execute a GraphQL document, retrying when Shopify throttles the request.
     *
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function query(string $document, array $variables): array
    {
        $attempt = 0;

        do {
            $response = $this->client->query(['query' => $document, 'variables' => $variables]);
            $decoded = $response->getDecodedBody();
            $body = is_array($decoded) ? $decoded : [];

            if ($this->isThrottled($body) && $attempt < self::MAX_THROTTLE_RETRIES) {
                Sleep::for($this->throttleWaitMs($body))->milliseconds();
                $attempt++;

                continue;
            }

            break;
        } while (true);

        $errors = $body['errors'] ?? null;

        if ($response->getStatusCode() >= 400 || ! empty($errors)) {
            throw new ShopifyException(
                self::formatErrors($errors) ?? 'Shopify GraphQL request failed.',
                $response->getStatusCode(),
            );
        }

        return $body['data'] ?? [];
    }

    /**
     * Shopify returns `errors` either as a string (transport-level, e.g. auth)
     * or as a list of objects (GraphQL validation).
     */
    private static function formatErrors(mixed $errors): ?string
    {
        if (is_string($errors)) {
            return $errors;
        }

        if (is_array($errors)) {
            return implode('; ', array_map(
                static fn ($error) => is_array($error) ? ($error['message'] ?? 'Unknown error') : (string) $error,
                $errors,
            ));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function isThrottled(array $body): bool
    {
        if (! is_array($body['errors'] ?? null)) {
            return false;
        }

        foreach ($body['errors'] as $error) {
            if (is_array($error) && ($error['extensions']['code'] ?? null) === 'THROTTLED') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function throttleWaitMs(array $body): int
    {
        $cost = $body['extensions']['cost'] ?? null;

        if ($cost) {
            $needed = $cost['requestedQueryCost'] ?? 0;
            $available = $cost['throttleStatus']['currentlyAvailable'] ?? 0;
            $restoreRate = $cost['throttleStatus']['restoreRate'] ?? 50;
            $deficit = max(0, $needed - $available);

            if ($restoreRate > 0) {
                return (int) ceil($deficit / $restoreRate * 1000);
            }
        }

        return 1000;
    }

    private static function gid(string $resource, int $id): string
    {
        return "gid://shopify/{$resource}/{$id}";
    }

    private static function gidToId(string $gid): int
    {
        return (int) (preg_replace('/\D/', '', substr(strrchr($gid, '/') ?: $gid, 1)) ?: '0');
    }
}

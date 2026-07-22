<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Shopify\Contracts\ShopifyProductImporter;
use App\Services\Shopify\ProductImportResult;
use App\Services\Shopify\ShopifyException;

/**
 * In-memory {@see ShopifyProductImporter} for tests: records every call and
 * can be told to treat imports as updates or to fail specific handles.
 */
class FakeShopifyProductImporter implements ShopifyProductImporter
{
    /** @var list<array{id:int, product:array<string,mixed>}> */
    public array $imported = [];

    /** @var list<int> */
    public array $collectionAdds = [];

    /** @var list<string> Handles that should throw when imported. */
    public array $failHandles = [];

    public bool $treatAsUpdate = false;

    private int $sequence = 1000;

    public function importProduct(array $product): ProductImportResult
    {
        $handle = (string) ($product['handle'] ?? '');

        if (in_array($handle, $this->failHandles, true)) {
            throw new ShopifyException('Shopify rejected the product.');
        }

        $id = ++$this->sequence;
        $this->imported[] = ['id' => $id, 'product' => $product];

        return new ProductImportResult($id, created: ! $this->treatAsUpdate);
    }

    public function addToCollection(int $productId): void
    {
        $this->collectionAdds[] = $productId;
    }

    public function collectionId(): int
    {
        return 123456;
    }
}

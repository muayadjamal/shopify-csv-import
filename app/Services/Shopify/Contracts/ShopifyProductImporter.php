<?php

declare(strict_types=1);

namespace App\Services\Shopify\Contracts;

use App\Services\Shopify\ProductImportResult;

/**
 * Imports products into Shopify. Implementations decide the transport
 * (e.g. the GraphQL Admin API).
 */
interface ShopifyProductImporter
{
    /**
     * Create the product, or update it if one with the same handle already
     * exists (upsert).
     *
     * @param  array<string, mixed>  $product  Canonical product produced by the CSV mapper.
     */
    public function importProduct(array $product): ProductImportResult;

    /**
     * Attach a product to the configured collection.
     */
    public function addToCollection(int $productId): void;

    /**
     * The collection every imported product is added to.
     */
    public function collectionId(): int;
}

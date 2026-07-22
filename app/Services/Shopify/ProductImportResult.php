<?php

declare(strict_types=1);

namespace App\Services\Shopify;

/**
 * Result of upserting a single product into Shopify.
 */
final class ProductImportResult
{
    public function __construct(
        public readonly int $id,
        public readonly bool $created,
    ) {}
}

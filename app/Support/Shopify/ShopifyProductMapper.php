<?php

declare(strict_types=1);

namespace App\Support\Shopify;

use Illuminate\Support\Str;

/**
 * Maps a row from a standard Shopify product CSV export to the payload expected
 * by the Admin API `POST /products.json` endpoint.
 *
 * Header lookups are tolerant of casing and punctuation, so both the canonical
 * export headers (e.g. "Body (HTML)", "Type") and the shorthand variants
 * (e.g. "Body HTML", "Product Type") resolve to the same field.
 */
class ShopifyProductMapper
{
    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    public static function map(array $row): array
    {
        $fields = self::normalizeKeys($row);

        $title = self::pick($fields, 'title');
        $handle = self::pick($fields, 'handle') ?? ($title !== null ? Str::slug($title) : null);

        $product = array_filter([
            'handle' => $handle,
            'title' => $title,
            'body_html' => self::pick($fields, 'bodyhtml'),
            'vendor' => self::pick($fields, 'vendor'),
            'product_type' => self::pick($fields, 'type', 'producttype'),
            'tags' => self::pick($fields, 'tags'),
            'status' => self::isTruthy(self::pick($fields, 'published')) ? 'active' : 'draft',
        ], static fn ($value) => $value !== null);

        $product['variants'] = [self::mapVariant($fields)];

        $image = self::mapImage($fields);
        if ($image !== []) {
            $product['images'] = [$image];
        }

        return $product;
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return array<string, mixed>
     */
    private static function mapImage(array $fields): array
    {
        return array_filter([
            'src' => self::pick($fields, 'imagesrc'),
            'position' => self::intOrNull(self::pick($fields, 'imageposition')),
            'alt' => self::pick($fields, 'imagealttext'),
        ], static fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, string|null>  $fields
     * @return array<string, mixed>
     */
    private static function mapVariant(array $fields): array
    {
        $inventoryTracker = self::pick($fields, 'variantinventorytracker', 'inventorytracker');

        return array_filter([
            'sku' => self::pick($fields, 'variantsku', 'sku'),
            'price' => self::pick($fields, 'variantprice', 'price'),
            'compare_at_price' => self::pick($fields, 'variantcompareatprice', 'compareatprice'),
            'requires_shipping' => self::boolOrNull(self::pick($fields, 'variantrequiresshipping', 'requiresshipping')),
            'taxable' => self::boolOrNull(self::pick($fields, 'varianttaxable', 'taxable')),
            'inventory_management' => $inventoryTracker !== null ? 'shopify' : null,
            'inventory_quantity' => self::intOrNull(self::pick($fields, 'variantinventoryqty', 'inventoryqty')),
            'inventory_policy' => self::pick($fields, 'variantinventorypolicy', 'inventorypolicy'),
            'fulfillment_service' => self::pick($fields, 'variantfulfillmentservice', 'fulfillmentservice'),
            'weight' => self::floatOrNull(self::pick($fields, 'variantweight', 'weight')),
            'weight_unit' => self::pick($fields, 'variantweightunit', 'weightunit'),
        ], static fn ($value) => $value !== null);
    }

    /**
     * Reduce header keys to a comparable form: lowercase, alphanumeric only.
     *
     * @param  array<string, string|null>  $row
     * @return array<string, string|null>
     */
    private static function normalizeKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = preg_replace('/[^a-z0-9]/', '', strtolower((string) $key)) ?? '';
            $value = is_string($value) ? trim($value) : $value;
            $normalized[$normalizedKey] = ($value === '') ? null : $value;
        }

        return $normalized;
    }

    /**
     * @param  array<string, string|null>  $fields
     */
    private static function pick(array $fields, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                return $fields[$key];
            }
        }

        return null;
    }

    private static function isTruthy(?string $value): bool
    {
        return in_array(strtolower((string) $value), ['true', '1', 'yes'], true);
    }

    private static function boolOrNull(?string $value): ?bool
    {
        return $value === null ? null : self::isTruthy($value);
    }

    private static function intOrNull(?string $value): ?int
    {
        return $value === null || ! is_numeric($value) ? null : (int) $value;
    }

    private static function floatOrNull(?string $value): ?float
    {
        return $value === null || ! is_numeric($value) ? null : (float) $value;
    }
}

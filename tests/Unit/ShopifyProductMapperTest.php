<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Shopify\ShopifyProductMapper;
use PHPUnit\Framework\TestCase;

class ShopifyProductMapperTest extends TestCase
{
    public function test_it_maps_standard_shopify_columns_to_the_api_payload(): void
    {
        $product = ShopifyProductMapper::map([
            'Handle' => 'classic-tee',
            'Title' => 'Classic Tee',
            'Body (HTML)' => '<p>Soft</p>',
            'Vendor' => 'Acme',
            'Type' => 'Shirts',
            'Tags' => 'cotton,summer',
            'Published' => 'TRUE',
            'Variant SKU' => 'TEE-001',
            'Variant Price' => '19.99',
            'Variant Compare At Price' => '29.99',
            'Variant Requires Shipping' => 'TRUE',
            'Variant Taxable' => 'FALSE',
            'Variant Inventory Tracker' => 'shopify',
            'Variant Inventory Qty' => '120',
            'Variant Inventory Policy' => 'deny',
        ]);

        $this->assertSame('classic-tee', $product['handle']);
        $this->assertSame('Classic Tee', $product['title']);
        $this->assertSame('<p>Soft</p>', $product['body_html']);
        $this->assertSame('Acme', $product['vendor']);
        $this->assertSame('Shirts', $product['product_type']);
        $this->assertSame('cotton,summer', $product['tags']);
        $this->assertSame('active', $product['status']);

        $variant = $product['variants'][0];
        $this->assertSame('TEE-001', $variant['sku']);
        $this->assertSame('19.99', $variant['price']);
        $this->assertSame('29.99', $variant['compare_at_price']);
        $this->assertTrue($variant['requires_shipping']);
        $this->assertFalse($variant['taxable']);
        $this->assertSame('shopify', $variant['inventory_management']);
        $this->assertSame(120, $variant['inventory_quantity']);
        $this->assertSame('deny', $variant['inventory_policy']);
    }

    public function test_it_accepts_shorthand_headers_and_is_case_insensitive(): void
    {
        $product = ShopifyProductMapper::map([
            'title' => 'Shorthand',
            'Body HTML' => '<p>Body</p>',
            'Product Type' => 'Misc',
            'Variant Price' => '5.00',
        ]);

        $this->assertSame('<p>Body</p>', $product['body_html']);
        $this->assertSame('Misc', $product['product_type']);
        $this->assertSame('5.00', $product['variants'][0]['price']);
    }

    public function test_it_derives_a_handle_from_the_title_when_missing(): void
    {
        $product = ShopifyProductMapper::map([
            'Title' => 'No Handle Here',
            'Variant Price' => '1.00',
        ]);

        $this->assertSame('no-handle-here', $product['handle']);
    }

    public function test_unpublished_products_are_drafts(): void
    {
        $product = ShopifyProductMapper::map([
            'Title' => 'Draft',
            'Published' => 'FALSE',
            'Variant Price' => '1.00',
        ]);

        $this->assertSame('draft', $product['status']);
    }

    public function test_blank_optional_values_are_omitted_from_the_payload(): void
    {
        $product = ShopifyProductMapper::map([
            'Title' => 'Sparse',
            'Variant Price' => '1.00',
            'Variant Compare At Price' => '',
            'Vendor' => '',
        ]);

        $this->assertArrayNotHasKey('vendor', $product);
        $this->assertArrayNotHasKey('compare_at_price', $product['variants'][0]);
    }

    public function test_it_maps_images_weight_and_fulfillment_service(): void
    {
        $product = ShopifyProductMapper::map([
            'Title' => 'Full',
            'Variant Price' => '9.99',
            'Variant Fulfillment Service' => 'manual',
            'Variant Weight' => '1.2',
            'Variant Weight Unit' => 'kg',
            'Image Src' => 'https://example.com/a.jpg',
            'Image Position' => '1',
            'Image Alt Text' => 'A photo',
        ]);

        $this->assertSame('manual', $product['variants'][0]['fulfillment_service']);
        $this->assertSame(1.2, $product['variants'][0]['weight']);
        $this->assertSame('kg', $product['variants'][0]['weight_unit']);

        $this->assertSame([
            'src' => 'https://example.com/a.jpg',
            'position' => 1,
            'alt' => 'A photo',
        ], $product['images'][0]);
    }
}

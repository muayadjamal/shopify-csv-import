<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Shopify\GraphqlShopifyImporter;
use App\Support\Shopify\ShopifyProductMapper;
use PHPUnit\Framework\TestCase;

class GraphqlShopifyImporterTest extends TestCase
{
    public function test_it_builds_a_product_set_input_from_a_mapped_product(): void
    {
        $product = ShopifyProductMapper::map([
            'Handle' => 'desk-lamp',
            'Title' => 'Desk Lamp',
            'Body HTML' => '<p>Bright</p>',
            'Vendor' => 'LightCraft',
            'Type' => 'Lighting',
            'Tags' => 'desk,office',
            'Published' => 'TRUE',
            'Variant SKU' => 'MDL-001',
            'Variant Price' => '39.99',
            'Variant Compare At Price' => '49.99',
            'Variant Taxable' => 'TRUE',
            'Variant Inventory Policy' => 'deny',
            'Variant Weight' => '1.2',
            'Variant Weight Unit' => 'kg',
            'Image Src' => 'https://example.com/lamp.jpg',
            'Image Alt Text' => 'Lamp',
        ]);

        $input = GraphqlShopifyImporter::buildProductSetInput($product);

        $this->assertSame('desk-lamp', $input['handle']);
        $this->assertSame('Desk Lamp', $input['title']);
        $this->assertSame('<p>Bright</p>', $input['descriptionHtml']);
        $this->assertSame('Lighting', $input['productType']);
        $this->assertSame('ACTIVE', $input['status']);
        $this->assertSame(['desk', 'office'], $input['tags']);

        // Single default variant with an option value.
        $variant = $input['variants'][0];
        $this->assertSame('MDL-001', $variant['sku']);
        $this->assertSame('39.99', $variant['price']);
        $this->assertSame('49.99', $variant['compareAtPrice']);
        $this->assertSame('DENY', $variant['inventoryPolicy']);
        $this->assertSame([['optionName' => 'Title', 'name' => 'Default Title']], $variant['optionValues']);
        $this->assertSame('KILOGRAMS', $variant['inventoryItem']['measurement']['weight']['unit']);
        $this->assertSame(1.2, $variant['inventoryItem']['measurement']['weight']['value']);

        // Image mapped to a file.
        $this->assertSame('https://example.com/lamp.jpg', $input['files'][0]['originalSource']);
        $this->assertSame('IMAGE', $input['files'][0]['contentType']);
    }

    public function test_draft_products_map_to_draft_status(): void
    {
        $product = ShopifyProductMapper::map([
            'Title' => 'Hidden',
            'Published' => 'FALSE',
            'Variant Price' => '1.00',
        ]);

        $input = GraphqlShopifyImporter::buildProductSetInput($product);

        $this->assertSame('DRAFT', $input['status']);
    }
}

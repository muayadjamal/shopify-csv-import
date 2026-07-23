<?php

use App\Services\Shopify\GraphqlShopifyImporter;
use App\Support\Shopify\ShopifyProductMapper;

test('it builds a productSet input from a mapped product', function () {
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

    expect($input)
        ->handle->toBe('desk-lamp')
        ->title->toBe('Desk Lamp')
        ->descriptionHtml->toBe('<p>Bright</p>')
        ->productType->toBe('Lighting')
        ->status->toBe('ACTIVE')
        ->tags->toBe(['desk', 'office']);

    expect($input['variants'][0])
        ->sku->toBe('MDL-001')
        ->price->toBe('39.99')
        ->compareAtPrice->toBe('49.99')
        ->inventoryPolicy->toBe('DENY')
        ->optionValues->toBe([['optionName' => 'Title', 'name' => 'Default Title']]);

    expect($input['variants'][0]['inventoryItem']['measurement']['weight'])
        ->unit->toBe('KILOGRAMS')
        ->value->toBe(1.2);

    expect($input['files'][0])
        ->originalSource->toBe('https://example.com/lamp.jpg')
        ->contentType->toBe('IMAGE');
});

test('draft products map to draft status', function () {
    $product = ShopifyProductMapper::map([
        'Title' => 'Hidden',
        'Published' => 'FALSE',
        'Variant Price' => '1.00',
    ]);

    expect(GraphqlShopifyImporter::buildProductSetInput($product)['status'])->toBe('DRAFT');
});

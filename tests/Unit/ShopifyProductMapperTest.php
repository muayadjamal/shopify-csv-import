<?php

use App\Support\Shopify\ShopifyProductMapper;

test('it maps standard Shopify columns to the api payload', function () {
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

    expect($product)
        ->handle->toBe('classic-tee')
        ->title->toBe('Classic Tee')
        ->body_html->toBe('<p>Soft</p>')
        ->vendor->toBe('Acme')
        ->product_type->toBe('Shirts')
        ->tags->toBe('cotton,summer')
        ->status->toBe('active');

    expect($product['variants'][0])
        ->sku->toBe('TEE-001')
        ->price->toBe('19.99')
        ->compare_at_price->toBe('29.99')
        ->requires_shipping->toBeTrue()
        ->taxable->toBeFalse()
        ->inventory_management->toBe('shopify')
        ->inventory_quantity->toBe(120)
        ->inventory_policy->toBe('deny');
});

test('it accepts shorthand headers and is case-insensitive', function () {
    $product = ShopifyProductMapper::map([
        'title' => 'Shorthand',
        'Body HTML' => '<p>Body</p>',
        'Product Type' => 'Misc',
        'Variant Price' => '5.00',
    ]);

    expect($product)
        ->body_html->toBe('<p>Body</p>')
        ->product_type->toBe('Misc');
    expect($product['variants'][0]['price'])->toBe('5.00');
});

test('it derives a handle from the title when missing', function () {
    $product = ShopifyProductMapper::map([
        'Title' => 'No Handle Here',
        'Variant Price' => '1.00',
    ]);

    expect($product['handle'])->toBe('no-handle-here');
});

test('unpublished products are drafts', function () {
    $product = ShopifyProductMapper::map([
        'Title' => 'Draft',
        'Published' => 'FALSE',
        'Variant Price' => '1.00',
    ]);

    expect($product['status'])->toBe('draft');
});

test('blank optional values are omitted from the payload', function () {
    $product = ShopifyProductMapper::map([
        'Title' => 'Sparse',
        'Variant Price' => '1.00',
        'Variant Compare At Price' => '',
        'Vendor' => '',
    ]);

    expect($product)->not->toHaveKey('vendor');
    expect($product['variants'][0])->not->toHaveKey('compare_at_price');
});

test('it maps images, weight and fulfillment service', function () {
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

    expect($product['variants'][0])
        ->fulfillment_service->toBe('manual')
        ->weight->toBe(1.2)
        ->weight_unit->toBe('kg');

    expect($product['images'][0])->toBe([
        'src' => 'https://example.com/a.jpg',
        'position' => 1,
        'alt' => 'A photo',
    ]);
});

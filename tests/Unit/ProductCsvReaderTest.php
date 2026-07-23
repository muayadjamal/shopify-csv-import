<?php

use App\Support\Csv\ProductCsvReader;

$fixture = __DIR__.'/../Fixtures/products.csv';

test('it counts data rows excluding the header', function () use ($fixture) {
    expect(ProductCsvReader::fromPath($fixture)->count())->toBe(3);
});

test('it yields rows keyed by header with 1-based numbers', function () use ($fixture) {
    $rows = iterator_to_array(ProductCsvReader::fromPath($fixture)->rows());

    expect(array_keys($rows))->toBe([1, 2, 3])
        ->and($rows[1]['Title'])->toBe('Good One')
        ->and($rows[2]['Variant SKU'])->toBe('SKU2');
});

test('it exposes the header row', function () use ($fixture) {
    $headers = ProductCsvReader::fromPath($fixture)->headers();

    expect($headers)->toContain('Handle', 'Variant Price');
});

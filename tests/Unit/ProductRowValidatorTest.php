<?php

use App\Exceptions\InvalidProductRowException;
use App\Support\Shopify\ProductRowValidator;
use Tests\TestCase;

// Uses the Laravel Validator facade, so boot the framework for this file.
uses(TestCase::class);

test('it passes for a valid payload', function () {
    expect(fn () => ProductRowValidator::validate([
        'title' => 'Valid',
        'variants' => [['price' => '9.99']],
    ]))->not->toThrow(InvalidProductRowException::class);
});

test('it rejects a missing title', function () {
    ProductRowValidator::validate([
        'variants' => [['price' => '9.99']],
    ]);
})->throws(InvalidProductRowException::class, 'Title is required.');

test('it rejects a missing or non-numeric price', function () {
    ProductRowValidator::validate([
        'title' => 'No price',
        'variants' => [[]],
    ]);
})->throws(InvalidProductRowException::class);

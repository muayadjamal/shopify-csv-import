<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\InvalidProductRowException;
use App\Support\Shopify\ProductRowValidator;
use Tests\TestCase;

class ProductRowValidatorTest extends TestCase
{
    public function test_it_passes_for_a_valid_payload(): void
    {
        ProductRowValidator::validate([
            'title' => 'Valid',
            'variants' => [['price' => '9.99']],
        ]);

        $this->expectNotToPerformAssertions();
    }

    public function test_it_rejects_a_missing_title(): void
    {
        $this->expectException(InvalidProductRowException::class);
        $this->expectExceptionMessage('Title is required.');

        ProductRowValidator::validate([
            'variants' => [['price' => '9.99']],
        ]);
    }

    public function test_it_rejects_a_missing_or_non_numeric_price(): void
    {
        $this->expectException(InvalidProductRowException::class);

        ProductRowValidator::validate([
            'title' => 'No price',
            'variants' => [[]],
        ]);
    }
}

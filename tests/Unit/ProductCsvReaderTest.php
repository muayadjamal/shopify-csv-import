<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Csv\ProductCsvReader;
use PHPUnit\Framework\TestCase;

class ProductCsvReaderTest extends TestCase
{
    private string $fixture = __DIR__.'/../Fixtures/products.csv';

    public function test_it_counts_data_rows_excluding_the_header(): void
    {
        $reader = ProductCsvReader::fromPath($this->fixture);

        $this->assertSame(3, $reader->count());
    }

    public function test_it_yields_rows_keyed_by_header_with_1_based_numbers(): void
    {
        $reader = ProductCsvReader::fromPath($this->fixture);

        $rows = iterator_to_array($reader->rows());

        $this->assertSame([1, 2, 3], array_keys($rows));
        $this->assertSame('Good One', $rows[1]['Title']);
        $this->assertSame('SKU2', $rows[2]['Variant SKU']);
    }

    public function test_it_exposes_the_header_row(): void
    {
        $reader = ProductCsvReader::fromPath($this->fixture);

        $this->assertContains('Handle', $reader->headers());
        $this->assertContains('Variant Price', $reader->headers());
    }
}

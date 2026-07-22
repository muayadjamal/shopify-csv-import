<?php

declare(strict_types=1);

namespace App\Support\Csv;

use League\Csv\Reader;

/**
 * Streams a Shopify product CSV file row by row, keyed by header.
 *
 * The file is never loaded fully into memory, so large exports import safely.
 */
class ProductCsvReader
{
    /**
     * @param  Reader<array<string, string|null>>  $reader
     */
    private function __construct(private readonly Reader $reader) {}

    public static function fromPath(string $path): self
    {
        $reader = Reader::from($path, 'r');
        $reader->setHeaderOffset(0);
        $reader->skipInputBOM();

        return new self($reader);
    }

    /**
     * Number of data rows (excludes the header row).
     */
    public function count(): int
    {
        return count($this->reader);
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return array_values($this->reader->getHeader());
    }

    /**
     * Yield each data row as `[rowNumber => record]`, where rowNumber is a
     * 1-based index over data rows (the header is row 0).
     *
     * @return iterable<int, array<string, string|null>>
     */
    public function rows(): iterable
    {
        $rowNumber = 0;

        foreach ($this->reader->getRecords() as $record) {
            yield ++$rowNumber => $record;
        }
    }
}

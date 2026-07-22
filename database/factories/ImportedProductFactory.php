<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductImportStatus;
use App\Models\ImportedProduct;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportedProduct>
 */
class ImportedProductFactory extends Factory
{
    protected $model = ImportedProduct::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'row_number' => fake()->unique()->numberBetween(1, 1000),
            'handle' => fake()->slug(),
            'title' => fake()->words(3, true),
            'sku' => strtoupper(fake()->bothify('SKU-####')),
            'status' => ProductImportStatus::Success,
            'shopify_product_id' => fake()->numberBetween(1_000_000, 9_999_999),
            'error_message' => null,
        ];
    }

    public function failed(string $error = 'Validation failed'): static
    {
        return $this->state(fn () => [
            'status' => ProductImportStatus::Failed,
            'shopify_product_id' => null,
            'error_message' => $error,
        ]);
    }
}

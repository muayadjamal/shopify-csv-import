<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LogLevel;
use App\Models\ImportLog;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportLog>
 */
class ImportLogFactory extends Factory
{
    protected $model = ImportLog::class;

    public function definition(): array
    {
        return [
            'import_run_id' => ImportRun::factory(),
            'imported_product_id' => null,
            'level' => LogLevel::Info,
            'event' => 'row.created',
            'message' => fake()->sentence(),
            'context' => [],
        ];
    }
}

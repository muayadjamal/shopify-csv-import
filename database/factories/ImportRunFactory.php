<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\ImportRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportRun>
 */
class ImportRunFactory extends Factory
{
    protected $model = ImportRun::class;

    public function definition(): array
    {
        return [
            'original_filename' => fake()->slug().'.csv',
            'disk' => 'local',
            'path' => 'imports/'.fake()->uuid().'.csv',
            'status' => ImportStatus::Pending,
            'total_rows' => null,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
        ];
    }

    public function completed(int $total = 10): static
    {
        return $this->state(fn () => [
            'status' => ImportStatus::Completed,
            'total_rows' => $total,
            'processed_rows' => $total,
            'successful_rows' => $total,
            'failed_rows' => 0,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => ImportStatus::Failed,
            'error_message' => 'The uploaded file could not be parsed.',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImportStatus;
use Database\Factories\ImportRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single CSV upload and its asynchronous import into Shopify.
 *
 * @property int $id
 * @property string $original_filename
 * @property string $disk
 * @property string $path
 * @property ImportStatus $status
 * @property int|null $total_rows
 * @property int $processed_rows
 * @property int $successful_rows
 * @property int $failed_rows
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class ImportRun extends Model
{
    /** @use HasFactory<ImportRunFactory> */
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'disk',
        'path',
        'status',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'successful_rows' => 'integer',
            'failed_rows' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ImportedProduct, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(ImportedProduct::class);
    }

    /**
     * @return HasMany<ImportLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }

    /**
     * Completion percentage (0–100) based on rows processed so far.
     */
    public function progress(): int
    {
        if (! $this->total_rows) {
            return $this->status === ImportStatus::Completed ? 100 : 0;
        }

        return (int) floor(($this->processed_rows / $this->total_rows) * 100);
    }
}

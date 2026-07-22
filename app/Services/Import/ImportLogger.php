<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\LogLevel;
use App\Models\ImportedProduct;
use App\Models\ImportLog;
use App\Models\ImportRun;
use Illuminate\Support\Facades\Log;

/**
 * Records import events for a single run to both the `import_logs` table
 * (surfaced in the dashboard) and a dedicated `import` log channel on disk.
 */
class ImportLogger
{
    public function __construct(private readonly ImportRun $run) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $event, string $message, array $context = [], ?ImportedProduct $product = null): ImportLog
    {
        return $this->log(LogLevel::Info, $event, $message, $context, $product);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $event, string $message, array $context = [], ?ImportedProduct $product = null): ImportLog
    {
        return $this->log(LogLevel::Warning, $event, $message, $context, $product);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $event, string $message, array $context = [], ?ImportedProduct $product = null): ImportLog
    {
        return $this->log(LogLevel::Error, $event, $message, $context, $product);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(LogLevel $level, string $event, string $message, array $context = [], ?ImportedProduct $product = null): ImportLog
    {
        $context = [
            'import_run_id' => $this->run->id,
            ...$context,
        ];

        Log::channel('import')->log($level->value, "[run {$this->run->id}] {$event}: {$message}", $context);

        return $this->run->logs()->create([
            'imported_product_id' => $product?->id,
            'level' => $level,
            'event' => $event,
            'message' => $message,
            'context' => $context,
        ]);
    }
}

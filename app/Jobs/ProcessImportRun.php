<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Enums\ProductImportStatus;
use App\Exceptions\InvalidProductRowException;
use App\Models\ImportedProduct;
use App\Models\ImportRun;
use App\Notifications\ImportFailuresNotification;
use App\Services\Import\ImportLogger;
use App\Services\Shopify\Contracts\ShopifyProductImporter;
use App\Services\Shopify\ShopifyException;
use App\Support\Csv\ProductCsvReader;
use App\Support\Shopify\ProductRowValidator;
use App\Support\Shopify\ShopifyProductMapper;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Parses an uploaded product CSV and imports every row into Shopify, upserting
 * each product (create or update by handle) and attaching it to the configured
 * collection.
 *
 * Retry-safe: per-row results are keyed by row number, already-successful rows
 * are skipped, upserts never duplicate a product, and counters are recomputed
 * from stored results. Every step is logged to the import log.
 */
class ProcessImportRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 3600;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    private ImportLogger $logger;

    public function __construct(public readonly ImportRun $importRun) {}

    public function handle(ShopifyProductImporter $importer): void
    {
        $run = $this->importRun;
        $this->logger = new ImportLogger($run);

        $run->update([
            'status' => ImportStatus::Processing,
            'started_at' => $run->started_at ?? now(),
            'error_message' => null,
        ]);

        $reader = ProductCsvReader::fromPath(Storage::disk($run->disk)->path($run->path));
        $run->update(['total_rows' => $reader->count()]);

        $this->logger->info('run.started', "Import started for {$run->original_filename} ({$reader->count()} rows).");

        foreach ($reader->rows() as $rowNumber => $record) {
            $this->importRow($importer, $rowNumber, $record);
            $this->refreshCounts();
        }

        $run->update([
            'status' => ImportStatus::Completed,
            'completed_at' => now(),
        ]);

        $this->logger->info('run.completed', "Import completed: {$run->successful_rows} succeeded, {$run->failed_rows} failed.");

        $this->notifyOfFailures();
    }

    /**
     * @param  array<string, string|null>  $record
     */
    private function importRow(ShopifyProductImporter $importer, int $rowNumber, array $record): void
    {
        $existing = $this->importRun->products()->firstWhere('row_number', $rowNumber);

        if ($existing?->status === ProductImportStatus::Success) {
            $this->logger->info('row.skipped', "Row {$rowNumber} already imported; skipping.", [], $existing);

            return;
        }

        $product = ShopifyProductMapper::map($record);

        $row = $this->recordRow($rowNumber, [
            'handle' => $product['handle'] ?? null,
            'title' => $product['title'] ?? null,
            'sku' => $product['variants'][0]['sku'] ?? null,
            'shopify_product_id' => $existing?->shopify_product_id,
            'payload' => $product,
            'status' => ProductImportStatus::Processing,
            'error_message' => null,
        ]);

        try {
            ProductRowValidator::validate($product);

            $result = $importer->importProduct($product);
            $importer->addToCollection($result->id);

            $row->update([
                'status' => ProductImportStatus::Success,
                'shopify_product_id' => $result->id,
                'error_message' => null,
            ]);

            $event = $result->created ? 'row.created' : 'row.updated';
            $verb = $result->created ? 'Created' : 'Updated';
            $this->logger->info($event, "{$verb} “{$row->title}” (Shopify #{$result->id}).", [
                'shopify_product_id' => $result->id,
            ], $row);
        } catch (InvalidProductRowException|ShopifyException $e) {
            $row->update([
                'status' => ProductImportStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            $this->logger->error('row.failed', "Row {$rowNumber} failed: {$e->getMessage()}", [
                'exception' => $e::class,
            ], $row);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordRow(int $rowNumber, array $attributes): ImportedProduct
    {
        return $this->importRun->products()->updateOrCreate(
            ['row_number' => $rowNumber],
            $attributes,
        );
    }

    /**
     * Recompute run counters from the persisted per-row results so progress is
     * accurate even across retries.
     */
    private function refreshCounts(): void
    {
        $this->importRun->update([
            'processed_rows' => $this->importRun->products()
                ->whereNot('status', ProductImportStatus::Processing)->count(),
            'successful_rows' => $this->importRun->products()
                ->where('status', ProductImportStatus::Success)->count(),
            'failed_rows' => $this->importRun->products()
                ->where('status', ProductImportStatus::Failed)->count(),
        ]);
    }

    private function notifyOfFailures(): void
    {
        if ($this->importRun->failed_rows > 0) {
            ImportFailuresNotification::dispatchFor($this->importRun);
        }
    }

    /**
     * Mark the run as failed when the job ultimately gives up (e.g. an
     * unreadable file or repeated fatal errors).
     */
    public function failed(Throwable $exception): void
    {
        $this->importRun->update([
            'status' => ImportStatus::Failed,
            'error_message' => $exception->getMessage(),
            'completed_at' => now(),
        ]);

        (new ImportLogger($this->importRun))->error('run.failed', "Import failed: {$exception->getMessage()}", [
            'exception' => $exception::class,
        ]);

        ImportFailuresNotification::dispatchFor($this->importRun);
    }
}

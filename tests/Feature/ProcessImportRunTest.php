<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\LogLevel;
use App\Enums\ProductImportStatus;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\Shopify\Contracts\ShopifyProductImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeShopifyProductImporter;
use Tests\TestCase;

class ProcessImportRunTest extends TestCase
{
    use RefreshDatabase;

    private FakeShopifyProductImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new FakeShopifyProductImporter;
        $this->app->instance(ShopifyProductImporter::class, $this->importer);
    }

    public function test_it_imports_valid_rows_and_records_validation_failures(): void
    {
        $run = $this->makeRunFromFixture();

        $this->runJob($run);
        $run->refresh();

        $this->assertSame(ImportStatus::Completed, $run->status);
        $this->assertSame(3, $run->total_rows);
        $this->assertSame(3, $run->processed_rows);
        $this->assertSame(2, $run->successful_rows);
        $this->assertSame(1, $run->failed_rows);
        $this->assertNotNull($run->completed_at);

        // Two valid rows reached Shopify and were added to the collection.
        $this->assertCount(2, $this->importer->imported);
        $this->assertCount(2, $this->importer->collectionAdds);

        $failed = $run->products()->where('status', ProductImportStatus::Failed)->sole();
        $this->assertSame('missing-price', $failed->handle);
        $this->assertStringContainsString('Variant price is required', (string) $failed->error_message);
    }

    public function test_it_logs_every_import_event(): void
    {
        $run = $this->makeRunFromFixture();

        $this->runJob($run);

        $events = $run->logs()->pluck('event')->all();
        $this->assertContains('run.started', $events);
        $this->assertContains('row.created', $events);
        $this->assertContains('row.failed', $events);
        $this->assertContains('run.completed', $events);

        $this->assertSame(1, $run->logs()->where('level', LogLevel::Error)->count());
    }

    public function test_it_records_updates_when_the_product_already_exists(): void
    {
        $this->importer->treatAsUpdate = true;
        $run = $this->makeRunFromFixture();

        $this->runJob($run);

        $this->assertTrue($run->logs()->where('event', 'row.updated')->exists());
        $this->assertFalse($run->logs()->where('event', 'row.created')->exists());
    }

    public function test_it_records_shopify_failures_per_row(): void
    {
        $this->importer->failHandles = ['good-one'];
        $run = $this->makeRunFromFixture();

        $this->runJob($run);
        $run->refresh();

        // good-one fails at Shopify, missing-price fails validation, good-two succeeds.
        $this->assertSame(1, $run->successful_rows);
        $this->assertSame(2, $run->failed_rows);

        $failed = $run->products()->where('handle', 'good-one')->sole();
        $this->assertSame(ProductImportStatus::Failed, $failed->status);
        $this->assertStringContainsString('Shopify rejected', (string) $failed->error_message);
    }

    public function test_it_is_idempotent_and_skips_already_imported_rows_on_retry(): void
    {
        $run = $this->makeRunFromFixture();

        $this->runJob($run);
        $this->assertCount(2, $this->importer->imported);

        // Re-running must not re-import the already-successful rows.
        $this->runJob($run);
        $this->assertCount(2, $this->importer->imported);

        $run->refresh();
        $this->assertSame(2, $run->successful_rows);
        $this->assertTrue($run->logs()->where('event', 'row.skipped')->exists());
    }

    public function test_it_notifies_admins_when_a_run_has_failures(): void
    {
        $admin = User::factory()->admin()->create();
        $regular = User::factory()->create();
        $run = $this->makeRunFromFixture();

        $this->runJob($run);

        // Only admins (panel users) are notified — not every user.
        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(0, $regular->notifications()->count());
    }

    public function test_it_marks_the_run_failed_when_the_file_is_unreadable(): void
    {
        $run = ImportRun::factory()->create([
            'disk' => 'local',
            'path' => 'imports/does-not-exist.csv',
        ]);

        $job = new ProcessImportRun($run);

        try {
            $job->handle($this->importer);
        } catch (\Throwable $e) {
            $job->failed($e);
        }

        $run->refresh();
        $this->assertSame(ImportStatus::Failed, $run->status);
        $this->assertNotNull($run->error_message);
        $this->assertTrue($run->logs()->where('event', 'run.failed')->exists());
    }

    private function runJob(ImportRun $run): void
    {
        (new ProcessImportRun($run))->handle($this->importer);
    }

    private function makeRunFromFixture(): ImportRun
    {
        Storage::fake('local');
        Storage::disk('local')->put('imports/products.csv', file_get_contents(__DIR__.'/../Fixtures/products.csv'));

        return ImportRun::factory()->create([
            'disk' => 'local',
            'path' => 'imports/products.csv',
        ]);
    }
}

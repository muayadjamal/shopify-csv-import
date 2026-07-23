<?php

use App\Enums\ImportStatus;
use App\Enums\LogLevel;
use App\Enums\ProductImportStatus;
use App\Jobs\ProcessImportRun;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\Shopify\Contracts\ShopifyProductImporter;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeShopifyProductImporter;

beforeEach(function () {
    $this->importer = new FakeShopifyProductImporter;
    $this->app->instance(ShopifyProductImporter::class, $this->importer);
});

/** Stage the 3-row fixture (2 valid, 1 invalid) on the fake disk and return a run for it. */
function makeRunFromFixture(): ImportRun
{
    Storage::fake('local');
    Storage::disk('local')->put('imports/products.csv', file_get_contents(__DIR__.'/../Fixtures/products.csv'));

    return ImportRun::factory()->create([
        'disk' => 'local',
        'path' => 'imports/products.csv',
    ]);
}

test('it imports valid rows and records validation failures', function () {
    $run = makeRunFromFixture();

    (new ProcessImportRun($run))->handle($this->importer);
    $run->refresh();

    expect($run->status)->toBe(ImportStatus::Completed)
        ->and($run->total_rows)->toBe(3)
        ->and($run->processed_rows)->toBe(3)
        ->and($run->successful_rows)->toBe(2)
        ->and($run->failed_rows)->toBe(1)
        ->and($run->completed_at)->not->toBeNull();

    // Two valid rows reached Shopify and were added to the collection.
    expect($this->importer->imported)->toHaveCount(2)
        ->and($this->importer->collectionAdds)->toHaveCount(2);

    $failed = $run->products()->where('status', ProductImportStatus::Failed)->sole();
    expect($failed->handle)->toBe('missing-price')
        ->and($failed->error_message)->toContain('Variant price is required');
});

test('it logs every import event', function () {
    $run = makeRunFromFixture();

    (new ProcessImportRun($run))->handle($this->importer);

    $events = $run->logs()->pluck('event')->all();

    expect($events)->toContain('run.started', 'row.created', 'row.failed', 'run.completed');
    expect($run->logs()->where('level', LogLevel::Error)->count())->toBe(1);
});

test('it records updates when the product already exists', function () {
    $this->importer->treatAsUpdate = true;
    $run = makeRunFromFixture();

    (new ProcessImportRun($run))->handle($this->importer);

    expect($run->logs()->where('event', 'row.updated')->exists())->toBeTrue()
        ->and($run->logs()->where('event', 'row.created')->exists())->toBeFalse();
});

test('it records Shopify failures per row', function () {
    $this->importer->failHandles = ['good-one'];
    $run = makeRunFromFixture();

    (new ProcessImportRun($run))->handle($this->importer);
    $run->refresh();

    // good-one fails at Shopify, missing-price fails validation, good-two succeeds.
    expect($run->successful_rows)->toBe(1)
        ->and($run->failed_rows)->toBe(2);

    $failed = $run->products()->where('handle', 'good-one')->sole();
    expect($failed->status)->toBe(ProductImportStatus::Failed)
        ->and($failed->error_message)->toContain('Shopify rejected');
});

test('it is idempotent and skips already-imported rows on retry', function () {
    $run = makeRunFromFixture();

    (new ProcessImportRun($run))->handle($this->importer);
    expect($this->importer->imported)->toHaveCount(2);

    // Re-running must not re-import the already-successful rows.
    (new ProcessImportRun($run))->handle($this->importer);
    expect($this->importer->imported)->toHaveCount(2);

    $run->refresh();
    expect($run->successful_rows)->toBe(2)
        ->and($run->logs()->where('event', 'row.skipped')->exists())->toBeTrue();
});

test('it notifies admins when a run has failures', function () {
    $admin = User::factory()->admin()->create();
    $regular = User::factory()->create();
    $run = makeRunFromFixture();

    (new ProcessImportRun($run))->handle($this->importer);

    // Only admins (panel users) are notified — not every user.
    expect($admin->notifications()->count())->toBe(1)
        ->and($regular->notifications()->count())->toBe(0);
});

test('it marks the run failed when the file is unreadable', function () {
    $run = ImportRun::factory()->create([
        'disk' => 'local',
        'path' => 'imports/does-not-exist.csv',
    ]);

    $job = new ProcessImportRun($run);

    try {
        $job->handle($this->importer);
    } catch (Throwable $e) {
        $job->failed($e);
    }

    $run->refresh();
    expect($run->status)->toBe(ImportStatus::Failed)
        ->and($run->error_message)->not->toBeNull()
        ->and($run->logs()->where('event', 'run.failed')->exists())->toBeTrue();
});

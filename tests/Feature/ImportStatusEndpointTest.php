<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImportedProduct;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_run_status_as_a_json_resource(): void
    {
        $run = ImportRun::factory()->completed(2)->create();
        ImportedProduct::factory()->for($run)->create(['row_number' => 1]);
        ImportedProduct::factory()->for($run)->failed()->create(['row_number' => 2]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('imports.status', $run))
            ->assertOk()
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonPath('data.status.value', 'completed')
            ->assertJsonPath('data.status.is_finished', true)
            ->assertJsonPath('data.totals.total', 2)
            ->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.products.0.status.value', 'success')
            ->assertJsonPath('data.products.1.status.value', 'failed');
    }
}

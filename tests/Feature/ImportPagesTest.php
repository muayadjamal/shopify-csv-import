<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ImportedProduct;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ImportPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_index_page_lists_runs(): void
    {
        ImportRun::factory()->completed()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('imports.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('imports/index')
                ->has('runs', 1)
                ->has('runs.0.status.value'),
            );
    }

    public function test_the_show_page_exposes_a_flat_run_with_a_products_array(): void
    {
        $run = ImportRun::factory()->completed(2)->create();
        ImportedProduct::factory()->for($run)->create(['row_number' => 1]);
        ImportedProduct::factory()->for($run)->create(['row_number' => 2]);

        $this->actingAs(User::factory()->create())
            ->get(route('imports.show', $run))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('imports/show')
                ->where('run.id', $run->id)
                ->where('run.status.is_finished', true)
                ->has('run.products', 2)
                ->has('run.products.0.status.value'),
            );
    }
}

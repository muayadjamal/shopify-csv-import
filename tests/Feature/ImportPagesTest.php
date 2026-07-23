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

    public function test_the_index_page_lists_the_current_users_runs(): void
    {
        $user = User::factory()->create();
        ImportRun::factory()->for($user)->completed()->create();
        // A run owned by someone else must not appear.
        ImportRun::factory()->completed()->create();

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('imports/index')
                ->has('runs', 1)
                ->has('runs.0.status.value'),
            );
    }

    public function test_a_user_cannot_view_another_users_run(): void
    {
        $run = ImportRun::factory()->completed()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('imports.show', $run))
            ->assertForbidden();
    }

    public function test_the_show_page_exposes_a_flat_run_with_a_products_array(): void
    {
        $user = User::factory()->create();
        $run = ImportRun::factory()->for($user)->completed(2)->create();
        ImportedProduct::factory()->for($run)->create(['row_number' => 1]);
        ImportedProduct::factory()->for($run)->create(['row_number' => 2]);

        $this->actingAs($user)
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

<?php

use App\Models\ImportedProduct;
use App\Models\ImportRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the index page lists only the current user\'s runs', function () {
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
});

test('a user cannot view another user\'s run', function () {
    $run = ImportRun::factory()->completed()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('imports.show', $run))
        ->assertForbidden();
});

test('the show page exposes a flat run with a products array', function () {
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
});

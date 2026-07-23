<?php

use App\Models\ImportedProduct;
use App\Models\ImportRun;
use App\Models\User;

test('it returns the run status as a json resource', function () {
    $user = User::factory()->create();
    $run = ImportRun::factory()->for($user)->completed(2)->create();
    ImportedProduct::factory()->for($run)->create(['row_number' => 1]);
    ImportedProduct::factory()->for($run)->failed()->create(['row_number' => 2]);

    $this->actingAs($user)
        ->getJson(route('imports.status', $run))
        ->assertOk()
        ->assertJsonPath('data.id', $run->id)
        ->assertJsonPath('data.status.value', 'completed')
        ->assertJsonPath('data.status.is_finished', true)
        ->assertJsonPath('data.totals.total', 2)
        ->assertJsonCount(2, 'data.products')
        ->assertJsonPath('data.products.0.status.value', 'success')
        ->assertJsonPath('data.products.1.status.value', 'failed');
});

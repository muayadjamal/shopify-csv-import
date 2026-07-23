<?php

use Laravel\Fortify\Features;

test('registration is disabled', function () {
    // This is an internal tool: public registration is intentionally off.
    expect(Features::enabled(Features::registration()))->toBeFalse();
});

test('the registration screen is not available', function () {
    $this->get('/register')->assertNotFound();
});

test('the registration endpoint is not available', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'intruder@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();

    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'intruder@example.com']);
});

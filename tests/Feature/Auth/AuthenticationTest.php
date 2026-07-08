<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('inertia login forces a full reload for fresh csrf token', function () {
    $user = User::factory()->create();

    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

    $this->assertAuthenticated();
    $response
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('dashboard'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('inertia logout forces a full reload for fresh csrf token', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->post('/logout');

    $this->assertGuest();
    $response
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/'));
});

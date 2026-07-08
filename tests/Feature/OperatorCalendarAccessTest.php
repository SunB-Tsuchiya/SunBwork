<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('operator calendar is accessible to permitted roles', function (string $role) {
    $user = User::factory()->create(['user_role' => $role]);

    $response = $this
        ->actingAs($user)
        ->get(route('coordinator.operator_calendar.index'));

    $response->assertOk();
})->with([
    'superadmin',
    'admin',
    'leader',
    'clerk',
    'coordinator',
]);

test('operator calendar is forbidden to regular users', function () {
    $user = User::factory()->create(['user_role' => 'user']);

    $response = $this
        ->actingAs($user)
        ->get(route('coordinator.operator_calendar.index'));

    $response->assertForbidden();
});

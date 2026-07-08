<?php

use App\Models\Company;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function proofAccessCompany(string $type = 'sunbrain'): Company
{
    return Company::create([
        'name' => $type === 'sunbrain' ? 'Sunbrain' : 'General',
        'code' => strtoupper($type) . fake()->unique()->numerify('###'),
        'company_type' => $type,
    ]);
}

test('proof coordinator dashboard is accessible to proof admins and admins', function (string $role) {
    $company = proofAccessCompany('sunbrain');
    $user = User::factory()->create([
        'user_role' => $role,
        'company_id' => $company->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('proof_coordinator.dashboard'));

    $response->assertOk();
})->with([
    'proof_coordinator',
    'admin',
    'superadmin',
]);

test('leader sees the forbidden page for proof coordinator dashboard', function () {
    $company = proofAccessCompany('sunbrain');
    $user = User::factory()->create([
        'user_role' => 'leader',
        'company_id' => $company->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('proof_coordinator.dashboard'));

    $response
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Errors/403', false));
});

test('proof admin outside sunbrain company sees the forbidden page', function () {
    $company = proofAccessCompany('general');
    $user = User::factory()->create([
        'user_role' => 'proof_coordinator',
        'company_id' => $company->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('proof_coordinator.dashboard'));

    $response
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Errors/403', false));
});

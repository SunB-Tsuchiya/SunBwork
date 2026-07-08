<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function prepressAccessCompany(string $type = 'sunbrain'): Company
{
    return Company::create([
        'name' => $type === 'sunbrain' ? 'Sunbrain' : 'General',
        'code' => 'PREPRESS' . strtoupper($type) . fake()->unique()->numerify('###'),
        'company_type' => $type,
    ]);
}

function prepressAccessDepartment(Company $company): Department
{
    return Department::create([
        'company_id' => $company->id,
        'name' => '製版',
        'module' => 'prepress',
        'code' => 'prepress',
        'active' => true,
    ]);
}

test('prepress dashboard is accessible to super admin', function () {
    $user = User::factory()->create([
        'user_role' => 'superadmin',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('prepress.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Prepress/Dashboard', false));
});

test('prepress dashboard is accessible to admin in the same prepress company', function () {
    $company = prepressAccessCompany('sunbrain');
    prepressAccessDepartment($company);

    $user = User::factory()->create([
        'user_role' => 'admin',
        'company_id' => $company->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('prepress.dashboard'));

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Prepress/Dashboard', false));
});

test('prepress dashboard is forbidden to admin outside sunbrain company', function () {
    $company = prepressAccessCompany('general');
    prepressAccessDepartment($company);

    $user = User::factory()->create([
        'user_role' => 'admin',
        'company_id' => $company->id,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('prepress.dashboard'));

    $response
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('Errors/403', false));
});

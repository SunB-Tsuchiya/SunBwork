<?php

use App\Models\Company;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows a super admin to set their company and department membership', function () {
    $superAdminCompany = Company::create([
        'name' => 'Superadmin Company',
        'code' => 'SUPERADMIN',
        'company_type' => 'general',
        'active' => true,
    ]);
    $company = Company::create([
        'name' => '株式会社サン・ブレーン',
        'code' => 'SUNBRAIN',
        'company_type' => 'sunbrain',
        'active' => true,
    ]);
    $department = Department::create([
        'company_id' => $company->id,
        'name' => '情報出版',
        'code' => 'INFO',
        'active' => true,
    ]);
    $superAdmin = User::factory()->create([
        'user_role' => 'superadmin',
        'company_id' => $superAdminCompany->id,
    ]);
    $adminTeam = Team::create([
        'user_id' => $superAdmin->id,
        'name' => 'Superadmin Team',
        'personal_team' => false,
        'team_type' => 'admin',
    ]);
    $departmentTeam = Team::create([
        'user_id' => $superAdmin->id,
        'name' => '情報出版',
        'personal_team' => false,
        'company_id' => $company->id,
        'department_id' => $department->id,
        'team_type' => 'department',
    ]);
    $superAdmin->teams()->attach($adminTeam->id, ['role' => 'owner']);

    $response = $this->actingAs($superAdmin)->put(route('superadmin.membership.update'), [
        'company_id' => $company->id,
        'department_id' => $department->id,
    ]);

    $response->assertRedirect(route('superadmin.membership.edit'));
    $superAdmin->refresh();
    expect($superAdmin->company_id)->toBe($company->id)
        ->and($superAdmin->home_company_id)->toBe($company->id)
        ->and($superAdmin->department_id)->toBe($department->id)
        ->and($superAdmin->current_team_id)->toBe($departmentTeam->id)
        ->and($superAdmin->teams()->whereKey($adminTeam->id)->exists())->toBeTrue()
        ->and($superAdmin->teams()->whereKey($departmentTeam->id)->exists())->toBeTrue();
});

it('rejects a department from another company', function () {
    $company = Company::create([
        'name' => 'Company A',
        'code' => 'COMPANY_A',
        'company_type' => 'general',
        'active' => true,
    ]);
    $otherCompany = Company::create([
        'name' => 'Company B',
        'code' => 'COMPANY_B',
        'company_type' => 'general',
        'active' => true,
    ]);
    $department = Department::create([
        'company_id' => $otherCompany->id,
        'name' => 'Other Department',
        'code' => 'OTHER',
        'active' => true,
    ]);
    $superAdmin = User::factory()->create(['user_role' => 'superadmin']);

    $response = $this->actingAs($superAdmin)
        ->from(route('superadmin.membership.edit'))
        ->put(route('superadmin.membership.update'), [
            'company_id' => $company->id,
            'department_id' => $department->id,
        ]);

    $response->assertRedirect(route('superadmin.membership.edit'))
        ->assertSessionHasErrors('department_id');
    expect($superAdmin->refresh()->company_id)->toBeNull();
});

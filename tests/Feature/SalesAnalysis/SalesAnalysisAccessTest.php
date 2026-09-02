<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\SalesAnalysisPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SalesAnalysisAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_access_dashboard_without_explicit_permission()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($superadmin)
            ->get(route('sales_analysis.dashboard'))
            ->assertOk();
    }

    public function test_admin_without_permission_is_forbidden()
    {
        $admin = User::factory()->create(['user_role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('sales_analysis.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_with_enabled_permission_can_access_dashboard()
    {
        $admin = User::factory()->create(['user_role' => 'admin']);
        SalesAnalysisPermission::create(['user_id' => $admin->id, 'enabled' => true]);

        $this->actingAs($admin)
            ->get(route('sales_analysis.dashboard'))
            ->assertOk();
    }

    public function test_admin_with_disabled_permission_record_is_forbidden()
    {
        $admin = User::factory()->create(['user_role' => 'admin']);
        SalesAnalysisPermission::create(['user_id' => $admin->id, 'enabled' => false]);

        $this->actingAs($admin)
            ->get(route('sales_analysis.dashboard'))
            ->assertForbidden();
    }

    public function test_clerk_with_enabled_permission_can_access_dashboard()
    {
        $clerk = User::factory()->create(['user_role' => 'clerk']);
        SalesAnalysisPermission::create(['user_id' => $clerk->id, 'enabled' => true]);

        $this->actingAs($clerk)
            ->get(route('sales_analysis.dashboard'))
            ->assertOk();
    }

    public function test_clerk_without_permission_is_forbidden()
    {
        $clerk = User::factory()->create(['user_role' => 'clerk']);

        $this->actingAs($clerk)
            ->get(route('sales_analysis.dashboard'))
            ->assertForbidden();
    }

    public function test_leader_is_always_forbidden_even_with_a_permission_record()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);
        // Leaderは候補外だが、レコードが誤って存在しても拒否されることを保証する
        SalesAnalysisPermission::create(['user_id' => $leader->id, 'enabled' => true]);

        $this->actingAs($leader)
            ->get(route('sales_analysis.dashboard'))
            ->assertForbidden();
    }

    public function test_coordinator_is_forbidden()
    {
        $coordinator = User::factory()->create(['user_role' => 'coordinator']);

        $this->actingAs($coordinator)
            ->get(route('sales_analysis.dashboard'))
            ->assertForbidden();
    }

    public function test_user_role_is_forbidden()
    {
        $user = User::factory()->create(['user_role' => 'user']);

        $this->actingAs($user)
            ->get(route('sales_analysis.dashboard'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login()
    {
        $this->get(route('sales_analysis.dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_superadmin_permission_settings_route_rejects_non_superadmin()
    {
        $admin = User::factory()->create(['user_role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('superadmin.sales_analysis_permissions.index'))
            ->assertForbidden();
    }

    public function test_superadmin_permission_index_lists_only_admin_and_clerk()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $admin = User::factory()->create(['user_role' => 'admin', 'name' => 'Admin Taro']);
        $clerk = User::factory()->create(['user_role' => 'clerk', 'name' => 'Clerk Hanako']);
        $leader = User::factory()->create(['user_role' => 'leader', 'name' => 'Leader Jiro']);

        $response = $this->actingAs($superadmin)
            ->get(route('superadmin.sales_analysis_permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('SuperAdmin/SalesAnalysisPermissions/Index', false)
            ->has('candidates', 2)
        );
    }

    public function test_superadmin_can_toggle_permission_and_it_takes_effect_immediately()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $admin = User::factory()->create(['user_role' => 'admin']);

        $this->actingAs($superadmin)
            ->put(route('superadmin.sales_analysis_permissions.update', ['user' => $admin->id]), [
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('sales_analysis_permissions', [
            'user_id' => $admin->id,
            'enabled' => true,
            'granted_by' => $superadmin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('sales_analysis.dashboard'))
            ->assertOk();
    }

    public function test_superadmin_cannot_grant_permission_to_a_leader()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $leader = User::factory()->create(['user_role' => 'leader']);

        $this->actingAs($superadmin)
            ->put(route('superadmin.sales_analysis_permissions.update', ['user' => $leader->id]), [
                'enabled' => true,
            ])
            ->assertStatus(422);
    }
}

<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** 「Excel取込」タブの入口（年度別データ取込・経費取込の選択画面、2026-10-09） */
class ImportHubTest extends TestCase
{
    use RefreshesSalesDatabase;

    public function test_index_renders_with_expenses_card_hidden_by_default()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.import_hub'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->component('SalesAnalysis/ImportHub', false)->where('supportsExpenses', false));
    }

    public function test_index_shows_expenses_card_for_sunbrain_company()
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.import_hub'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page->where('supportsExpenses', true));
    }

    public function test_index_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $response = $this->actingAs($leader)->get(route('superadmin.sales_analysis.import_hub'));

        $response->assertForbidden();
    }
}

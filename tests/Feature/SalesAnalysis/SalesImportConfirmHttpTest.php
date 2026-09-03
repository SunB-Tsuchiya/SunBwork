<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsSalesWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesImportConfirmHttpTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsSalesWorkbook;

    public function test_preview_then_store_confirms_import_end_to_end()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [[
            'order_number' => '8000001',
            'client_name' => 'A社',
            'product_name' => '商品A',
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_amount' => 1000,
            'order_amount_component' => 1000,
            'plate_date' => '2026/09/05',
        ]];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $previewResponse = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
            ]);
            $previewResponse->assertOk();
            $token = $previewResponse->json('preview_token');
            $this->assertNotNull($token);

            $storeResponse = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), [
                'preview_token' => $token,
            ]);
            $storeResponse->assertOk();
            $storeResponse->assertJson(['version' => 1]);

            $this->assertDatabaseHas('sales_orders', ['order_number' => '8000001'], 'sales');
            $this->assertNotNull(
                SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 9)->first()
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_store_endpoint_rejects_confirmation_by_a_different_user_than_previewer()
    {
        $previewer = User::factory()->create(['user_role' => 'superadmin']);
        $confirmer = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [[
            'order_number' => '8000010',
            'client_name' => 'A社',
            'product_name' => '商品A',
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_amount' => 1000,
            'order_amount_component' => 1000,
            'plate_date' => '2026/09/05',
        ]];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $token = $this->actingAs($previewer)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
            ])->json('preview_token');

            // previewerが検証したプレビューをconfirmerが確定しようとする
            // （Codexレビュー2回目 High-2対応）
            $response = $this->actingAs($confirmer)->post(route('superadmin.sales_analysis.import.store'), [
                'preview_token' => $token,
            ]);

            $response->assertStatus(422);
            $this->assertDatabaseMissing('sales_orders', ['order_number' => '8000010'], 'sales');
        } finally {
            @unlink($path);
        }
    }

    public function test_store_endpoint_rejects_unknown_token()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), [
            'preview_token' => 'non-existent-token',
        ]);

        $response->assertStatus(422);
    }

    public function test_import_history_lists_confirmed_import_and_requires_access()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $leader = User::factory()->create(['user_role' => 'leader']);

        $rows = [[
            'order_number' => '8000002',
            'client_name' => 'A社',
            'product_name' => '商品A',
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_amount' => 1000,
            'order_amount_component' => 1000,
            'plate_date' => '2026/09/06',
        ]];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
            $token = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
            ])->json('preview_token');

            $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), ['preview_token' => $token]);

            $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.import_history.index'));
            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->component('SalesAnalysis/ImportHistory', false)
                ->has('imports', 1)
                ->where('imports.0.is_active', true)
                // 月次ファイル（1ヶ月分）なので1/1で完全有効（Codexレビュー2回目 8.1 Medium-2対応）
                ->where('imports.0.active_month_count', 1)
                ->where('imports.0.total_month_count', 1)
            );

            $this->actingAs($leader)
                ->get(route('superadmin.sales_analysis.import_history.index'))
                ->assertForbidden();
        } finally {
            @unlink($path);
        }
    }
}

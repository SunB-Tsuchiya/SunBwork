<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsSalesWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesImportPreviewTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsSalesWorkbook;

    public function test_preview_endpoint_returns_valid_result_and_cleans_up_temp_file()
    {
        Storage::fake('local');

        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $rows = [
            [
                'order_number' => '5000001',
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
            ],
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
            ]);

            $response->assertOk();
            $response->assertJson(['valid' => true]);
            $this->assertNotNull($response->json('preview_token'));

            // 一時保存されたファイルが処理後に残っていないこと
            Storage::disk('local')->assertDirectoryEmpty('sales_imports');
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_endpoint_rejects_non_xlsx_file()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $file = UploadedFile::fake()->create('fake.xlsx', 10, 'text/plain');

        $response = $this->actingAs($superadmin)->post(route('sales_analysis.import.preview'), [
            'file' => $file,
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 9,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_preview_endpoint_rejects_disabled_department()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $rows = [[
            'order_number' => '5000002',
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
        $path = $this->makeSalesWorkbook($this->monthlyTitle('制作', 2026, 9), $rows);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'production',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
            ], ['Accept' => 'application/json']);

            $response->assertStatus(422);
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_endpoint_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $response = $this->actingAs($leader)->get(route('sales_analysis.import.create'));

        $response->assertForbidden();
    }
}

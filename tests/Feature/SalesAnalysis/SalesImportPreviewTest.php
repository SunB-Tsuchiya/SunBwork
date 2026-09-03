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

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
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

    public function test_preview_endpoint_accepts_range_type_with_month_span()
    {
        Storage::fake('local');

        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $rows = [
            [
                'order_number' => '5000010',
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
                'plate_date' => '2026/03/05',
            ],
        ];
        // 範囲指定ファイルのタイトルは開始月（1月）のみ
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 1), $rows);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'range',
                'source_year' => 2026,
                'source_month' => 1,
                'source_month_end' => 6,
            ]);

            $response->assertOk();
            $response->assertJson(['valid' => true]);
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_endpoint_requires_month_end_gte_month_for_range_type()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 6), [[
            'order_number' => '5000011',
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
            'plate_date' => '2026/06/05',
        ]]);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'range',
                'source_year' => 2026,
                'source_month' => 6,
                'source_month_end' => 3, // 開始月より前 → 不正
            ], ['Accept' => 'application/json']);

            $response->assertStatus(422);
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_endpoint_rejects_non_xlsx_file()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $file = UploadedFile::fake()->create('fake.xlsx', 10, 'text/plain');

        $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
            'file' => $file,
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 9,
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
    }

    public function test_preview_endpoint_rejects_unknown_department()
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

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'unknown_dept',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
            ], ['Accept' => 'application/json']);

            $response->assertStatus(422);
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_endpoint_accepts_production_and_ondemand_departments()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        foreach (['production' => '制作', 'ondemand' => 'オンデマンド'] as $key => $label) {
            $rows = [[
                'order_number' => "DEPT-{$key}",
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
            $path = $this->makeSalesWorkbook($this->monthlyTitle($label, 2026, 9), $rows);

            try {
                $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

                $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                    'file' => $file,
                    'department_key' => $key,
                    'source_type' => 'monthly',
                    'source_year' => 2026,
                    'source_month' => 9,
                ]);

                $response->assertOk();
                $response->assertJson(['valid' => true]);
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_preview_endpoint_rejects_excluding_a_normal_order()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $rows = [[
            'order_number' => '5000020',
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

            // 5000020は検証エラーの無い正常な受注。除外指定は認められず、ファイル全体が確定不可になる
            // （Codexレビュー2回目 High-1対応: 正常受注や存在しない受注Noを不正に除外できてしまう問題）
            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
                'excluded_order_numbers' => ['5000020'],
            ]);

            $response->assertOk();
            $response->assertJson(['valid' => false]);
            $this->assertNotEmpty(array_filter($response->json('errors'), fn ($e) => str_contains($e, '除外対象として認められない受注No')));
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_endpoint_accepts_excluding_a_genuinely_invalid_order()
    {
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $rows = [
            [
                'order_number' => '5000021',
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
            [
                // 受注金額（N列）が0のみ＝正の値が無い、Excel側の入力ミスを想定
                'order_number' => '5000022',
                'client_name' => 'B社',
                'product_name' => '商品B',
                'category' => '組版',
                'item_name' => '新規',
                'format_size' => 'A4',
                'color_count' => 1,
                'quantity' => 1,
                'unit_price' => 1000,
                'line_amount' => 1000,
                'order_amount_component' => 0,
                'plate_date' => '2026/09/05',
            ],
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $file = new UploadedFile($path, 'sample.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            $response = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.preview'), [
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 9,
                'excluded_order_numbers' => ['5000022'],
            ]);

            $response->assertOk();
            $response->assertJson(['valid' => true]);
            $this->assertSame(['5000022'], $response->json('excluded_orders'));
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_endpoint_requires_sales_analysis_access()
    {
        $leader = User::factory()->create(['user_role' => 'leader']);

        $response = $this->actingAs($leader)->get(route('superadmin.sales_analysis.import.create'));

        $response->assertForbidden();
    }
}

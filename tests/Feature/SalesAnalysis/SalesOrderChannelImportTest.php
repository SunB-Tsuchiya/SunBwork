<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsSalesWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/**
 * Phase 20: サン・ブレーンの受注経路分離（サンエー印刷経由/独自受注）。
 * ファイル名だけがサーバー側の正本であり、フォーム送信値は無視される（PLAN Phase20 20.3）。
 */
class SalesOrderChannelImportTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsSalesWorkbook;

    /**
     * RefreshesSalesDatabase::setUp()が作成したテスト会社をサン・ブレーンとして扱う。
     * トレイトのsetUp()を隠さないよう、独自にsetUp()を定義せず各テストの先頭で呼ぶ。
     */
    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    private function orderRow(string $orderNumber, string $plateDate, float $amount = 1000): array
    {
        return [
            'order_number' => $orderNumber,
            'client_name' => 'A社',
            'product_name' => '商品A',
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => $amount,
            'line_amount' => $amount,
            'order_amount_component' => $amount,
            'plate_date' => $plateDate,
        ];
    }

    private function upload(User $user, string $filename, array $rows, string $title, array $formOverrides = []): array
    {
        $path = $this->makeSalesWorkbook($title, $rows);

        try {
            $file = new UploadedFile($path, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

            // サン・ブレーンではファイル名がサーバー側の正本のため、フォーム値はダミーのままでも
            // 確定結果はファイル名解析値になる（フォーム側のUX自動入力とは独立している）。
            return $this->actingAs($user)->post(route('superadmin.sales_analysis.import.preview'), array_merge([
                'file' => $file,
                'department_key' => 'planning',
                'source_type' => 'monthly',
                'source_year' => 2026,
                'source_month' => 8,
            ], $formOverrides))->json();
        } finally {
            @unlink($path);
        }
    }

    public function test_standard_filename_is_confirmed_with_standard_channel()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $preview = $this->upload(
            $superadmin,
            '企画_2026年08月.xlsx',
            [$this->orderRow('9100001', '2026/08/10')],
            $this->monthlyTitle('企画', 2026, 8)
        );

        $this->assertTrue($preview['valid']);
        $this->assertSame('standard', $preview['order_channel']);

        $store = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), [
            'preview_token' => $preview['preview_token'],
        ])->json();

        $this->assertSame('standard', $store['order_channel']);
        $import = SalesImport::where('id', $store['import_id'])->first();
        $this->assertSame('standard', $import->order_channel);

        $active = SalesActiveMonth::where('company_id', $this->salesTestCompanyId())
            ->where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 8)
            ->where('order_channel', 'standard')->first();
        $this->assertNotNull($active);
    }

    public function test_direct_suffix_filename_is_confirmed_with_direct_channel()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $preview = $this->upload(
            $superadmin,
            '企画_2026年08月_独自.xlsx',
            [$this->orderRow('9100002', '2026/08/10')],
            $this->monthlyTitle('企画', 2026, 8)
        );

        $this->assertTrue($preview['valid']);
        $this->assertSame('direct', $preview['order_channel']);

        $store = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), [
            'preview_token' => $preview['preview_token'],
        ])->json();

        $this->assertSame('direct', $store['order_channel']);

        $active = SalesActiveMonth::where('company_id', $this->salesTestCompanyId())
            ->where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 8)
            ->where('order_channel', 'direct')->first();
        $this->assertNotNull($active);
    }

    public function test_standard_and_direct_coexist_and_reimport_does_not_affect_other_channel()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        // ① サンエー印刷経由を取込
        $standardPreview = $this->upload($superadmin, '企画_2026年08月.xlsx', [$this->orderRow('9100010', '2026/08/10', 1000)], $this->monthlyTitle('企画', 2026, 8));
        $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), ['preview_token' => $standardPreview['preview_token']]);

        $standardActive = SalesActiveMonth::where('company_id', $this->salesTestCompanyId())
            ->where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 8)
            ->where('order_channel', 'standard')->first();
        $standardImportIdBefore = $standardActive->sales_import_id;

        // ② 独自受注を取込（同じ会社・部署・年月）
        $directPreview = $this->upload($superadmin, '企画_2026年08月_独自.xlsx', [$this->orderRow('9100011', '2026/08/12', 500)], $this->monthlyTitle('企画', 2026, 8));
        $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), ['preview_token' => $directPreview['preview_token']]);

        // 両経路が同時にactiveとして存在できる（PLAN Phase20 受入基準）
        $this->assertDatabaseHas('sales_active_months', [
            'company_id' => $this->salesTestCompanyId(), 'department_key' => 'planning',
            'sales_year' => 2026, 'sales_month' => 8, 'order_channel' => 'standard',
        ], 'sales');
        $this->assertDatabaseHas('sales_active_months', [
            'company_id' => $this->salesTestCompanyId(), 'department_key' => 'planning',
            'sales_year' => 2026, 'sales_month' => 8, 'order_channel' => 'direct',
        ], 'sales');

        // ③ 独自受注を再取込（版2）。standardのactive pointerは変わらない
        $directReimport = $this->upload($superadmin, '企画_2026年08月_独自.xlsx', [$this->orderRow('9100011', '2026/08/12', 600)], $this->monthlyTitle('企画', 2026, 8));
        $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), ['preview_token' => $directReimport['preview_token']]);

        $standardActiveAfter = SalesActiveMonth::where('company_id', $this->salesTestCompanyId())
            ->where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 8)
            ->where('order_channel', 'standard')->first();
        $this->assertSame($standardImportIdBefore, $standardActiveAfter->sales_import_id);

        $directActiveAfter = SalesActiveMonth::where('company_id', $this->salesTestCompanyId())
            ->where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 8)
            ->where('order_channel', 'direct')->first();
        $directImport = SalesImport::find($directActiveAfter->sales_import_id);
        $this->assertSame(2, $directImport->version);
    }

    public function test_same_order_number_is_allowed_across_channels_but_not_within_same_channel_other_month()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        // standard 8月に受注No 9100020
        $standardPreview = $this->upload($superadmin, '企画_2026年08月.xlsx', [$this->orderRow('9100020', '2026/08/10')], $this->monthlyTitle('企画', 2026, 8));
        $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), ['preview_token' => $standardPreview['preview_token']]);

        // direct 8月に同じ受注No 9100020（経路が違うので許可される）
        $directPreview = $this->upload($superadmin, '企画_2026年08月_独自.xlsx', [$this->orderRow('9100020', '2026/08/11')], $this->monthlyTitle('企画', 2026, 8));
        $this->assertTrue($directPreview['valid']);
        $directStore = $this->actingAs($superadmin)->post(route('superadmin.sales_analysis.import.store'), ['preview_token' => $directPreview['preview_token']])->json();
        $this->assertSame('direct', $directStore['order_channel'] ?? null);

        // standard 9月に同じ受注No 9100020（同一経路内の他月重複なので拒否される）
        $duplicatePreview = $this->upload($superadmin, '企画_2026年09月.xlsx', [$this->orderRow('9100020', '2026/09/10')], $this->monthlyTitle('企画', 2026, 9));
        $this->assertFalse($duplicatePreview['valid']);
    }

    public function test_malformed_filename_is_rejected_without_manual_fallback()
    {
        $this->markCompanyAsSunbrain();
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $response = $this->upload($superadmin, '企画_2026年08月_独自版.xlsx', [$this->orderRow('9100030', '2026/08/10')], $this->monthlyTitle('企画', 2026, 8));

        $this->assertFalse($response['valid']);
        $this->assertNotEmpty($response['errors']);
    }

    public function test_non_sunbrain_company_ignores_direct_suffix_and_always_uses_standard()
    {
        // 会社コードをSUNBRAIN以外に戻す（非対象会社の既存挙動を変えないことの回帰確認）
        Company::find($this->salesTestCompanyId())->update(['code' => 'OTHER_' . uniqid()]);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        // ファイル名に「_独自」が付いていても、経路非対応会社ではstandard固定のまま
        $preview = $this->upload($superadmin, '企画_2026年08月_独自.xlsx', [$this->orderRow('9100040', '2026/08/10')], $this->monthlyTitle('企画', 2026, 8));

        $this->assertTrue($preview['valid']);
        $this->assertSame('standard', $preview['order_channel']);
        $this->assertFalse($preview['supports_order_channels']);
    }
}

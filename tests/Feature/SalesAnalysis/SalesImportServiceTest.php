<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderDetail;
use App\Models\User;
use App\Services\SalesAnalysis\Exceptions\SalesImportConfirmException;
use App\Services\SalesAnalysis\SalesImportService;
use App\Services\SalesAnalysis\SalesImportValidator;
use App\Services\SalesAnalysis\SalesWorkbookReader;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsSalesWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesImportServiceTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsSalesWorkbook;

    private function service(): SalesImportService
    {
        return new SalesImportService();
    }

    private function validator(): SalesImportValidator
    {
        return new SalesImportValidator(new SalesWorkbookReader());
    }

    /** 検証済み結果をプレビューCacheへ保存し、confirm()から使えるtokenを返す */
    private function seedPreviewCache(array $validationResult, int $previewedBy): string
    {
        $validationResult['previewed_by'] = $previewedBy;

        $token = (string) Str::uuid();
        $this->service()->previewCacheStore()->put(
            $this->service()->previewCacheKey($token),
            Crypt::encrypt($validationResult),
            now()->addMinutes(30)
        );

        return $token;
    }

    private function validateWorkbook(array $rows, string $title, string $departmentKey, string $sourceType, int $year, ?int $month, ?int $monthEnd = null): array
    {
        $path = $this->makeSalesWorkbook($title, $rows);

        try {
            $result = $this->validator()->validate($path, $departmentKey, $sourceType, $year, $month, $monthEnd);
            $result['file_sha256'] = hash('sha256', Str::random(32) . microtime());
            $result['original_filename'] = 'test.xlsx';

            return $result;
        } finally {
            @unlink($path);
        }
    }

    public function test_preview_cache_store_accepts_payloads_larger_than_database_text_column()
    {
        // 実機検証で発見: 既定のDBキャッシュ（cacheテーブルのvalue列はTEXT=65,535byte上限）だと、
        // 制作・オンデマンドのような行数の多い部署でプレビューが「Data too long for column 'value'」で
        // 500エラーになっていた。previewCacheStore()はfileストアを使うため上限を超えても保存できる。
        $key = 'sales_import_preview:oversized-payload-test';
        $oversized = str_repeat('あ', 100_000); // 100,000文字 ≒ 300,000byte（UTF-8）。65,535byteを優に超える

        $this->service()->previewCacheStore()->put($key, $oversized, now()->addMinutes(30));

        $this->assertSame($oversized, $this->service()->previewCacheStore()->get($key));
    }

    public function test_confirm_persists_orders_details_and_activates_month()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [
            $this->row('7000001', 'A社', '商品A', 1000, 1000, '2026/09/05'),
            $this->row('7000002', 'B社', '商品B', 500, 0, '2026/09/10'),
            $this->row('7000002', 'B社', '商品B', 500, 1000, '2026/09/10'),
        ];
        $result = $this->validateWorkbook($rows, $this->monthlyTitle('企画', 2026, 9), 'planning', 'monthly', 2026, 9);
        $this->assertTrue($result['valid'], implode(' / ', $result['errors']));

        $token = $this->seedPreviewCache($result, $user->id);

        $import = $this->service()->confirm($token, $user->id);

        $this->assertSame(1, $import->version);
        $this->assertSame('completed', $import->status);
        $this->assertDatabaseHas('sales_orders', ['order_number' => '7000001', 'order_amount' => 1000], 'sales');
        $this->assertDatabaseHas('sales_orders', ['order_number' => '7000002', 'order_amount' => 1000], 'sales');
        $this->assertSame(2, SalesOrder::where('sales_import_id', $import->id)->count());
        $this->assertSame(3, SalesOrderDetail::whereIn('sales_order_id', SalesOrder::where('sales_import_id', $import->id)->pluck('id'))->count());

        $active = SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 9)->first();
        $this->assertNotNull($active);
        $this->assertSame($import->id, $active->sales_import_id);

        $this->assertDatabaseHas('sales_audit_logs', ['action' => 'import', 'target_id' => $import->id], 'sales');

        // プレビューCacheは確定後に消費される
        $this->assertNull($this->service()->previewCacheStore()->get($this->service()->previewCacheKey($token)));
    }

    public function test_reimport_same_month_switches_active_pointer_and_keeps_old_version()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $firstRows = [$this->row('7100001', 'A社', '商品A', 1000, 1000, '2026/10/05')];
        $firstResult = $this->validateWorkbook($firstRows, $this->monthlyTitle('企画', 2026, 10), 'planning', 'monthly', 2026, 10);
        $firstImport = $this->service()->confirm($this->seedPreviewCache($firstResult, $user->id), $user->id);

        $secondRows = [$this->row('7100002', 'A社', '商品B', 2000, 2000, '2026/10/06')];
        $secondResult = $this->validateWorkbook($secondRows, $this->monthlyTitle('企画', 2026, 10), 'planning', 'monthly', 2026, 10);
        $secondImport = $this->service()->confirm($this->seedPreviewCache($secondResult, $user->id), $user->id);

        $this->assertSame(2, $secondImport->version);

        $active = SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 10)->first();
        $this->assertSame($secondImport->id, $active->sales_import_id);

        // 旧版のデータは削除されず残る
        $this->assertDatabaseHas('sales_imports', ['id' => $firstImport->id], 'sales');
        $this->assertDatabaseHas('sales_orders', ['order_number' => '7100001'], 'sales');
    }

    public function test_annual_import_activates_all_twelve_months_including_ones_without_data()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [
            $this->row('7200001', 'A社', '商品A', 1000, 1000, '2026/02/10'),
            $this->row('7200002', 'A社', '商品B', 2000, 2000, '2026/11/15'),
        ];
        $result = $this->validateWorkbook($rows, $this->annualTitle('企画', 2026), 'planning', 'annual', 2026, null);
        $this->assertTrue($result['valid'], implode(' / ', $result['errors']));

        $import = $this->service()->confirm($this->seedPreviewCache($result, $user->id), $user->id);

        $this->assertNotNull(SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 2)->first());
        $this->assertNotNull(SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 11)->first());
        // 受注データが無い月（6月）も取込指定範囲全体（年次=1〜12月）としてactive化される
        // （Codexレビュー6.2 High-4: データが無い月だけを除外すると旧版の残留が起きるため）
        $this->assertNotNull(SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 6)->first());

        $this->assertSame(12, SalesActiveMonth::where('sales_import_id', $import->id)->count());
    }

    public function test_range_import_activates_months_within_span_and_persists_source_month_end()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [
            $this->row('7500001', 'A社', '商品A', 1000, 1000, '2026/01/10'),
            $this->row('7500002', 'A社', '商品B', 2000, 2000, '2026/06/15'),
        ];
        $result = $this->validateWorkbook($rows, $this->rangeTitle('企画', 2026, 1), 'planning', 'range', 2026, 1, 6);
        $this->assertTrue($result['valid'], implode(' / ', $result['errors']));

        $import = $this->service()->confirm($this->seedPreviewCache($result, $user->id), $user->id);

        $this->assertSame(1, $import->source_month);
        $this->assertSame(6, $import->source_month_end);
        $this->assertNotNull(SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 1)->first());
        $this->assertNotNull(SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 6)->first());
        // 受注データが無い月（2〜5月）も指定範囲全体としてactive化される（Codexレビュー6.2 High-4）
        $this->assertNotNull(SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 3)->first());
        $this->assertSame(6, SalesActiveMonth::where('sales_import_id', $import->id)->count());
    }

    public function test_confirm_rejects_duplicate_file_hash()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [$this->row('7300001', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $result = $this->validateWorkbook($rows, $this->monthlyTitle('企画', 2026, 9), 'planning', 'monthly', 2026, 9);
        $fixedHash = hash('sha256', 'fixed-content-for-dup-test');
        $result['file_sha256'] = $fixedHash;

        $this->service()->confirm($this->seedPreviewCache($result, $user->id), $user->id);

        // 同じハッシュを持つ別のプレビューを確定しようとする
        $rows2 = [$this->row('7300002', 'B社', '商品B', 2000, 2000, '2026/09/06')];
        $result2 = $this->validateWorkbook($rows2, $this->monthlyTitle('企画', 2026, 9), 'planning', 'monthly', 2026, 9);
        $result2['file_sha256'] = $fixedHash;

        $this->expectException(SalesImportConfirmException::class);
        $this->service()->confirm($this->seedPreviewCache($result2, $user->id), $user->id);
    }

    public function test_confirm_fails_for_expired_or_unknown_token()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $this->expectException(SalesImportConfirmException::class);
        $this->service()->confirm((string) Str::uuid(), $user->id);
    }

    public function test_failed_confirm_does_not_switch_active_pointer()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        // 事前に有効な版を作っておく
        $firstRows = [$this->row('7400001', 'A社', '商品A', 1000, 1000, '2026/12/05')];
        $firstResult = $this->validateWorkbook($firstRows, $this->monthlyTitle('企画', 2026, 12), 'planning', 'monthly', 2026, 12);
        $firstImport = $this->service()->confirm($this->seedPreviewCache($firstResult, $user->id), $user->id);

        // 期限切れ・不正tokenでのconfirmはactive pointerに影響しないことを確認
        try {
            $this->service()->confirm((string) Str::uuid(), $user->id);
        } catch (SalesImportConfirmException $e) {
            // 期待どおりの例外
        }

        $active = SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 12)->first();
        $this->assertSame($firstImport->id, $active->sales_import_id);
    }

    public function test_annual_reimport_switches_active_pointer_even_for_month_with_no_orders_in_new_data()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        // 1回目: 6月にデータあり
        $firstRows = [$this->row('7600001', 'A社', '商品A', 1000, 1000, '2026/06/10')];
        $firstResult = $this->validateWorkbook($firstRows, $this->annualTitle('企画', 2026), 'planning', 'annual', 2026, null);
        $firstImport = $this->service()->confirm($this->seedPreviewCache($firstResult, $user->id), $user->id);

        $activeBefore = SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 6)->first();
        $this->assertSame($firstImport->id, $activeBefore->sales_import_id);

        // 2回目（修正版）: 6月のデータが無くなった
        $secondRows = [$this->row('7600002', 'B社', '商品B', 2000, 2000, '2026/03/10')];
        $secondResult = $this->validateWorkbook($secondRows, $this->annualTitle('企画', 2026), 'planning', 'annual', 2026, null);
        $secondImport = $this->service()->confirm($this->seedPreviewCache($secondResult, $user->id), $user->id);

        // 6月は新データが0件でも、取込指定範囲（年次=1〜12月）全体としてactive pointerが
        // 新しい版に切り替わる（旧版が残留してはいけない。Codexレビュー6.2 High-4）。
        $activeAfter = SalesActiveMonth::where('department_key', 'planning')->where('sales_year', 2026)->where('sales_month', 6)->first();
        $this->assertNotNull($activeAfter);
        $this->assertSame($secondImport->id, $activeAfter->sales_import_id);
        $this->assertSame(0, SalesOrder::where('sales_import_id', $secondImport->id)->where('sales_month', 6)->count());
    }

    public function test_confirm_persists_order_with_null_optional_fields()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $row = $this->row('7700001', 'A社', '商品A', 1000, 1000, '2026/09/05');
        $row['client_name'] = null;
        $row['product_name'] = null;
        $row['category'] = null;
        $row['item_name'] = null;
        $row['format_size'] = null;
        $row['color_count'] = null;
        $row['quantity'] = null;
        $row['unit_price'] = null;

        $result = $this->validateWorkbook([$row], $this->monthlyTitle('企画', 2026, 9), 'planning', 'monthly', 2026, 9);
        $this->assertTrue($result['valid'], implode(' / ', $result['errors']));

        $import = $this->service()->confirm($this->seedPreviewCache($result, $user->id), $user->id);

        $this->assertDatabaseHas('sales_orders', [
            'sales_import_id' => $import->id,
            'order_number' => '7700001',
            'client_name' => null,
            'product_name' => null,
        ], 'sales');
    }

    public function test_confirm_rejects_preview_created_by_a_different_user()
    {
        $previewer = User::factory()->create(['user_role' => 'superadmin']);
        $confirmer = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [$this->row('7800001', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $result = $this->validateWorkbook($rows, $this->monthlyTitle('企画', 2026, 9), 'planning', 'monthly', 2026, 9);
        $token = $this->seedPreviewCache($result, $previewer->id);

        // 検証を実行したユーザー（previewer）と異なるユーザー（confirmer）が確定しようとする
        // （Codexレビュー2回目 High-2対応: 他ユーザーのプレビュートークンを確定できてしまう問題）
        $this->expectException(SalesImportConfirmException::class);
        $this->service()->confirm($token, $confirmer->id);
    }

    public function test_confirm_succeeds_when_same_user_previews_and_confirms()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [$this->row('7800002', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $result = $this->validateWorkbook($rows, $this->monthlyTitle('企画', 2026, 9), 'planning', 'monthly', 2026, 9);
        $token = $this->seedPreviewCache($result, $user->id);

        $import = $this->service()->confirm($token, $user->id);

        $this->assertSame('completed', $import->status);
    }

    public function test_confirm_rejects_second_use_of_the_same_token()
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);

        $rows = [$this->row('7800003', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $result = $this->validateWorkbook($rows, $this->monthlyTitle('企画', 2026, 9), 'planning', 'monthly', 2026, 9);
        $token = $this->seedPreviewCache($result, $user->id);

        $this->service()->confirm($token, $user->id);

        // 同じトークンを続けて確定しようとしても、既にキャッシュが消費されているため二重登録されない
        // （Codexレビュー2回目 High-2/8.3対応）
        $this->expectException(SalesImportConfirmException::class);
        $this->service()->confirm($token, $user->id);
    }

    private function row(string $orderNumber, string $client, string $product, float $lineAmount, float $orderAmount, string $plateDate): array
    {
        return [
            'order_number' => $orderNumber,
            'client_name' => $client,
            'product_name' => $product,
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => $lineAmount,
            'line_amount' => $lineAmount,
            'order_amount_component' => $orderAmount,
            'plate_date' => $plateDate,
        ];
    }
}

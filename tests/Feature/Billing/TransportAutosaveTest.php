<?php

namespace Tests\Feature\Billing;

use App\Models\TransportExpense;
use App\Models\User;
use App\Services\Billing\Transport\TransportExpenseExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: 自動保存（JSON）と、長い文字を収める計算 */
class TransportAutosaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false]);   // 既存のテストは、過去の月で入力するため、期限の強制を切る（期限の検証は TransportDeadlineTest）
    }

    private function body(array $items, string $month = '2026-08'): array
    {
        return ['billing_month' => $month, 'charge_department_id' => null, 'items' => $items];
    }

    private function item(array $over = []): array
    {
        return array_merge([
            'occurrence_date' => '2026-08-05', 'destination' => '小台工場', 'purpose' => 'round_trip', 'purpose_text' => '',
            'station_from' => '田端', 'station_to' => '宮城都営住宅前', 'fare_type' => 'ic',
            'amount' => 420, 'base_amount' => 210, 'auto_double' => true,
        ], $over);
    }

    public function test_自動保存は下書きを作り_JSONを返し_繰り返しても1件のまま(): void
    {
        $u = User::factory()->create(['user_role' => 'superadmin']);

        $res = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body([$this->item()]));
        $res->assertOk()->assertJson(['ok' => true, 'status' => 'draft', 'total' => 420]);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $res->json('saved_at'));
        $id = $res->json('id');

        $res2 = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body([$this->item(), $this->item(['occurrence_date' => '2026-08-06'])]));
        $res2->assertOk()->assertJson(['id' => $id, 'total' => 840]);
        $this->assertSame(1, TransportExpense::where('user_id', $u->id)->count());
        $this->assertCount(2, TransportExpense::find($id)->items);
        $this->assertSame('draft', TransportExpense::find($id)->status);   // 自動保存では申請されない
    }

    public function test_自動保存でも入力の検証は同じ_日本語のエラーがJSONで返る(): void
    {
        $u = User::factory()->create(['user_role' => 'superadmin']);

        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body([$this->item(['occurrence_date' => '2026-09-01'])]))
            ->assertStatus(422)->assertJsonValidationErrors('items.0.occurrence_date');
        $this->assertStringContainsString('範囲', $this->actingAs($u)->postJson(route('user.billing.transport.autosave'),
            $this->body([$this->item(['occurrence_date' => '2026-09-01'])]))->json('errors')['items.0.occurrence_date'][0]);
        $this->assertSame(0, TransportExpense::count());   // 検証に通らなければ、何も作られない
    }

    public function test_申請済みの月は自動保存でも書き換えられない(): void
    {
        $u = User::factory()->create(['user_role' => 'superadmin']);
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body([$this->item()]))->json('id');
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $id]))->assertSessionHasNoErrors();

        $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body([$this->item(['amount' => 999, 'base_amount' => 999, 'auto_double' => false])]))
            ->assertStatus(422);
        $this->assertSame(420, TransportExpense::find($id)->total_amount);
    }

    public function test_長い文字は収まるまで文字の大きさが下がり_下限で止まる(): void
    {
        // 短い: そのまま 10pt
        $this->assertSame(10.0, TransportExpenseExporter::fitFontSize('小台工場', 111));
        // 全角 14 文字（セル幅 111pt）: 10pt では 140pt 必要 → 下がる
        $long = TransportExpenseExporter::fitFontSize('クリエイティブストゥディオワークス', 111);
        $this->assertLessThan(10.0, $long);
        $this->assertGreaterThanOrEqual(5.5, $long);
        $this->assertSame(0.0, fmod($long * 2, 1.0));          // 0.5pt 刻み
        $this->assertLessThanOrEqual(111 - 4, $long * 17);     // 全角 17 文字が、余白を除いた幅に収まる
        // 極端に長い: 下限（それでも入らない分は切れる）
        $this->assertSame(5.5, TransportExpenseExporter::fitFontSize(str_repeat('あ', 60), 111));
        // 半角英数は細いので、同じ文字数でも大きさが残る
        $this->assertGreaterThan(TransportExpenseExporter::fitFontSize(str_repeat('あ', 20), 111), TransportExpenseExporter::fitFontSize(str_repeat('A', 20), 111));
        // 空
        $this->assertSame(10.0, TransportExpenseExporter::fitFontSize('', 111));
    }

    public function test_Excelの明細セルは縮小して全体を表示する設定になる(): void
    {
        $u = User::factory()->create(['user_role' => 'superadmin']);
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body([$this->item(['destination' => 'クリエイティブストゥディオワークス'])]))->json('id');

        $res = $this->actingAs($u)->get(route('user.billing.transport.excel', ['expense' => $id]));
        $res->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'tx') . '.xlsx';
        file_put_contents($tmp, $res->getContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getSheet(0);
        @unlink($tmp);

        $this->assertSame('クリエイティブストゥディオワークス', $sheet->getCell('D7')->getValue());
        foreach (['D7', 'J7', 'O7', 'T7', 'X7'] as $c) {
            $this->assertTrue($sheet->getStyle($c)->getAlignment()->getShrinkToFit(), "{$c} は縮小して全体を表示");
        }
    }

    public function test_長い名前のPDFも作れる(): void
    {
        $u = User::factory()->create(['user_role' => 'superadmin']);
        $id = $this->actingAs($u)->postJson(route('user.billing.transport.autosave'), $this->body([
            $this->item(['destination' => 'クリエイティブストゥディオワークス', 'station_to' => '宮城都営住宅前駅西口バスターミナル']),
        ]))->json('id');

        $res = $this->actingAs($u)->get(route('user.billing.transport.pdf', ['expense' => $id]));
        $res->assertOk();
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}

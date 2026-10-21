<?php

namespace Tests\Feature\Billing;

use App\Models\Company;
use App\Models\Department;
use App\Models\TransportExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 交通費: 1ユーザー × 対象月 = 1申請、月初〜月末の範囲、状態遷移 */
class TransportExpenseMonthlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transport.enforce_deadline' => false]);   // 既存のテストは、過去の月で入力するため、期限の強制を切る（期限の検証は TransportDeadlineTest）
        config(['applications.enforce_access' => false]);   // 申請者管理の制限は、TransportApplicantAccessTest で検証
    }

    private function actor(?Company $company = null, string $role = 'superadmin'): User
    {
        return User::factory()->create(['user_role' => $role, 'company_id' => $company?->id]);
    }

    private function company(string $type, string $name): Company
    {
        return Company::create(['name' => $name, 'code' => strtoupper($type) . random_int(1000, 9999), 'company_type' => $type, 'active' => true]);
    }

    private function dept(Company $c, string $name, bool $active = true): Department
    {
        return Department::create(['company_id' => $c->id, 'name' => $name, 'code' => 'D' . random_int(1000, 9999), 'sort_order' => 0, 'active' => $active]);
    }

    private function sheetFor(User $u, int $deptId): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item()], '2026-08', $deptId))->assertSessionHasNoErrors();
        $e = TransportExpense::where('user_id', $u->id)->first();
        $res = $this->actingAs($u)->get(route('user.billing.transport.excel', ['expense' => $e->id]));
        $tmp = tempnam(sys_get_temp_dir(), 'tx') . '.xlsx';
        file_put_contents($tmp, $res->getContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getSheet(0);
        @unlink($tmp);
        return $sheet;
    }

    private function item(array $over = []): array
    {
        return array_merge([
            'occurrence_date' => '2026-08-05', 'destination' => '小台工場', 'purpose' => 'round_trip',
            'purpose_text' => '', 'station_from' => '田端', 'station_to' => '宮城都営住宅前',
            'fare_type' => 'ic', 'amount' => 420,
        ], $over);
    }

    private function payload(array $items, string $month = '2026-08', ?int $dept = null): array
    {
        return ['billing_month' => $month, 'charge_department_id' => $dept, 'items' => $items];
    }

    public function test_保存すると月ごとに1件の下書きができ_再保存は同じ申請を更新する(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item()]))->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item(), $this->item(['amount' => 210])]))->assertSessionHasNoErrors();

        $this->assertSame(1, TransportExpense::where('user_id', $u->id)->count());
        $e = TransportExpense::first();
        $this->assertSame('draft', $e->status);
        $this->assertSame('2026-08', $e->billing_month);
        $this->assertSame(630, $e->total_amount);
        $this->assertCount(2, $e->items);
    }

    public function test_発生日は対象月の範囲内のみ(): void
    {
        $u = $this->actor();
        foreach (['2026-07-31', '2026-09-01'] as $bad) {
            $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item(['occurrence_date' => $bad])]))
                ->assertSessionHasErrors('items.0.occurrence_date');
        }
        foreach (['2026-08-01', '2026-08-31'] as $ok) {
            $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item(['occurrence_date' => $ok])]))
                ->assertSessionHasNoErrors();
        }
    }

    public function test_日付として読めない入力は日本語のエラーになる(): void
    {
        $u = $this->actor();
        $res = $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([
            $this->item(['occurrence_date' => 'ﾀﾊﾞﾀ']), $this->item(['amount' => 'abc']),
        ]));
        $res->assertSessionHasErrors(['items.0.occurrence_date', 'items.1.amount']);
        $this->assertSame('1行目の発生日を日付として読み取れません。「0422」「4/22」のように入力してください。', session('errors')->first('items.0.occurrence_date'));
        $this->assertSame('2行目の金額は数字で入力してください。', session('errors')->first('items.1.amount'));
    }

    public function test_用件がその他でも_往復の2倍が使え_出力の用件にICは付かない(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([
            $this->item(['occurrence_date' => '2026-08-01', 'purpose' => 'other', 'purpose_text' => '研修', 'fare_type' => 'ic', 'base_amount' => 200, 'auto_double' => true, 'amount' => 200]),
            $this->item(['occurrence_date' => '2026-08-02', 'purpose' => 'other', 'purpose_text' => '研修', 'fare_type' => 'ic', 'base_amount' => 200, 'auto_double' => false, 'amount' => 200]),
        ]))->assertSessionHasNoErrors();

        $e = TransportExpense::first();
        $this->assertSame([400, 200], $e->items->pluck('amount')->all());
        $this->assertSame([true, false], $e->items->pluck('auto_double')->all());

        // 用件には、IC／切符の区別を付けない
        $this->assertSame('研修', \App\Services\Billing\Transport\TransportExpenseExporter::purposeLabel($e->items->first()));
        $round = $this->item(['occurrence_date' => '2026-07-05', 'purpose' => 'round_trip', 'fare_type' => 'ic']);
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$round], '2026-07'))->assertSessionHasNoErrors();
        $this->assertSame('打合せ（往復）', \App\Services\Billing\Transport\TransportExpenseExporter::purposeLabel(TransportExpense::where('billing_month', '2026-07')->first()->items->first()));
    }

    public function test_往復の片道金額と_2倍_は保存して同じ状態で復元できる(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([
            $this->item(['occurrence_date' => '2026-08-01', 'base_amount' => 210, 'auto_double' => true, 'amount' => 420]),
            $this->item(['occurrence_date' => '2026-08-02', 'base_amount' => 300, 'auto_double' => false, 'amount' => 300]),
            // クライアントの合計が食い違っていても、サーバーが片道×2 に直す
            $this->item(['occurrence_date' => '2026-08-03', 'base_amount' => 100, 'auto_double' => true, 'amount' => 999]),
            // 往復以外では ×2 は無効（金額はそのまま）
            $this->item(['occurrence_date' => '2026-08-04', 'purpose' => 'outbound', 'base_amount' => 150, 'auto_double' => true, 'amount' => 150]),
        ]))->assertSessionHasNoErrors();

        $items = TransportExpense::first()->items;
        $this->assertSame([420, 300, 200, 150], $items->pluck('amount')->all());
        $this->assertSame([210, 300, 100, 150], $items->pluck('base_amount')->all());
        $this->assertSame([true, false, true, false], $items->pluck('auto_double')->all());
        $this->assertSame(1070, TransportExpense::first()->total_amount);

        // 開き直したときは、片道の金額と ×2 の状態がそのまま返る
        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page
                ->where('expense.items.0.base_amount', 210)->where('expense.items.0.auto_double', true)->where('expense.items.0.amount', 420)
                ->where('expense.items.1.base_amount', 300)->where('expense.items.1.auto_double', false));
    }

    public function test_片道の記録が無い旧データは合計のまま表示される(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item(['occurrence_date' => '2026-08-01'])]));
        \App\Models\TransportExpenseItem::query()->update(['base_amount' => null, 'auto_double' => false]);   // 旧データ相当

        $this->actingAs($u)->get(route('user.billing.transport.index', ['month' => '2026-08']))
            ->assertInertia(fn ($page) => $page->where('expense.items.0.base_amount', 420)->where('expense.items.0.auto_double', false));
    }

    public function test_9月は9月1日から9月30日(): void
    {
        $this->assertSame(['2026-09-01', '2026-09-30'], TransportExpense::monthRange('2026-09'));
        $this->assertSame(['2026-02-01', '2026-02-28'], TransportExpense::monthRange('2026-02'));
        $this->assertSame(['2028-02-01', '2028-02-29'], TransportExpense::monthRange('2028-02'));
    }

    public function test_金額と文字は画面を通さなくてもサーバーで整形される(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([
            $this->item(['amount' => '１,２３４円', 'station_from' => 'JR田端']),
        ]))->assertSessionHasNoErrors();
        $it = TransportExpense::first()->items->first();
        $this->assertSame(1234, $it->amount);
        $this->assertSame('ＪＲ田端', $it->station_from);
    }

    public function test_申請_取り下げ_差戻し後の再編集の流れ(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item()]));
        $e = TransportExpense::first();

        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $e->fresh()->status);
        $this->assertNotNull($e->fresh()->submitted_at);

        // 申請済みは保存・削除できない
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item()]))->assertStatus(422);
        $this->actingAs($u)->delete(route('user.billing.transport.destroy', ['expense' => $e->id]))->assertStatus(422);

        $this->actingAs($u)->post(route('user.billing.transport.withdraw', ['expense' => $e->id]));
        $this->assertSame('draft', $e->fresh()->status);

        // 差戻しは再編集できる
        $e->update(['status' => 'returned', 'review_comment' => '金額を確認してください']);
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item(['amount' => 500])]))->assertSessionHasNoErrors();
        $this->assertSame(500, $e->fresh()->total_amount);
    }

    public function test_必須項目が欠けていると申請できない(): void
    {
        $u = $this->actor();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item(['station_to' => '', 'amount' => 0])]));
        $e = TransportExpense::first();
        $this->actingAs($u)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertSessionHasErrors('submit');
        $this->assertSame('draft', $e->fresh()->status);
    }

    public function test_他人の申請は操作も出力もできない(): void
    {
        $owner = $this->actor();
        $other = $this->actor();
        $this->actingAs($owner)->post(route('user.billing.transport.store'), $this->payload([$this->item()]));
        $e = TransportExpense::first();

        $this->actingAs($other)->post(route('user.billing.transport.submit', ['expense' => $e->id]))->assertStatus(403);
        $this->actingAs($other)->delete(route('user.billing.transport.destroy', ['expense' => $e->id]))->assertStatus(403);
    }

    public function test_Excelは30行ごとに複数シートになり合計が合う(): void
    {
        $u = $this->actor();
        $items = [];
        for ($i = 1; $i <= 33; $i++) {
            $items[] = $this->item(['occurrence_date' => sprintf('2026-08-%02d', ($i - 1) % 31 + 1), 'amount' => 100]);
        }
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload($items))->assertSessionHasNoErrors();
        $e = TransportExpense::first();

        $res = $this->actingAs($u)->get(route('user.billing.transport.excel', ['expense' => $e->id]));
        $res->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'tx') . '.xlsx';
        file_put_contents($tmp, $res->getContent());
        $wb = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        @unlink($tmp);

        $this->assertSame(2, $wb->getSheetCount());
        // 右上に「from SunBWork」と申請 No.（どのシートにも）
        foreach ([0, 1] as $i) {
            $this->assertSame('from SunBWork　申請No.' . $e->id, $wb->getSheet($i)->getCell('S2')->getValue());
        }
        $this->assertEquals(3000, $wb->getSheet(0)->getCell('X37')->getValue());   // 1枚目: 小計（30行）
        $this->assertEmpty($wb->getSheet(0)->getCell('S37')->getValue());          // 計の行に「－」は無い
        $this->assertSame('－', $wb->getSheet(0)->getCell('S36')->getValue());      // 明細の空行には残る
        $this->assertEquals(3300, $wb->getSheet(1)->getCell('X37')->getValue());   // 最終枚: 全体の合計（33行）
        $this->assertSame('8月29日', $wb->getSheet(1)->getCell('A7')->getFormattedValue());   // 日付順で 31〜33 件目
    }

    public function test_部門の選択肢は自社に登録された部署のみ(): void
    {
        $sb = $this->company('sunbrain', 'サン・ブレーン（確認用）');
        $sanei = $this->company('general', 'サンエー（確認用）');
        $mine = $this->dept($sb, '情報出版');
        $other = $this->dept($sanei, '総務部');
        $inactive = $this->dept($sb, '廃止部署', false);
        $u = $this->actor($sb);

        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item()], '2026-08', $mine->id))->assertSessionHasNoErrors();
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item()], '2026-07', $other->id))->assertSessionHasErrors('charge_department_id');
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload([$this->item()], '2026-07', $inactive->id))->assertSessionHasErrors('charge_department_id');

        // 画面に渡る選択肢も自社の有効な部署だけ
        $this->actingAs($u)->get(route('user.billing.transport.index'))
            ->assertInertia(fn ($page) => $page->has('departments', 1)->where('departments.0.name', '情報出版'));
    }

    public function test_サンブレーン様式は部門コードの数字を強調し_サンエーは部署名を表示する(): void
    {
        $sb = $this->company('sunbrain', 'サン・ブレーン（確認用）');
        $sanei = $this->company('general', 'サンエー（確認用）');

        $sheet = $this->sheetFor($this->actor($sb), $this->dept($sb, '製版')->id);
        $this->assertSame('部門コード', $sheet->getCell('A4')->getValue());
        $this->assertCount(1, $sheet->getDrawingCollection());               // ○ は 1 つだけ
        $this->assertSame('H5', $sheet->getDrawingCollection()[0]->getCoordinates());   // 30：製版 を囲む

        $sheet = $this->sheetFor($this->actor($sanei), $this->dept($sanei, '総務部')->id);
        $this->assertSame('部門', $sheet->getCell('A4')->getValue());
        $this->assertSame('総務部', $sheet->getCell('D4')->getValue());
        $this->assertSame('D4', $sheet->getDrawingCollection()[0]->getCoordinates());   // 部署名を囲む
        $this->assertEmpty($sheet->getCell('E4')->getValue());               // 数字コードの欄は消えている
        $this->assertEmpty($sheet->getCell('M5')->getValue());
    }

    public function test_PDFは30行ごとに改ページし_所属は会社名になる(): void
    {
        $sb = $this->company('sunbrain', '株式会社サン・ブレーン');
        $u = $this->actor($sb);
        $items = [];
        for ($i = 1; $i <= 31; $i++) {
            $items[] = $this->item(['occurrence_date' => sprintf('2026-08-%02d', $i), 'amount' => 100]);
        }
        $this->actingAs($u)->post(route('user.billing.transport.store'), $this->payload($items))->assertSessionHasNoErrors();
        $e = TransportExpense::first();

        $this->assertSame('サン・ブレーン', \App\Services\Billing\Transport\TransportExpenseExporter::companyName($e));

        $res = $this->actingAs($u)->get(route('user.billing.transport.pdf', ['expense' => $e->id]));
        $res->assertOk();
        $pdf = $res->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(2, preg_match_all('#/Type\s*/Page[^s]#', $pdf));   // 31 行 → 30 行 + 1 行の 2 ページ
    }
}

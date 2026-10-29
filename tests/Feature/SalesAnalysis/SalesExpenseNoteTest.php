<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Company;
use App\Models\Sales\SalesExpenseImport;
use App\Models\Sales\SalesExpenseLineItem;
use App\Models\Sales\SalesExpenseNote;
use App\Models\SalesAnalysisPermission;
use App\Models\User;
use App\Services\SalesAnalysis\SalesExpenseAliasService;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

/** Phase24-5: 経費メモ（科目・支払先・月へのコメント。編集・削除は書いた本人とSuperAdminのみ） */
class SalesExpenseNoteTest extends TestCase
{
    use RefreshesSalesDatabase;

    private function markCompanyAsSunbrain(): void
    {
        Company::find($this->salesTestCompanyId())->update(['code' => 'SUNBRAIN']);
    }

    private function clerk(string $name = 'クラーク'): User
    {
        $user = User::factory()->create(['user_role' => 'clerk', 'company_id' => $this->salesTestCompanyId(), 'name' => $name]);
        SalesAnalysisPermission::create(['user_id' => $user->id, 'enabled' => true]);

        return $user;
    }

    private function storeNote(User $user, array $overrides = [])
    {
        return $this->actingAs($user)->postJson(route('clerk.sales_analysis.api.expense_notes.store'), array_merge([
            'department_key' => 'production',
            'fiscal_year' => 2025,
            'category' => '外注加工費',
            'vendor_name' => 'A社',
            'calendar_month' => 6,
            'body' => '臨時案件のため増',
        ], $overrides));
    }

    private function index(User $user, int $fiscalYear = 2025, string $department = 'production')
    {
        return $this->actingAs($user)->getJson(route('clerk.sales_analysis.api.expense_notes.index', ['department_key' => $department, 'fiscal_year' => $fiscalYear]));
    }

    public function test_clerk_can_add_notes_at_department_category_and_vendor_level()
    {
        $this->markCompanyAsSunbrain();
        $clerk = $this->clerk('山田');

        $this->storeNote($clerk, ['category' => null, 'vendor_name' => null, 'calendar_month' => null, 'body' => '部署全体のメモ'])->assertStatus(201);
        $this->storeNote($clerk, ['vendor_name' => null, 'calendar_month' => null, 'body' => '科目のメモ'])->assertStatus(201);
        $created = $this->storeNote($clerk);
        $created->assertStatus(201);
        $created->assertJsonPath('author_name', '山田');
        $created->assertJsonPath('can_edit', true);
        $created->assertJsonPath('calendar_month', 6);

        $notes = collect($this->index($clerk)->json('notes'));
        $this->assertCount(3, $notes);
        $this->assertNotNull($notes->first(fn ($n) => $n['category'] === null && $n['vendor_name'] === null));
        $this->assertNotNull($notes->first(fn ($n) => $n['category'] === '外注加工費' && $n['vendor_name'] === null));
        $this->assertNotNull($notes->first(fn ($n) => $n['vendor_name'] === 'A社' && $n['calendar_month'] === 6));
    }

    public function test_index_returns_previous_year_notes_separately_and_scopes_by_department()
    {
        $this->markCompanyAsSunbrain();
        $clerk = $this->clerk();
        $this->storeNote($clerk, ['fiscal_year' => 2024, 'body' => '前年のメモ'])->assertStatus(201);
        $this->storeNote($clerk, ['fiscal_year' => 2025, 'body' => '今年のメモ'])->assertStatus(201);
        $this->storeNote($clerk, ['fiscal_year' => 2025, 'department_key' => 'planning', 'body' => '別部署のメモ'])->assertStatus(201);
        $this->storeNote($clerk, ['fiscal_year' => 2023, 'body' => '一昨年のメモ'])->assertStatus(201);

        $r = $this->index($clerk);
        $this->assertSame(['今年のメモ'], array_column($r->json('notes'), 'body'));
        $this->assertSame(['前年のメモ'], array_column($r->json('previous_notes'), 'body')); // 前年度だけ。一昨年・別部署は出ない
    }

    public function test_only_author_and_superadmin_can_edit_or_delete()
    {
        $this->markCompanyAsSunbrain();
        $author = $this->clerk('書いた人');
        $other = $this->clerk('他の人');
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);

        $id = $this->storeNote($author)->json('id');

        // 他の人: 見られるが編集不可（can_editがfalse）、更新・削除は403
        $seen = collect($this->index($other)->json('notes'))->first();
        $this->assertFalse($seen['can_edit']);
        $this->actingAs($other)->putJson(route('clerk.sales_analysis.api.expense_notes.update', ['note' => $id]), ['body' => '改ざん'])->assertForbidden();
        $this->actingAs($other)->deleteJson(route('clerk.sales_analysis.api.expense_notes.destroy', ['note' => $id]))->assertForbidden();
        $this->assertSame('臨時案件のため増', SalesExpenseNote::find($id)->body);

        // 本人: 本文と月を更新できる（付け先は変わらない）
        $update = $this->actingAs($author)->putJson(route('clerk.sales_analysis.api.expense_notes.update', ['note' => $id]), ['body' => '修正後', 'calendar_month' => 7]);
        $update->assertOk();
        $update->assertJsonPath('body', '修正後');
        $update->assertJsonPath('calendar_month', 7);
        $update->assertJsonPath('is_edited', true);
        $this->assertSame('A社', SalesExpenseNote::find($id)->vendor_name);

        // SuperAdmin: 他人のメモも編集・削除できる
        $this->actingAs($superadmin)->putJson(route('superadmin.sales_analysis.api.expense_notes.update', ['note' => $id]), ['body' => '管理者の修正'])->assertOk();
        $this->actingAs($superadmin)->deleteJson(route('superadmin.sales_analysis.api.expense_notes.destroy', ['note' => $id]))->assertOk();
        $this->assertNull(SalesExpenseNote::find($id));
    }

    public function test_notes_of_another_company_are_not_visible_or_modifiable()
    {
        $this->markCompanyAsSunbrain();
        $clerk = $this->clerk();
        $foreign = SalesExpenseNote::create([
            'company_id' => $this->salesTestCompanyId() + 999, 'fiscal_year' => 2025, 'department_key' => 'production',
            'category' => '外注加工費', 'vendor_name' => 'A社', 'body' => '他社のメモ', 'created_by' => $clerk->id,
        ]);

        $this->assertSame([], $this->index($clerk)->json('notes'));
        $this->actingAs($clerk)->putJson(route('clerk.sales_analysis.api.expense_notes.update', ['note' => $foreign->id]), ['body' => 'x'])->assertNotFound();
        $this->actingAs($clerk)->deleteJson(route('clerk.sales_analysis.api.expense_notes.destroy', ['note' => $foreign->id]))->assertNotFound();
        $this->assertNotNull(SalesExpenseNote::find($foreign->id));
    }

    public function test_validation_rejects_bad_input()
    {
        $this->markCompanyAsSunbrain();
        $clerk = $this->clerk();

        $this->storeNote($clerk, ['body' => ''])->assertStatus(422);
        $this->storeNote($clerk, ['body' => '   '])->assertStatus(422);
        $this->storeNote($clerk, ['body' => str_repeat('あ', 1001)])->assertStatus(422);
        $this->storeNote($clerk, ['calendar_month' => 13])->assertStatus(422);
        $this->storeNote($clerk, ['department_key' => 'unknown'])->assertStatus(422);
        $this->storeNote($clerk, ['category' => null, 'vendor_name' => 'A社'])->assertStatus(422); // 支払先には科目が必要
        $this->assertSame(0, SalesExpenseNote::count());

        $this->actingAs($clerk)->getJson(route('clerk.sales_analysis.api.expense_notes.index', ['department_key' => 'production']))->assertStatus(422);
    }

    public function test_notes_follow_alias_changes_and_survive_reimport()
    {
        $this->markCompanyAsSunbrain();
        $clerk = $this->clerk();
        $aliases = (new SalesExpenseAliasService())->forCompany($this->salesTestCompanyId());

        // 名寄せ前に、旧名（外注加工／西武プロセス）でメモを付ける
        $this->storeNote($clerk, ['category' => '外注加工', 'vendor_name' => '西武プロセス', 'body' => '旧名で付けたメモ'])->assertStatus(201);

        // あとから名寄せを承認すると、正式名の行にメモが表示される
        $aliases->saveGroup('production', '外注加工費', ['外注加工']);
        $aliases->saveVendorGroup('㈱西武プロセス', ['西武プロセス']);
        $note = collect($this->index($clerk)->json('notes'))->first();
        $this->assertSame('外注加工費', $note['category']);
        $this->assertSame('㈱西武プロセス', $note['vendor_name']);

        // 取込の版（再取込）とは切り離されている: 新しい版を作っても、メモは消えない・付け替わらない
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(), 'fiscal_year' => 2025, 'version' => 2, 'original_filename' => 'v2.xlsx',
            'file_sha256' => hash('sha256', 'v2'), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now(),
        ]);
        $this->assertNotNull($import->id);
        $this->assertCount(1, $this->index($clerk)->json('notes'));
    }

    public function test_new_notes_are_stored_under_canonical_names()
    {
        $this->markCompanyAsSunbrain();
        $clerk = $this->clerk();
        $aliases = (new SalesExpenseAliasService())->forCompany($this->salesTestCompanyId());
        $aliases->saveGroup('production', '外注加工費', ['外注加工']);
        $aliases->saveVendorGroup('㈱西武プロセス', ['西武プロセス']);

        $this->storeNote($clerk, ['category' => '外注加工', 'vendor_name' => '西武プロセス'])->assertStatus(201);

        $row = SalesExpenseNote::first();
        $this->assertSame('外注加工費', $row->category);
        $this->assertSame('㈱西武プロセス', $row->vendor_name);
    }

    public function test_vendor_notes_are_listed_across_years_and_departments_including_aliases()
    {
        $this->markCompanyAsSunbrain();
        $clerk = $this->clerk();
        $this->storeNote($clerk, ['fiscal_year' => 2024, 'department_key' => 'planning', 'body' => '企画の古いメモ'])->assertStatus(201);
        $this->storeNote($clerk, ['fiscal_year' => 2025, 'department_key' => 'production', 'body' => '制作の新しいメモ'])->assertStatus(201);
        $this->storeNote($clerk, ['vendor_name' => 'B社', 'body' => '別の支払先'])->assertStatus(201);
        $this->storeNote($clerk, ['vendor_name' => null, 'body' => '科目のメモ（支払先なし）'])->assertStatus(201);

        $r = $this->actingAs($clerk)->getJson(route('clerk.sales_analysis.api.expense_notes.vendor', ['vendor' => 'A社']));
        $r->assertOk();
        $notes = $r->json('notes');
        $this->assertSame(['制作の新しいメモ', '企画の古いメモ'], array_column($notes, 'body')); // 新しい年度順
        $this->assertSame(['production', 'planning'], array_column($notes, 'department_key'));

        // 別名で開いても、正式名のメモが出る
        (new SalesExpenseAliasService())->forCompany($this->salesTestCompanyId())->saveVendorGroup('A社', ['A株式会社']);
        $byAlias = $this->actingAs($clerk)->getJson(route('clerk.sales_analysis.api.expense_notes.vendor', ['vendor' => 'A株式会社']));
        $this->assertCount(2, $byAlias->json('notes'));
    }

    public function test_forbidden_for_non_sunbrain_company_and_users_without_access()
    {
        $clerk = $this->clerk();
        // SUNBRAIN以外の会社は利用不可
        $this->index($clerk)->assertForbidden();
        $this->storeNote($clerk)->assertForbidden();

        $this->markCompanyAsSunbrain();
        // 売上分析の権限が無いClerkはミドルウェアで拒否される
        $noAccess = User::factory()->create(['user_role' => 'clerk', 'company_id' => $this->salesTestCompanyId()]);
        $this->actingAs($noAccess)->getJson(route('clerk.sales_analysis.api.expense_notes.index', ['department_key' => 'production', 'fiscal_year' => 2025]))->assertForbidden();
    }

    public function test_export_includes_memo_column_and_department_memo()
    {
        $this->markCompanyAsSunbrain();
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(), 'fiscal_year' => 2025, 'version' => 1, 'original_filename' => 's.xlsx',
            'file_sha256' => hash('sha256', 'export-note'), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now(),
        ]);
        SalesExpenseLineItem::create([
            'sales_expense_import_id' => $import->id, 'department_key' => 'planning', 'category' => '外注加工費', 'category_order' => 1,
            'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 6, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1,
        ]);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_notes.store'), ['department_key' => 'planning', 'fiscal_year' => 2025, 'body' => '部署の注意点'])->assertStatus(201);
        $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_notes.store'), ['department_key' => 'planning', 'fiscal_year' => 2025, 'category' => '外注加工費', 'body' => '科目メモ'])->assertStatus(201);
        $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_notes.store'), ['department_key' => 'planning', 'fiscal_year' => 2025, 'category' => '外注加工費', 'vendor_name' => 'A社', 'calendar_month' => 6, 'body' => '=SUM(1+1)'])->assertStatus(201);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.expense_comparison.export', ['fiscal_year' => 2025]));
        $response->assertOk();
        $tmp = tempnam(sys_get_temp_dir(), 'exp') . '.xlsx';
        file_put_contents($tmp, $response->getContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        @unlink($tmp);

        $dept = $book->getSheet(1);
        $this->assertSame('部署メモ: 部署の注意点', $dept->getCell('A3')->getValue());
        $this->assertSame('メモ', $dept->getCell('T4')->getValue());
        $this->assertSame('科目メモ', $dept->getCell('T5')->getValue());
        // 支払先の行: 月つきのメモは「6月: 本文」。文字列型で書かれ、数式として解釈されない
        $this->assertSame('6月: =SUM(1+1)', $dept->getCell('T6')->getValue());
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $dept->getCell('T6')->getDataType());
    }

    public function test_export_neutralizes_memo_starting_with_formula_character()
    {
        $this->markCompanyAsSunbrain();
        $import = SalesExpenseImport::create([
            'company_id' => $this->salesTestCompanyId(), 'fiscal_year' => 2025, 'version' => 1, 'original_filename' => 's.xlsx',
            'file_sha256' => hash('sha256', 'export-note-2'), 'status' => 'completed', 'imported_by' => 1, 'imported_at' => now(),
        ]);
        SalesExpenseLineItem::create([
            'sales_expense_import_id' => $import->id, 'department_key' => 'planning', 'category' => '外注加工費', 'category_order' => 1,
            'vendor_name' => 'A社', 'calendar_year' => 2025, 'calendar_month' => 6, 'amount' => 1000, 'is_shared_split' => false, 'subtotal_group' => 1,
        ]);
        $superadmin = User::factory()->create(['user_role' => 'superadmin']);
        $this->actingAs($superadmin)->postJson(route('superadmin.sales_analysis.api.expense_notes.store'), ['department_key' => 'planning', 'fiscal_year' => 2025, 'category' => '外注加工費', 'body' => '=HYPERLINK("http://x","y")'])->assertStatus(201);

        $response = $this->actingAs($superadmin)->get(route('superadmin.sales_analysis.expense_comparison.export', ['fiscal_year' => 2025]));
        $tmp = tempnam(sys_get_temp_dir(), 'exp') . '.xlsx';
        file_put_contents($tmp, $response->getContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
        @unlink($tmp);

        $cell = $book->getSheet(1)->getCell('T5');
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame("'=HYPERLINK(\"http://x\",\"y\")", $cell->getValue());
    }
}

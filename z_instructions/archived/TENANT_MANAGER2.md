# TENANT_MANAGER2.md — company_id フィルター漏れ修正 進捗管理

## 作業フロー

1. TENANT_PLAN2.md を参照して対象を確認
2. 各タスクのコードを読む → 修正 → 動作確認
3. このファイルの進捗テーブルを更新
4. 全タスク完了後: npm run build → ChangelogSeeder 追記

---

## 進捗テーブル

| タスク | 対象ファイル | 内容 | 状態 | 備考 |
|--------|-------------|------|------|------|
| T-01 | BulkProjectJobController | index() User・Department フィルター | ✅ 完了 | |
| T-02 | BulkProjectJobController | sharedProps() User フィルター | ✅ 完了 | |
| T-03 | BulkProjectJobController | CSV インポート リーダー名マッチング | ✅ 完了 | store()のcompany_id欠落も同時修正 |
| T-04 | ProjectJobAssignmentsController | create/edit/show WorkItemType等フィルター | ✅ 完了 | companyFilter クロージャで3箇所統一 |
| T-05 | ProjectJobAssignmentsController | update/store exists バリデーション | ✅ 完了 | Rule::exists()に変更 |
| T-06 | ProgressTemplateController | create/edit Stage等フィルター | ✅ 完了 | |
| — | npm run build | ビルド確認 | ✅ 完了 | 全タスク完了 |
| — | ChangelogSeeder | 修正ログ追記 | ✅ 完了 | version: tenant-2 |

**状態凡例:** ⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ❌ ブロック

---

## Phase 1 — 高優先度（データ漏洩・不正入力防止）

### T-01: BulkProjectJobController — index()
**対象行:** 約 L41-54
- [ ] `$coordinatorCandidates` に `company_id` フィルター追加
- [ ] `$users` に `company_id` フィルター追加
- [ ] `$departments` に `company_id` フィルター追加
- [ ] `$members` に `company_id` フィルター追加
- [ ] `resolveCompanyId()` ヘルパーメソッド追加

### T-02: BulkProjectJobController — sharedProps()
**対象行:** 約 L596-601
- [ ] `coordinatorCandidates` に `company_id` フィルター追加
- [ ] `users` に `company_id` フィルター追加

### T-03: BulkProjectJobController — CSV インポート
**対象行:** 約 L371
- [ ] `User::where('name', ...)` に `->where('company_id', $companyId)` 追加
- [ ] `$companyId` の取得を追加（ `$request->user()->company_id` 等）

### T-05: ProjectJobAssignmentsController — バリデーション
**対象行:** L657, L794, L804
- [ ] `update()` の `user_id` バリデーションを `Rule::exists()` に変更
- [ ] `store()` の `assignments.*.user_id` を `Rule::exists()` に変更
- [ ] `store()` の `assignments.*.sender_id` を `Rule::exists()` に変更
- [ ] use 宣言に `Illuminate\Validation\Rule` を追加

---

## Phase 2 — 中優先度（送出データ最小化）

### T-04: ProjectJobAssignmentsController — create/edit/show
**対象行:** L268-270, L380-383, L542-545
- [ ] `WorkItemType` クエリに `whereNull/orWhere company_id` フィルター追加（3箇所）
- [ ] `Size` クエリに同フィルター追加（3箇所）
- [ ] `Stage` クエリに同フィルター追加（3箇所）
- [ ] `Status` クエリに同フィルター追加（3箇所）
- [ ] `resolveCompanyId()` ヘルパーを追加（または共通 Concern に切り出し）

### T-06: ProgressTemplateController — create/edit
**対象行:** L41-44, L108-111
- [ ] `Stage` クエリにフィルター追加（2箇所）
- [ ] `Size` クエリにフィルター追加（2箇所）
- [ ] `WorkItemType` クエリにフィルター追加（2箇所）

---

## 作業ログ

### 2026-06-02
- 調査完了。TENANT_PLAN2 / TENANT_MANAGER2 / TENANT2_PROMPT 作成
- ProjectJobController.create/edit/coordinatorCandidates() は修正済み（本フェーズ開始前に対応）
- AssignmentForm.vue はフロントで company_id フィルター済みと確認（T-04 は中優先に格下げ）
- T-01/T-02: BulkProjectJobController index()/sharedProps() を修正（User・Department に company_id フィルター追加）
- T-03: BulkProjectJobController validateRow() / findClientByFlexibleName() に companyId パラメータ追加。leader 名前マッチングも会社絞り込み。store() の company_id 欠落も同時修正
- T-05: ProjectJobAssignmentsController update()/store() の user_id・sender_id バリデーションを Rule::exists() に変更（company_id 条件付き）
- npm run build 成功（Phase 1 完了）

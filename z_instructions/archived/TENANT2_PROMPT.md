# TENANT2_PROMPT.md — 新セッション開始用プロンプト

## このセッションで行う作業

`z_instructions/TENANT_PLAN2.md` に基づき、Coordinator コントローラーの **company_id フィルター漏れ** を修正する。
進捗は `z_instructions/TENANT_MANAGER2.md` で管理する。

---

## 背景

マルチテナント構成（同一 DB を複数会社が共有）において、Coordinator・User ロールが
自社以外のユーザー・部署・マスターデータを参照・操作できてしまう問題が残存している。

**直前に修正済み（本フェーズ対象外）:**
- `Coordinator/ProjectJobController.coordinatorCandidates()` — company_id フィルター追加済み
- `Coordinator/ProjectJobController.create() / edit()` — User・Department フィルター追加済み

---

## 修正対象タスク一覧（TENANT_MANAGER2.md の進捗テーブルと対応）

### Phase 1（高優先度・先に完了させる）

| タスク | ファイル | 概要 |
|--------|---------|------|
| T-01 | BulkProjectJobController | index() の User/Department 全社クエリを company_id フィルターに修正 |
| T-02 | BulkProjectJobController | sharedProps() の User 全社クエリを修正 |
| T-03 | BulkProjectJobController | CSV インポートのリーダー名マッチングに company_id 追加 |
| T-05 | ProjectJobAssignmentsController | update/store の `exists:users,id` バリデーションを Rule::exists() に変更 |

### Phase 2（中優先度）

| タスク | ファイル | 概要 |
|--------|---------|------|
| T-04 | ProjectJobAssignmentsController | create/edit/show の WorkItemType/Size/Stage/Status を会社フィルター |
| T-06 | ProgressTemplateController | create/edit の Stage/Size/WorkItemType を会社フィルター |

---

## 重要な修正パターン

### company_id 解決ヘルパー
```php
private function resolveCompanyId(\App\Models\User $user): ?int
{
    if ($user->isSuperAdmin()) {
        return (int) (session('superadmin_context.company_id') ?? $user->company_id ?? 0) ?: null;
    }
    return $user->company_id ?? null;
}
```

### マスターデータ（全社共通 NULL を許容）
```php
$stages = Stage::where(function($q) use ($companyId) {
    $q->whereNull('company_id')->orWhere('company_id', $companyId);
})->orderBy('sort_order')->get(['id', 'name']);
```

### バリデーション
```php
use Illuminate\Validation\Rule;
$companyId = $request->user()->company_id;
'user_id' => ['nullable', Rule::exists('users', 'id')->where(function($q) use ($companyId) {
    $q->where('company_id', $companyId)->orWhereNull('company_id');
})],
```

---

## 注意事項

- `ghost` ユーザーは `User::withGhosts()` スコープ使用箇所があるため、company_id フィルターの影響確認が必要
- `Size`・`Stage`・`WorkItemType`・`Status` は `company_id = NULL` = 全社共通なので NULL 許容フィルターにする
- Vue ファイルの変更は不要（AssignmentForm.vue はフロントで company_id フィルター済み）
- 変更後は必ず `npm run build` を実行
- Artisan は `docker compose exec laravel bash -lc "php artisan ..."` で実行

---

## 完了条件

1. TENANT_MANAGER2.md の全タスクが ✅
2. `npm run build` 成功
3. ChangelogSeeder に追記・反映済み
4. PLAN/MANAGER/PROMPT を `z_instructions/archived/` に移動済み

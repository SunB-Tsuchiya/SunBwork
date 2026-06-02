# TENANT_PLAN2.md — company_id フィルター漏れ修正 詳細仕様

## 背景・目的

TENANT1 で project_jobs・クライアント・部署の基本的な会社隔離を実施済み。
今回は Coordinator・User コントローラーの残存するフィルター漏れを修正する。

**直前の修正済み箇所（本フェーズ対象外）:**
- `Coordinator/ProjectJobController.coordinatorCandidates()` — company_id フィルター追加済み
- `Coordinator/ProjectJobController.create() / edit()` — User・Department に company_id フィルター追加済み

---

## 根本原因の分析

### 重要度の分類

| 分類 | 説明 |
|------|------|
| 🔴 高 | 他社ユーザーが選択肢に出る / 他社 ID を POST できる |
| 🟡 中 | 全社データがネットワークに送出される（フロントでは正しくフィルター済み） |

---

## 修正対象一覧

### T-01: BulkProjectJobController — index() のユーザー・部署取得
**ファイル:** `app/Http/Controllers/Coordinator/BulkProjectJobController.php`
**重要度:** 🔴 高

```php
// 現状（問題）
$coordinatorCandidates = User::where('user_role', 'coordinator')...  // 全社
$users = User::orderBy('name')->get(['id', 'name']);                  // 全社
$departments = \App\Models\Department::all();                          // 全社
$members = User::orderBy('name')->with(['department', 'assignment'])->get(); // 全社
```

修正方針: ログインユーザーの `company_id` でフィルター。SuperAdmin はセッション context を参照。

---

### T-02: BulkProjectJobController — sharedProps() のユーザー取得
**ファイル:** `app/Http/Controllers/Coordinator/BulkProjectJobController.php`
**重要度:** 🔴 高

```php
// 現状（問題）
'coordinatorCandidates' => User::where('user_role', 'coordinator')... // 全社
'users'                 => User::orderBy('name')->get(['id', 'name']), // 全社
```

修正方針: T-01 と同様に company_id フィルターを追加。

---

### T-03: BulkProjectJobController — CSV インポートのリーダー名マッチング
**ファイル:** `app/Http/Controllers/Coordinator/BulkProjectJobController.php`
**重要度:** 🔴 高

```php
// 現状（問題）
$leader = User::where('name', $row['leader_name'])->first(); // 全社から名前検索
```

修正方針: `->where('company_id', $companyId)` を追加して同社のユーザーのみ対象にする。

---

### T-04: ProjectJobAssignmentsController — create/edit/show の WorkItemType・Size・Stage・Status 取得
**ファイル:** `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php`
**重要度:** 🟡 中

```php
// 現状（フロントでは company_id フィルター済み、しかし全社データを送出）
$types = WorkItemType::orderBy(...)->get([..., 'company_id', 'department_id']); // 全社
$sizes = Size::orderBy(...)->get([..., 'company_id', 'department_id']);          // 全社
$stages = Stage::orderBy(...)->get(['id', 'name', 'company_id', 'department_id']); // 全社
$statuses = Status::orderBy(...)->get([..., 'company_id', 'department_id']);     // 全社
```

※ AssignmentForm.vue は `typesGrouped()`, `sizesGrouped()`, `stagesForSelect()`, `statusesForSelect()` で `company_id` フィルター済み。
修正方針: `company_id IS NULL OR company_id = ?` でフィルターし送出データを最小化。

---

### T-05: ProjectJobAssignmentsController — update/store のバリデーション `exists:users,id`
**ファイル:** `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php`
**重要度:** 🔴 高

```php
// 現状（問題）— 他社ユーザー ID を指定できてしまう
'user_id'                    => 'nullable|exists:users,id',           // line 657
'assignments.*.user_id'      => 'nullable|exists:users,id',           // line 794
'assignments.*.sender_id'    => 'nullable|exists:users,id',           // line 804
```

修正方針: Laravel の `Rule::exists()` を使って `company_id` 条件を追加する。

```php
use Illuminate\Validation\Rule;
'user_id' => ['nullable', Rule::exists('users', 'id')->where('company_id', $companyId)],
```

ただし、`company_id` が NULL のユーザー（テストユーザー等）も存在する可能性があるため、
`where('company_id', $companyId)->orWhereNull('company_id')` で対応。

---

### T-06: ProgressTemplateController — create/edit の Stage・Size・WorkItemType 取得
**ファイル:** `app/Http/Controllers/Coordinator/ProgressTemplateController.php`
**重要度:** 🟡 中

```php
// 現状（問題）
Stage::orderBy('id')->get(['id', 'name'])                      // 全社
Size::orderBy('sort_order')->orderBy('name')->get([...])       // 全社
WorkItemType::orderBy('id')->get(['id', 'name', 'group'])      // 全社
```

修正方針: `company_id IS NULL OR company_id = ?` でフィルター。

---

## 修正パターン（共通）

### ユーザー取得
```php
$companyId = $this->resolveCompanyId($request->user());

// ユーザー（同社のみ）
$users = User::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']);
```

### マスターデータ取得（会社固有 OR 共通）
```php
// company_id=NULL（全社共通）または自社のみ
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

### company_id 解決ヘルパー（各コントローラーに追加）
```php
private function resolveCompanyId(\App\Models\User $user): ?int
{
    if ($user->isSuperAdmin()) {
        return (int) (session('superadmin_context.company_id') ?? $user->company_id ?? 0) ?: null;
    }
    return $user->company_id ?? null;
}
```

---

## 変更ファイル一覧

| # | ファイル | 変更内容 |
|---|---------|---------|
| 1 | `app/Http/Controllers/Coordinator/BulkProjectJobController.php` | T-01/T-02/T-03 |
| 2 | `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php` | T-04/T-05 |
| 3 | `app/Http/Controllers/Coordinator/ProgressTemplateController.php` | T-06 |

Vue ファイルの変更は不要（フロントは既に company_id フィルター済み）。
`npm run build` は PHP のみの変更でも念のため実行する。

---

## フェーズ別タスク

### Phase 1: 高優先度（データ漏洩・不正入力防止）
1. T-01: BulkProjectJobController index() 修正
2. T-02: BulkProjectJobController sharedProps() 修正
3. T-03: BulkProjectJobController CSV インポート修正
4. T-05: ProjectJobAssignmentsController バリデーション修正

### Phase 2: 中優先度（送出データ最小化）
5. T-04: ProjectJobAssignmentsController create/edit/show 修正
6. T-06: ProgressTemplateController 修正

---

## 注意事項

- `Size`・`Stage`・`WorkItemType`・`Status` は `company_id = NULL` が「全社共通」を意味する可能性があるため、NULL も許容するフィルターにする
- `ghost` ユーザーは `User::withGhosts()` スコープを使っている箇所があり、`company_id` のフィルター挙動が異なる場合があるため慎重に確認する
- バリデーション修正は既存テストへの影響確認が必要

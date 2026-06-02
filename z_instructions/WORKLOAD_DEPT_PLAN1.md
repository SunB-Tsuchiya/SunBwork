# WORKLOAD_DEPT_PLAN1 — ワークロード設定の部署スコープ対応

## 概要

`workload-setting`（Stages / WorkItemTypes / Sizes / Statuses / Difficulties）の各設定を
「会社全体（department_id = NULL）」と「部署固有（department_id = X）」で
完全独立して登録・編集できるようにする。

---

## 要件

| 項目 | 内容 |
|------|------|
| 会社 / 部署の関係 | **完全独立**（継承なし）— 会社スコープと部署スコープは別リスト |
| ワークロード分析での優先 | 部署スコープが存在すれば部署優先、なければ会社スコープを使う（将来実装） |
| Leader | 自分の部署のみ編集可。会社全体・他部署はグレー表示で操作不可 |
| SuperAdmin / Admin | 全スコープに切り替え可能 |

---

## スコープ定義

| scope_key | department_id | 意味 |
|-----------|--------------|------|
| `'company'` | `NULL` | 会社全体の共通設定 |
| `'5'`（数値文字列） | `5` | department_id=5 の部署固有設定 |

---

## DB 変更

**なし。** 対象テーブル（stages / work_item_types / sizes / statuses / difficulties）には
`department_id` カラムがすでに `nullable` で存在している。
既存レコードはすべて `department_id = NULL` のため、会社スコープに自動分類される。

---

## 変更ファイル一覧

| ファイル | 変更内容 |
|---------|---------|
| `app/Http/Controllers/WorkloadSettingController.php` | resolveScope / fetchDepartments ヘルパー追加、index / edit / store を部署スコープ対応に変更 |
| `resources/js/Pages/WorkloadSetting/Index.vue` | departments / currentScope / canEditScope props 追加、部署スコープバー UI 追加 |
| `resources/js/Pages/WorkloadSetting/Edit.vue` | 同上 + save() 時に department_id を POST に含める |

routes/web.php の変更は不要（クエリパラメータ `?dept=` で対応）。

---

## コントローラー設計

### resolveScope（新規ヘルパー）

```php
private function resolveScope(Request $request, $user, int $companyId): array
```

- SuperAdmin / Admin: クエリパラメータ `?dept=company|{id}` を読む（デフォルト: company）
- Leader: `$user->department_id` を強制使用（クエリ無視）
- 戻り値: `['department_id' => null|int, 'scope_key' => 'company'|'5']`

### fetchDepartments（新規ヘルパー）

```php
private function fetchDepartments(int $companyId): Collection
```

`departments` テーブルから `company_id = X` の active な部署一覧を返す。

### fetchItems の変更

```php
private function fetchItems(string $modelClass, string $orderBy, ?int $companyId, ?int $departmentId = null)
```

- `departmentId = null`（会社スコープ）:
  `WHERE (company_id IS NULL OR company_id = X) AND department_id IS NULL`
- `departmentId = Y`（部署スコープ）:
  `WHERE company_id = X AND department_id = Y`

### store の変更

1. POST ボディから `department_id` は受け取らず、サーバー側で `resolveScope` により決定
2. Leader の場合: 自部署以外のスコープで POST されたら 403
3. `department_id` を除外しない（`company_id` と同様に fillable から除外し、明示的に設定）

---

## Vue 設計

### 部署スコープバー（Index.vue / Edit.vue 共通）

```
[会社全体]  [情報出版]  [制作]  [校正]
```

- currentScope === 'company' → 会社全体ボタンがアクティブ
- currentScope === '5' → department_id=5 のボタンがアクティブ
- canEditScope = false（Leader）: 自部署ボタン以外は `disabled` + グレー

### スコープ切り替え

```js
function switchScope(scopeKey) {
    router.get(
        route('workload_setting.index'), // or edit
        scopeKey !== 'company' ? { dept: scopeKey } : {},
        { preserveState: false }
    );
}
```

### Edit.vue save() の変更

```js
router.post(
    route('workload_setting.store', { type: props.type }),
    {
        items: toRaw(state.items),
        group_orders: ...,
        scope: props.currentScope,  // 'company' or '5'
    },
    ...
)
```

---

## 権限マトリクス

| ロール | 会社全体 | 自部署 | 他部署 |
|--------|---------|--------|--------|
| SuperAdmin | ✓ 編集可 | ✓ 編集可 | ✓ 編集可 |
| Admin | ✓ 編集可 | ✓ 編集可 | ✓ 編集可 |
| Leader（workload_setting=true） | ✗ 表示のみ | ✓ 編集可 | ✗ 表示のみ |

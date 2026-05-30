# COSHARE_PLAN1 — クライアント会社間共有設計

## 概要

`company_clients` 中間テーブルを新設し、クライアントをグループ共通マスターとして管理しながら、  
各会社（サン・ブレーン / サンエー印刷 / 将来のグループ会社）ごとに表示・編集スコープを隔離する。

---

## 現状（本番確認済み 2026-05-30）

| 項目 | 値 |
|---|---|
| companies | 1: Superadmin, **2: サン・ブレーン**, **3: サンエー印刷** |
| clients 総件数 | 44件（company_id=2: 41件、company_id=NULL: 3件） |
| company_id=NULL の内訳 | NTS(id=39), NTS(2)(id=40), その他(id=50) |
| client_departments | 58件（全て dept_id=1〜3 → サン・ブレーン部署） |
| サンエー印刷の部署 | 総務部(id=4)・経理部(id=5)・営業部(id=6) → client_departments に紐付きなし |
| company_clients | **テーブル未存在** |

### 現在のバグ原因

`ClientController::index()` で `admin` ロールは `forCompany` スコープが適用されず全クライアントを取得。  
→ サンエー印刷 Admin がサン・ブレーンのクライアント44件・部署タブを全て閲覧できてしまう。

---

## 目標状態

- `clients` テーブル = グループ共通マスター（名前・コードは全社共通）
- `company_clients` 中間テーブル = 「どの会社がこのクライアントを使うか」を管理
- 各会社の Admin/Leader/Coordinator は **自社登録クライアントのみ** 表示・操作
- SuperAdmin は全クライアントを横断表示（現行通り）
- 部署タブ・部署選択は **自社の部署のみ** 表示

---

## DB 設計

### 新規テーブル: `company_clients`

```sql
CREATE TABLE company_clients (
  company_id BIGINT UNSIGNED NOT NULL,
  client_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (company_id, client_id),
  CONSTRAINT fk_cc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_cc_client  FOREIGN KEY (client_id)  REFERENCES clients(id)   ON DELETE CASCADE
);
```

### データ移行（マイグレーション内で実行）

```php
// 既存の全クライアント（company_id=2 および NULL）を サン・ブレーン(id=2) として登録
DB::table('clients')->get(['id'])->each(function ($c) {
    DB::table('company_clients')->insertOrIgnore([
        'company_id' => 2,
        'client_id'  => $c->id,
    ]);
});
```

> NULL の3件（NTS, NTS(2), その他）もサン・ブレーンに帰属させる。  
> `clients.company_id` カラムは削除せず残す（既存コードの安全網として）。

### `client_code` ユニーク制約

グローバル一意のまま維持（同一クライアント = 同一レコード。別社が同コードを使う場合は `company_clients` に追加登録）。  
バリデーションルールも全社スコープに統一する。

---

## フェーズ別タスク

### Phase 1: DB + Model + Policy（最優先・データ隔離の核心）

**変更ファイル:**
- `database/migrations/2026_05_30_XXXXXX_create_company_clients_table.php`（新規）
- `app/Models/Client.php`
- `app/Policies/ClientPolicy.php`

**内容:**

#### Client モデル

```php
// 追加
public function companies(): BelongsToMany
{
    return $this->belongsToMany(Company::class, 'company_clients');
}

// 変更: company_id カラムではなく company_clients を参照
public function scopeForCompany($query, $companyId)
{
    if (empty($companyId)) {
        return $query->whereDoesntHave('companies');
    }
    return $query->whereHas('companies', fn($q) => $q->where('companies.id', $companyId));
}
```

#### ClientPolicy

```php
// view / update / delete を company_clients ベースに変更
public function view(User $user, Client $client): bool
{
    if ($user->user_role === 'superadmin') return true;
    if (!$user->company_id) return false;
    return $client->companies()->where('companies.id', $user->company_id)->exists();
}
// update / delete も同様
```

---

### Phase 2: Controller（表示・操作スコープの修正）

**変更ファイル:**
- `app/Http/Controllers/ClientController.php`

**変更メソッド:**

| メソッド | 変更内容 |
|---|---|
| `index()` | admin も `forCompany` 適用（superadmin のみ全件）。部署タブ用に `$companyDepts` を追加 pass |
| `create()` | departments を自社のみに絞る |
| `store()` | `clients.company_id` 設定に加え `company_clients` にも INSERT |
| `edit()` | departments を自社のみに絞る |
| `update()` | `client_code` ユニークルールをグローバルに統一 |
| `csvUpload()` / `csvPreview()` / `csvStore()` | departments を自社のみ。csvStore で `company_clients` INSERT |
| `clientsJson()` | admin も forCompany 適用 |
| `checkDuplicate()` | admin も forCompany 適用 |
| `merge()` / `batchMerge()` | マージ後 source の `company_clients` を target に移す |

**`index()` 変更後イメージ:**

```php
$isSuperAdmin = $user->user_role === 'superadmin';
$query = Client::with('departments:id,name');
if (!$isSuperAdmin) {
    $query->forCompany($user->company_id ?? null);
}
$companyDepts = $isSuperAdmin
    ? []
    : Department::where('company_id', $user->company_id)->orderBy('id')->get(['id', 'name']);

return Inertia::render('Clients/Index', [
    'clients'     => $clients,
    'showDormant' => $showDormant,
    'departments' => $companyDepts,  // ← 新規追加
]);
```

---

### Phase 3: Vue（部署タブ・表示の修正）

**変更ファイル:**
- `resources/js/Pages/Clients/Index.vue`

**変更内容:**
- `departments` prop を追加（サーバーから受け取る）
- `allDepartments` computed をやめ、props の `departments` をタブ表示に使う
- `DEPT_COLORS` ハードコード（情報出版/製版/オンデマンド）を除去し、部署 id ベースの汎用カラーに変更

> Create.vue / Edit.vue はコントローラー側の部署フィルタリングのみで対応可能（Vue 変更不要）。

---

### Phase 4: SuperAdmin 拡張（後日対応）

- クライアント編集画面で「利用会社」を multi-select で管理できる UI
- クライアント一覧で会社バッジを表示
- 「同コードのクライアントが既存」の場合に「自社に追加登録」のフローを追加

---

## 変更ファイル一覧

| ファイル | Phase | 変更種別 |
|---|---|---|
| `database/migrations/2026_05_30_XXXXXX_create_company_clients_table.php` | 1 | 新規 |
| `app/Models/Client.php` | 1 | 修正（scope + relationship） |
| `app/Policies/ClientPolicy.php` | 1 | 修正（company_clients ベース） |
| `app/Http/Controllers/ClientController.php` | 2 | 修正（多数のメソッド） |
| `resources/js/Pages/Clients/Index.vue` | 3 | 修正（部署タブ） |

計 5ファイル（+1 マイグレーション新規）

---

## 既存データへの影響

| 対象 | 影響 |
|---|---|
| サン・ブレーン 44件のクライアント | `company_clients` に company_id=2 で登録 → 表示・操作は従来通り |
| `clients.company_id` カラム | 残す。store/update でも引き続き設定（互換性維持） |
| `client_departments` 58件 | 変更なし |
| `project_jobs` 88件 | 変更なし |
| サンエー印刷 | 初期状態では `company_clients` に登録なし → 一覧は空欄で正常 |

---

## 注意事項

1. マイグレーションは本番適用前にローカルで動作確認必須
2. `forCompany` スコープ変更は `clientsJson` / `checkDuplicate` にも影響するため全メソッドで確認
3. `merge()` で source の `company_clients` を target に移す処理（`syncWithoutDetaching`）を忘れると、マージ後に target がある会社から見えなくなる可能性あり

# COTYPE_PLAN1.md — 会社タイプ別機能分離 設計書 v3（最終版）

---

## 組織構造（確定）

```
株式会社サンエー印刷（新規登録・親会社）
├── 総務 / 経理 / 営業 / その他
└── company_type: 'general' → ベース機能のみ

株式会社サン・ブレーン（既存 SUNBRAIN・グループ会社）
├── 情報出版部署  → ProofCoordinator / JobBox / GhostUsers / Subcontractors
├── 製版部署      → Prepress
└── オンデマンド部署（将来） → 独自機能
└── company_type: 'sunbrain' → 部署ごとに機能追加

SuperAdmin Company → 廃止
└── SuperAdmin ユーザーはいずれの会社にも属さない（会社横断権限）
    └── 個人記録（日報・工数）用に「ホーム会社」を設定可能 → サン・ブレーン
```

---

## 会社タイプ定義

| company_type | 対象 | 追加機能 |
|---|---|---|
| `'sunbrain'` | 株式会社サン・ブレーン | 部署ごとに機能追加（後述） |
| `'general'` | 株式会社サンエー印刷・将来のグループ各社 | ベースのみ |

### サン・ブレーン内の部署ごとの機能

| 部署 | 機能 | 制御方法 |
|---|---|---|
| 情報出版 | ProofCoordinator / JobBox / GhostUsers / Subcontractors | `department.module = 'publishing'` |
| 製版 | Prepress | `department.module = 'prepress'`（既存 `isPrepressDepartment` を拡張） |
| オンデマンド | 将来追加 | `department.module = 'ondemand'` |
| その他 | なし | `department.module = null` |

---

## ベース機能（全 company_type 共通）

ProjectJob 進行管理 / マイプロジェクト / 日報 / カレンダー / Leader チーム管理 /
チャット / メッセージ / お知らせ / ワークロード解析 / AI / 工数記録 / 設定

---

## SuperAdmin コンテキスト切り替え機能

### 概念

SuperAdmin はすべての会社・機能に横断アクセスできる特権ユーザー。
しかし「今どの会社の画面を見ているか」が混在するとメニューが煩雑になる。
→ **コンテキスト切り替え**で「今どの会社の Admin として動くか」を明示する。

```
[グローバル管理]  ← 全会社管理・システム設定（SuperAdmin 専用ビュー）
      ↕ ヘッダーのスイッチャーで切り替え
[サン・ブレーン - Admin] ← サン・ブレーンの Admin として操作・日報記録等
[サンエー印刷 - Admin]   ← サンエー印刷の Admin として操作
[将来のグループ会社 - Admin]
```

### データ設計

```
users テーブル（SuperAdmin 向け）
├── company_id:      NULL（どの会社にも所属しない）
├── home_company_id: サン・ブレーンの company_id（個人記録用の紐付け先）
└── ※ home_company_id は新規カラム
```

セッション:
```
session('superadmin_context') = [
  'company_id' => null,      // null = グローバル管理モード
]
// または
session('superadmin_context') = [
  'company_id' => 2,         // サン・ブレーン ID = 会社 Admin モード
]
```

### コンテキスト別の動作

| コンテキスト | 表示されるメニュー | 個人記録の会社 |
|---|---|---|
| グローバル（null） | SuperAdmin 全体管理メニュー | home_company_id |
| サン・ブレーン | Admin + サン・ブレーン各部署ロールボタン | サン・ブレーン |
| サンエー印刷 | Admin + ベースロールボタン | サンエー印刷 |

### バックエンド実装

```php
// HandleInertiaRequests.php
// SuperAdmin のコンテキストに応じて companyType を返す
$company = null;
if ($request->user()?->isSuperAdmin()) {
    $ctxId = session('superadmin_context.company_id');
    $company = $ctxId ? Company::find($ctxId) : null;
} else {
    $company = $request->user()?->company;
}

'companyType'       => $company?->company_type ?? 'global',
'superAdminContext' => $request->user()?->isSuperAdmin()
    ? session('superadmin_context.company_id')  // null = グローバル
    : null,
```

新規エンドポイント:
```
POST /superadmin/switch-context   → session に company_id をセット → Inertia::location(back)
```

### フロントエンド: SuperAdminContextSwitcher.vue

ヘッダー右側（通知ボタン付近）に配置:

```
[地球アイコン] グローバル ▼
  ├── 🌐 グローバル管理
  ├── ── サン・ブレーン
  │     └── 👤 Admin として切り替え
  └── ── サンエー印刷
        └── 👤 Admin として切り替え
```

- 現在のコンテキストを常にヘッダーに表示（視覚的に明確化）
- グローバルモード時: ヘッダーに「🌐 全社管理」バッジ表示
- 会社 Admin モード時: 「[会社名] - Admin」バッジ表示

---

## アーキテクチャ: モジュールレジストリ方式

### ディレクトリ構成

```
resources/js/
  CompanyModules/
    index.js              ← レジストリ（1行追加で新モジュール登録）
    sunbrain.js           ← サン・ブレーンモジュール（情報出版 + 製版 + 将来のオンデマンド）
    general.js            ← サンエー印刷等（extraRoles なし）
    ondemand.js           ← 将来追加（このファイル1枚 + index.js 1行）
  Components/
    CompanyModuleNavButtons.vue   ← モジュールナビボタンの自動描画
    SuperAdminContextSwitcher.vue ← SuperAdmin 専用コンテキスト切り替えUI
```

### モジュール定義

```js
// CompanyModules/sunbrain.js
export default {
  companyType: 'sunbrain',
  extraRoles: [
    {
      role: 'proof_coordinator',
      label: 'Proof Admin',
      routeName: 'proof_coordinator.dashboard',
      routePrefix: 'proof_coordinator.',
      activeColor: 'bg-pink-600 text-white font-semibold',
      textColor: 'text-pink-600 hover:text-pink-800',
      // 情報出版部署のユーザー or Admin/SuperAdmin
      visibilityCheck: (auth) =>
        auth.user.departmentModule === 'publishing' ||
        ['superadmin', 'admin'].includes(auth.user.user_role),
    },
    {
      role: 'prepress',
      label: 'Prepress',
      routeName: 'prepress.dashboard',
      routePrefix: 'prepress.',
      activeColor: 'bg-green-700 text-white font-semibold',
      textColor: 'text-green-700 hover:text-green-900',
      // 製版部署のユーザー or Admin/SuperAdmin
      visibilityCheck: (auth) =>
        auth.user.isPrepressDepartment ||
        ['superadmin', 'admin'].includes(auth.user.user_role),
    },
    // 将来: ondemand ロールをここに追加
  ],
}
```

```js
// CompanyModules/index.js
import sunbrain from './sunbrain'
import general  from './general'
// import ondemand from './ondemand'  ← 将来: 1行追加するだけ

export const companyModules = {
  sunbrain,
  general,
  // ondemand,
}
```

### CompanyModuleNavButtons.vue（変更なし）

```vue
<script setup>
import { computed } from 'vue'
import { companyModules } from '@/CompanyModules/index.js'

const props = defineProps({ auth: Object, roleNavClass: Function })
const emit = defineEmits(['navigate'])

const extraRoles = computed(() => {
  const mod = companyModules[props.auth.companyType]
  if (!mod) return []
  return mod.extraRoles.filter(r =>
    r.visibilityCheck ? r.visibilityCheck(props.auth) : true
  )
})
</script>
<template>
  <template v-for="r in extraRoles" :key="r.role">
    <button type="button" @click="emit('navigate', r.role)" :class="roleNavClass(r.role)">
      {{ r.label }}
    </button>
  </template>
</template>
```

---

## DB 変更

### 1. companies テーブル

```php
// migration: add_company_type_to_companies
Schema::table('companies', function (Blueprint $table) {
    $table->string('company_type', 32)->default('general')->after('code');
});

// データ更新（マイグレーション内で同時実行）
DB::table('companies')->where('code', 'SUNBRAIN')->update(['company_type' => 'sunbrain']);
// SuperAdmin Company は別途 SuperAdmin 画面から削除（または code='SUPERADMIN' をそのまま残し非表示に）
```

### 2. departments テーブル

```php
// migration: add_module_to_departments
Schema::table('departments', function (Blueprint $table) {
    $table->string('module', 32)->nullable()->after('name');
    // 例: 'publishing' | 'prepress' | 'ondemand' | null
});

// データ更新
DB::table('departments')->where('name', '情報出版')->update(['module' => 'publishing']);
DB::table('departments')->where('name', '製版')->update(['module' => 'prepress']);
```

### 3. users テーブル（SuperAdmin 向け）

```php
// migration: add_home_company_id_to_users
Schema::table('users', function (Blueprint $table) {
    $table->unsignedBigInteger('home_company_id')->nullable()->after('company_id');
    $table->foreign('home_company_id')->references('id')->on('companies')->nullOnDelete();
});
```

---

## Inertia 共有データ

```php
// HandleInertiaRequests.php に追加
$company = null;
if ($request->user()?->isSuperAdmin()) {
    $ctxId = session('superadmin_context.company_id');
    $company = $ctxId ? \App\Models\Company::find($ctxId) : null;
} else {
    $company = $request->user()?->company;
}

// auth 配列に追加
'companyType'         => $company?->company_type ?? 'global',
'departmentModule'    => \App\Models\Department::find($request->user()?->department_id)?->module,
'superAdminContextId' => $request->user()?->isSuperAdmin()
    ? session('superadmin_context.company_id')
    : null,
```

---

## ルート追加

```php
// routes/web.php に追加
// SuperAdmin コンテキスト切り替え
Route::post('/superadmin/switch-context', [SuperAdminContextController::class, 'switch'])
    ->middleware(['auth', 'role:superadmin'])
    ->name('superadmin.switch_context');

// サン・ブレーン専用ルートグループ
Route::middleware(['auth', 'company_type:sunbrain'])->group(function () {
    // ProofCoordinator, JobBox, GhostUsers, Subcontractors, Prepress ルート
});
```

---

## 変更ファイル一覧（フェーズ別）

### Phase 1: DB + モデル + ミドルウェア

| # | ファイル | 変更 |
|---|---|---|
| 1 | `database/migrations/xxxx_add_company_type_to_companies.php` | 新規。SUNBRAIN→sunbrain の更新含む |
| 2 | `database/migrations/xxxx_add_module_to_departments.php` | 新規。情報出版→publishing, 製版→prepress |
| 3 | `database/migrations/xxxx_add_home_company_id_to_users.php` | 新規 |
| 4 | `app/Models/Company.php` | company_type fillable + `isPublishing()` `isSunbrain()` 等 |
| 5 | `app/Models/Department.php` | module fillable 追加 |
| 6 | `app/Models/User.php` | homeCompany リレーション追加 |
| 7 | `app/Http/Middleware/CheckCompanyType.php` | 新規 |
| 8 | `bootstrap/app.php` | ミドルウェアエイリアス登録 |

### Phase 2: ルート保護

| # | ファイル | 変更 |
|---|---|---|
| 9 | `routes/web.php` | sunbrain 専用グループ追加。SuperAdmin context 切り替えルート追加 |
| 10 | `app/Http/Controllers/SuperAdmin/ContextController.php` | 新規（switch-context エンドポイント） |

### Phase 3: モジュールレジストリ + フロントエンド

| # | ファイル | 変更 |
|---|---|---|
| 11 | `app/Http/Middleware/HandleInertiaRequests.php` | companyType / departmentModule / superAdminContextId 追加 |
| 12 | `resources/js/CompanyModules/sunbrain.js` | 新規 |
| 13 | `resources/js/CompanyModules/general.js` | 新規 |
| 14 | `resources/js/CompanyModules/index.js` | 新規 |
| 15 | `resources/js/Components/CompanyModuleNavButtons.vue` | 新規 |
| 16 | `resources/js/Components/SuperAdminContextSwitcher.vue` | 新規 |
| 17 | `resources/js/layouts/AppLayout.vue` | proof_coordinator/prepress ハードコード削除 → CompanyModuleNavButtons + SuperAdminContextSwitcher 組み込み |

### Phase 4: SuperAdmin 管理 UI

| # | ファイル | 変更 |
|---|---|---|
| 18 | `resources/js/Pages/SuperAdmin/Companies/` 該当ファイル | company_type セレクト追加 |
| 19 | `resources/js/Pages/SuperAdmin/Departments/` 該当ファイル | module セレクト追加（情報出版/製版/オンデマンド/なし）|

### Phase 5: 会社登録・動作確認

| 作業 | 内容 |
|---|---|
| SuperAdmin Company を SuperAdmin 画面から削除 | SuperAdmin ユーザーの company_id → NULL に |
| SuperAdmin の home_company_id → サン・ブレーン ID に設定 | |
| 株式会社サンエー印刷を登録（company_type: general） | |
| テストユーザー（サンエー印刷）を作成して動作確認 | |

---

---

## Phase 6: featureFlags — 部署別ジョブフロー UI ガード（追記 2026-05-29）

### 背景・目的

校正依頼などのジョブフローは情報出版部署（`module='publishing'`）特有のもの。  
製版・その他の部署ユーザーが MyJobBox などで「校正依頼」ボタンを見たとき、  
押してもエラーになるだけでなくユーザーが混乱する。  
→ **フィーチャーフラグ** として `auth.featureFlags` を Inertia 共有データに追加し、  
各コンポーネントはロジック不要でフラグを参照するだけにする。

### 設計: featureFlags

```php
// HandleInertiaRequests.php に追加
'featureFlags' => (function () use ($user, $contextCompany) {
    if (! $user) return [];
    $module    = \App\Models\Department::find($user->department_id)?->module;
    $isSunbrain = $contextCompany?->company_type === 'sunbrain';
    $isAdminUp  = in_array($user->user_role, ['superadmin', 'admin']);
    return [
        // 情報出版ジョブフロー（校正依頼ボタン・校正依頼履歴等）
        'proofRequest'  => $isSunbrain && ($isAdminUp || $module === 'publishing'),
        // 製版ボードへのアクセス（将来: Prepress 専用 UI に使用）
        'prepressBoard' => $isSunbrain && ($isAdminUp || $module === 'prepress'),
    ];
})(),
```

### フロントエンド利用方法

```vue
<!-- 例: MyJobBox/Show.vue -->
<button v-if="$page.props.auth.featureFlags.proofRequest && !isCompleted">
  校正依頼
</button>
```

### 今回の適用対象（Phase 6 即時実装）

| ファイル | 修正内容 |
|---|---|
| `HandleInertiaRequests.php` | `featureFlags` 追加 |
| `Pages/MyJobBox/Show.vue` | 校正依頼ボタン・校正依頼済みバッジを `featureFlags.proofRequest` でガード |
| `Pages/User/ProjectJobs/Show.vue` | 校正依頼セクション全体を `featureFlags.proofRequest` でガード |
| `Pages/Coordinator/ProjectJobs/Show.vue` | 校正依頼履歴セクションを `featureFlags.proofRequest` でガード |

### Phase 6-B: 進行表・管理シートの校正ガード（追記 2026-05-29）

**方針: 「校正セルは残す・アクションだけ止める」**

既存 proof_v2 セルには設定済みデータ（担当者・完了状態）がある。  
セルごと非表示にすると既存データが見えなくなるため、  
**「校正管理へ依頼」選択肢とそれを受け取るモーダルだけを `featureFlags.proofRequest` でガード**する。

| ファイル | 変更内容 |
|---|---|
| `Components/ProgressCell.vue` | `proof_v2` セルの `<option value="proof_coordinator">校正管理へ依頼</option>` をガード。`usePage()` で featureFlags を参照 |
| `Pages/Coordinator/ProgressSheets/Show.vue` | ProofRequestModal + 締切延長モーダルを featureFlags.proofRequest でガード |
| `Pages/Coordinator/WorkflowSheets/Show.vue` | 同上 |

**この変更後の動作:**
- `featureFlags.proofRequest === true` (情報出版 / Admin / SuperAdmin): 従来通り
- `featureFlags.proofRequest === false` (製版・その他): セルに担当者は表示されるが「校正管理へ依頼」は選択不可。モーダルは開かない

### 将来対応

| 対象 | 方針 |
|---|---|
| `Pages/User/ProofJobs/*` (3ページ) | ルートに `company_type:sunbrain` + 部署ミドルウェア追加 |
| `Pages/User/ProofStatus.vue` | 同上 |
| ColumnTreeEditor の列型選択 | `proof_v2` / `proof_user` 型を `featureFlags.proofRequest` でのみ追加可能にする（将来） |

### 拡張ロードマップ（将来: 管理画面から ON/OFF 設定）

```
company_feature_flags テーブル案（将来）
  company_id | department_module | feature_key    | enabled
  1          | publishing        | proof_request  | true
  1          | prepress          | prepress_board | true
  2          | null              | *              | false
```

この構造にすれば SuperAdmin 管理画面から機能の ON/OFF が可能になる。  
各社・各部署のジョブフロー特有の機能を段階的に追加・無効化できる仕組みへ発展させる。

---

---

## Phase 7: SuperAdmin UX 改善（追記 2026-05-30）

### 7-A: 在籍ボード（IrukaBoard）のコンテキスト対応

**問題:** `DashboardController` が `$user->company_id` で部署を取得するため、
SuperAdmin がコンテキストをサンエー印刷に切り替えても常にサン・ブレーンのボードが表示される。

**方針:**
- `DashboardController` に `ResolvesContextCompany` トレイトを追加
- 部署取得を `contextCompanyId() ?? $user->company_id` に変更
- グローバルモード（null）時: 空配列を渡す → IrukaBoard は非表示

```php
// DashboardController
use ResolvesContextCompany;

$boardCompanyId = $this->contextCompanyId() ?? $user->company_id;
$departments = Department::where('company_id', $boardCompanyId)->orderBy('sort_order')->get(['id','name'])->toArray();
```

### 7-B: SuperAdmin ユーザー一覧の会社タブ

**問題:** SuperAdmin のユーザー一覧にコンテキスト切り替えしかなく、会社ごとの表示タブがない。

**方針:**
- コントローラーに `?filter_company=ID` クエリパラメータを追加（コンテキストとは独立）
- `companies` をコントローラーから渡す
- Index.vue にタブ（全て / 各会社）を追加
- タブ押下で `router.get(..., { filter_company: id })` を発行

### 7-C: 部署・担当の code 自動生成（バグ修正）

`CompanyController` の `store()` / `update()` で departments / assignments の code が
NOT NULL のため登録エラーが発生。`generateCode()` ヘルパーを追加し、未入力時は自動生成。
フォームに「コード（任意）」入力欄も追加（Create.vue / Edit.vue）。

---

## 将来の新会社・新部署追加手順（完成後チートシート）

### 新グループ会社を追加する場合
```
1. SuperAdmin から会社追加（company_type: 'general' または新 type）
2. 新 type が必要なら CompanyModules/{type}.js を作成 + index.js に1行
3. routes/web.php に company_type:{type} グループを追加（専用機能がある場合のみ）
```

### サン・ブレーンにオンデマンド部署を追加する場合
```
1. SuperAdmin から部署追加（module: 'ondemand'）
2. CompanyModules/sunbrain.js の extraRoles に ondemand ロール定義を追加
3. 専用ページ・ルートを実装
```

AppLayout.vue / HandleInertiaRequests.php は**変更不要**（extraRoles に追加するだけ）。

---

## 実装注意事項

1. **さくら本番 migration** — 3本まとめて実行。順序: company_type → department module → home_company_id
2. **SUNBRAIN の company_type** — migration 内で `sunbrain` に同時更新。忘れると全員ログイン不能
3. **SuperAdmin の company_id** — NULL に変更。home_company_id をサン・ブレーンに設定
4. **isPrepressDepartment フラグ** — `department.module === 'prepress'` に置き換え可能（互換性維持のため並行運用も可）
5. **Clerk ルート** — 情報出版の経理担当。`company_type:sunbrain` グループに含める

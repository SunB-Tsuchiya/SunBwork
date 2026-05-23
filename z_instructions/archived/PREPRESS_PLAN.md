# SunBWork 部署専用エリア 設計書（Prepress / Typesetting / Ondemand）
作成日: 2026-04-29

---

## 目的・背景

印刷・組版会社内には以下の3部署がある。各部署に独立したエリアを設け、部署固有の作業管理機能を集約する。

| 部署名（DB） | エリア名（ルート） | タブ表示名 | 状態 |
|------------|-----------------|-----------|------|
| 製版 | `prepress.*` | Prepress | **今回実装** |
| 情報出版 | `typesetting.*` | Typesetting | 将来実装 |
| オンデマンド | `ondemand.*` | Ondemand | 将来実装 |

**共通設計方針:**
- 各エリアは既存のロールエリア（Coordinator, User 等）と独立したルート空間を持つ
- テーマカラー: 全3エリア共通で **濃い緑**（`green-700` / `green-800`）
- ユーザーは自分の所属部署のタブのみ表示される（SuperAdmin/Admin は全表示）
- Dashboard は「これから機能を追加します」旨の準備中表示から始める

> **今回の実装範囲は Prepress（製版）のみ。** Typesetting・Ondemand は設計を記録しておき、将来別セッションで実装する。

---

## 部署タブの表示ルール（3エリア共通）

| ロール | 表示条件 |
|--------|----------|
| SuperAdmin | 全3タブ（Prepress / Typesetting / Ondemand）常に表示 |
| Admin | 全3タブ常に表示 |
| Leader | 自分の所属部署のタブのみ表示 |
| Coordinator | 自分の所属部署のタブのみ表示 |
| User | 自分の所属部署のタブのみ表示 |
| Clerk | 自分の所属部署のタブのみ表示（Coordinator 相当） |
| proof_coordinator | 対象外（3タブとも表示しない） |

**部署 → タブ の対応:**
```
department.name === '製版'      → Prepress タブを表示
department.name === '情報出版'  → Typesetting タブを表示
department.name === 'オンデマンド' → Ondemand タブを表示
```

**実装方針:**
- バックエンド: `HandleInertiaRequests.php` の shared data に部署フラグを追加
  ```php
  'isPrepressDepartment'    => ...,  // department.name === '製版'
  'isTypesettingDepartment' => ...,  // department.name === '情報出版'  【将来実装】
  'isOndemandDepartment'    => ...,  // department.name === 'オンデマンド' 【将来実装】
  ```
- フロントエンド: 各フラグで対応タブの表示を制御
- ルートアクセス制御: コントローラーで部署チェック（SuperAdmin/Admin は例外）

---

## フェーズ構成

### 今回実装（Prepress）

| フェーズ | 内容 | 状態 |
|---------|------|------|
| フェーズ1 | Prepress ベース実装（タブ追加 + Dashboard） | 🔲 未着手 |
| フェーズ2以降 | ユーザーが指定する製版固有機能 | ⏸ 保留（ユーザー指示待ち） |

### 将来実装（Typesetting / Ondemand）

| エリア | 内容 | 状態 |
|--------|------|------|
| Typesetting | タブ追加 + Dashboard + 情報出版固有機能 | ⏸ 別セッションで実施 |
| Ondemand | タブ追加 + Dashboard + オンデマンド固有機能 | ⏸ 別セッションで実施 |

---

## フェーズ1：ベース実装

### P-01 AppLayout への Prepress タブリンク追加

**概要:** 既存のロール別タブバー（Row 2）に「Prepress」ボタンを追加する。「User」ボタンの直後に配置。

**変更ファイル一覧:**

| ファイル | 変更内容 |
|---------|---------|
| `resources/js/layouts/AppLayout.vue` | Prepress リンク追加・roleNavClass に prepress エントリ追加・currentRouteContext に prepress 判定追加・tabs slot に PrepressNavigationTabs 追加 |
| `resources/js/Components/Tabs/PrepressNavigationTabs.vue` | 新規作成 |
| `app/Http/Middleware/HandleInertiaRequests.php` | `isPrepressDepartment` フラグ追加 |

**AppLayout.vue 変更詳細:**

1. `roleNavClass` マップに `prepress` カラーを追加:
```js
prepress: 'bg-green-700 text-white font-semibold',      // active
prepress: 'text-green-700 hover:text-green-900',          // inactive
```

2. `currentRouteContext` computed に `prepress.` プレフィックス判定を追加:
```js
if (r.startsWith('prepress.')) return 'prepress';
```

3. 各ロール用 `<template>` に Prepress リンクを追加（User リンクの直後）:
```vue
<!-- SuperAdmin / Admin: 常に表示 -->
<Link :href="route('prepress.dashboard')" :class="roleNavClass('prepress')">Prepress</Link>

<!-- Leader / Coordinator / User / Clerk: isPrepressDepartment フラグで制御 -->
<Link
    v-if="$page.props.auth.user.isPrepressDepartment"
    :href="route('prepress.dashboard')"
    :class="roleNavClass('prepress')"
>Prepress</Link>
```

4. tabs slot に PrepressNavigationTabs を追加:
```vue
<PrepressNavigationTabs v-else-if="currentRouteContext === 'prepress'" :active="getTopTabActive()" />
```

5. レスポンシブナビゲーションにも同様に追加

**PrepressNavigationTabs.vue 仕様:**
- カラー: `green-700` / `green-800`
- 初期タブ: ダッシュボード（`route('prepress.dashboard')`）
- 参考: `ClerkNavigationTabs.vue`

```vue
<script setup>
import { Link } from '@inertiajs/vue3';
const props = defineProps({ active: { type: String, default: '' } });
// Prepress カラー: green-700
const tab = (key) => [
    'rounded-md px-3 py-2 text-sm font-medium',
    props.active === key
        ? 'bg-green-100 text-green-800'
        : 'border border-green-700 text-green-700 hover:bg-green-50 hover:text-green-900',
];
</script>
```

---

### P-02 Prepress ダッシュボード

**概要:** Prepress エリアのトップページ。現時点では「これから機能を追加する」旨の表示のみ。

**新規作成ファイル:**

| ファイル | 内容 |
|---------|------|
| `resources/js/Pages/Prepress/Dashboard.vue` | ダッシュボードページ（Clerk/Dashboard.vue を参考） |
| `app/Http/Controllers/Prepress/PrepressDashboardController.php` | コントローラー |

**Dashboard.vue 仕様:**
- AppLayout を使用
- ヘッダー: `【製版】{ユーザー名}さんのページ`
- プロフィールカード（green-700 カラー）
- 「これから機能を追加します」旨の準備中メッセージ
- テーマカラー: `green-700` / `green-800`

**コントローラー仕様:**
```php
namespace App\Http\Controllers\Prepress;

class PrepressDashboardController extends Controller
{
    public function index()
    {
        // 権限チェック: SuperAdmin/Admin or 製版部署のみ
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            if (!$user->department || $user->department->name !== '製版') {
                abort(403);
            }
        }
        return inertia('Prepress/Dashboard', [
            'user' => $user->load('department'),
        ]);
    }
}
```

---

### P-03 ルート定義

**変更ファイル:** `routes/web.php`

**追加するルート:**
```php
// Prepress エリア（製版部署専用）
Route::prefix('prepress')
    ->name('prepress.')
    ->middleware(['auth:sanctum', 'verified'])
    ->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Prepress\PrepressDashboardController::class, 'index'])
            ->name('dashboard');
        // 以降、フェーズ2以降で追加
    });
```

---

### P-04 HandleInertiaRequests.php への isPrepressDepartment 追加

**変更ファイル:** `app/Http/Middleware/HandleInertiaRequests.php`

```php
// Prepress（製版）フラグ — department.name === '製版' で確定（2026-04-29 確認済み）
'isPrepressDepartment' => $request->user()
    ? ($request->user()->isSuperAdmin() || $request->user()->isAdmin()
        ? true
        : ($request->user()->department && $request->user()->department->name === '製版'))
    : false,

// Typesetting（情報出版）フラグ 【将来実装用・今回は追加しない】
// 'isTypesettingDepartment' => ...,  // department.name === '情報出版'

// Ondemand（オンデマンド）フラグ 【将来実装用・今回は追加しない】
// 'isOndemandDepartment' => ...,     // department.name === 'オンデマンド'
```

> パフォーマンス注意: `department` リレーションは eager load されていないため、
> `Department::find($user->department_id)?->name` で取得するか、`$user->load('department')` を使うこと。
> `users.department_id` カラムは存在確認済み。

> **department.name の値（確認済み）:**
> - 製版 → `'製版'`
> - 情報出版 → `'情報出版'`（将来実装時に DB で要確認）
> - オンデマンド → `'オンデマンド'`（将来実装時に DB で要確認）

---

## 命名規則（3エリア共通設計）

| 種別 | Prepress（製版）| Typesetting（情報出版）| Ondemand（オンデマンド） |
|------|--------------|---------------------|----------------------|
| 実装状態 | **今回実装** | 将来実装 | 将来実装 |
| ルートプレフィックス | `prepress.` | `typesetting.` | `ondemand.` |
| URLプレフィックス | `/prepress/` | `/typesetting/` | `/ondemand/` |
| コントローラー名前空間 | `App\Http\Controllers\Prepress\` | `App\Http\Controllers\Typesetting\` | `App\Http\Controllers\Ondemand\` |
| Vueページディレクトリ | `resources/js/Pages/Prepress/` | `resources/js/Pages/Typesetting/` | `resources/js/Pages/Ondemand/` |
| タブコンポーネント | `PrepressNavigationTabs.vue` | `TypesettingNavigationTabs.vue` | `OndemandNavigationTabs.vue` |
| テーマカラー | `green-700` / `green-800` | `green-700` / `green-800` | `green-700` / `green-800` |
| タブ文字列 | `Prepress` | `Typesetting` | `Ondemand` |
| 部署名フラグ | `isPrepressDepartment` | `isTypesettingDepartment` | `isOndemandDepartment` |
| department.name | `'製版'`（確認済み） | `'情報出版'`（要確認） | `'オンデマンド'`（要確認） |

---

## フェーズ2：伝票管理機能（2026-04-29 設計確定）

製版部署がホワイトボード運用（予定／作業中／完了）をWeb上で行えるようにする機能。

### データモデル: `prepress_tickets` テーブル

| カラム | 型 | 内容 |
|--------|-----|------|
| `id` | bigint PK | |
| `user_id` | FK → users | 作成者 |
| `jobcode` | varchar(100) nullable | 伝票番号 |
| `title` | varchar(255) NOT NULL | タイトル（カード1行目） |
| `project_name` | varchar(255) nullable | 案件名（フリーテキスト） |
| `client_name` | varchar(255) nullable | クライアント名（フリーテキスト） |
| `memo` | text nullable | メモ（カード2行目以降） |
| `status` | varchar(20) default 'pending' | `pending` / `in_progress` / `completed` |
| `image_path` | varchar(500) nullable | 変換後 JPG のストレージパス |
| `original_filename` | varchar(255) nullable | 元ファイル名 |
| `created_at` / `updated_at` | timestamp | |

**ステータスラベル:**
- `pending` → 予定
- `in_progress` → 作業中
- `completed` → 完了

---

### P2-01: 伝票ボード（kanban）

**タブ名:** 伝票ボード  
**ルート:** `prepress.board` → `/prepress/board`

**仕様:**
- 横3列（予定／作業中／完了）、各列は縦にスクロール可能
- 全体は画面幅いっぱいに広がる（`-mx-4 sm:-mx-6 lg:-mx-8` で AppLayout の max-w-7xl を突き破る）
- カードはドラッグ＆ドロップで列間を移動 → ステータスが即座に PATCH API で更新される
- D&D 実装: HTML5 ネイティブ drag & drop（外部ライブラリ不要）
- カード表示: 1行目タイトル（font-semibold）、2行目以降メモ（text-sm text-gray-500）、右上に伝票番号

**変更ファイル:**
| ファイル | 種別 |
|---------|------|
| `app/Http/Controllers/Prepress/BoardController.php` | 新規 |
| `resources/js/Pages/Prepress/Board.vue` | 新規 |

---

### P2-02: 伝票一覧

**タブ名:** 伝票一覧  
**ルート:** `prepress.tickets.index` → `/prepress/tickets`

**仕様:**
- 一覧カラム: 登録日 / 伝票番号 / タイトル / 案件名 / クライアント名 / ステータス
- フィルター: クライアント名 / 案件名 / 登録日（from-to）/ キーワード検索
- 「完了を非表示」トグル（デフォルト: ON）
- ステータスはボードと常に同期（同一テーブルを参照）
- 右上に「伝票登録」ボタン → 伝票登録画面へ

**変更ファイル:**
| ファイル | 種別 |
|---------|------|
| `app/Http/Controllers/Prepress/TicketController.php` | 新規 |
| `resources/js/Pages/Prepress/Tickets/Index.vue` | 新規 |

---

### P2-03: 伝票登録

**タブ遷移:** 伝票一覧 → 「伝票登録」ボタン → 登録画面  
**ルート:** `prepress.tickets.create` / `prepress.tickets.store`

**フォーム項目（coordinator/project_jobs/create を参考に要素を流用）:**
| 項目 | 備考 |
|------|------|
| クライアント名 | テキスト入力（フリーテキスト） |
| 伝票番号 | テキスト入力 |
| 案件タイトル（タイトル） | テキスト入力・必須 |
| 案件名 | テキスト入力 |
| ステータス | select: 予定 / 作業中 / 完了 |
| メモ | textarea |
| 伝票画像 | ファイルドロップ +「フォルダから選ぶ」ボタン |

**削除項目（coordinator/project_jobs/create から除外）:** リーダー / サブリーダー / チームメンバー

**画像アップロード仕様:**
- ドロップゾーン + 「フォルダから選ぶ」ボタン
- スマホ時のみ「カメラ画像を取り込む」ボタンを追加表示（`<input type="file" accept="image/*" capture="environment">`）
- ファイル選択後: サムネイル表示 + 「拡大」ボタン（クリックで拡大モーダル）
- 対応形式: JPG / PNG / WEBP / HEIC / GIF / PDF（いずれもサーバー側で JPG に変換）
- 変換仕様: max 1600px 幅、JPEG quality 85、保存先 `storage/app/public/prepress/jobticker/`
- 画像変換: `PrepressImageService` を使用（Intervention Image v3 で実装済み）
- 保存後: 伝票一覧にリダイレクト

**変更ファイル:**
| ファイル | 種別 |
|---------|------|
| `app/Http/Controllers/Prepress/TicketController.php` | 新規（index/create/store/updateStatus/destroy） |
| `resources/js/Pages/Prepress/Tickets/Create.vue` | 新規 |

---

### P2-04: ベースインフラ（タブ / ルート / ナビ）

**変更ファイル:**
| ファイル | 種別 | 内容 |
|---------|------|------|
| `database/migrations/2026_04_29_000002_create_prepress_tickets_table.php` | 新規 | テーブル定義 |
| `app/Models/PrepressTicket.php` | 新規 | モデル |
| `app/Services/PrepressImageService.php` | 新規 | 画像変換サービス |
| `app/Http/Controllers/Prepress/PrepressDashboardController.php` | 新規 | Dashboard |
| `app/Http/Middleware/HandleInertiaRequests.php` | 変更 | `isPrepressDepartment` フラグ追加 |
| `routes/web.php` | 変更 | prepress ルート群追加 |
| `resources/js/layouts/AppLayout.vue` | 変更 | Prepress タブリンク・roleNavClass・currentRouteContext 追加 |
| `resources/js/Components/Tabs/PrepressNavigationTabs.vue` | 新規 | ナビタブコンポーネント |
| `resources/js/Pages/Prepress/Dashboard.vue` | 新規 | ダッシュボード |

**ルート一覧:**
```
GET  /prepress/dashboard          prepress.dashboard
GET  /prepress/board              prepress.board
GET  /prepress/tickets            prepress.tickets.index
GET  /prepress/tickets/create     prepress.tickets.create
POST /prepress/tickets            prepress.tickets.store
PATCH /prepress/tickets/{ticket}/status  prepress.tickets.updateStatus
DELETE /prepress/tickets/{ticket} prepress.tickets.destroy
```

---

## 実装作業順序（フェーズ2）

```
STEP 1: 既作成ファイルの確認・調整
  → migration / Model / PrepressImageService / DashboardController / TicketController
  → 問題なければそのまま使用

STEP 2: BoardController 作成

STEP 3: routes/web.php に全ルート追加

STEP 4: php artisan migrate 実行

STEP 5: PrepressNavigationTabs.vue 作成（伝票ボード / 伝票一覧 タブ）

STEP 6: AppLayout.vue に Prepress タブリンク追加

STEP 7: Dashboard.vue 作成

STEP 8: Board.vue 作成（D&D kanban）

STEP 9: Tickets/Index.vue 作成（伝票一覧）

STEP 10: Tickets/Create.vue 作成（伝票登録・画像アップロード）

STEP 11: npm run build

STEP 12: 動作確認依頼
```

---

## 将来実装メモ（Typesetting / Ondemand）

Prepress 実装完了後、同じパターンで以下を繰り返すだけでよい。

**Typesetting（情報出版）実装時のチェックリスト:**
- [ ] DB で `departments.name === '情報出版'` を確認
- [ ] `HandleInertiaRequests.php` に `isTypesettingDepartment` フラグ追加
- [ ] `routes/web.php` に `typesetting.dashboard` ルート追加
- [ ] `PrepressDashboardController.php` を参考に `TypesettingDashboardController.php` 作成
- [ ] `Prepress/Dashboard.vue` を参考に `Typesetting/Dashboard.vue` 作成（`【情報出版】`ヘッダー）
- [ ] `PrepressNavigationTabs.vue` を参考に `TypesettingNavigationTabs.vue` 作成
- [ ] `AppLayout.vue` に Typesetting タブリンク追加（`isTypesettingDepartment` 条件）
- [ ] `currentRouteContext` に `typesetting.` 判定追加

**Ondemand（オンデマンド）実装時も同様のチェックリストを使用する。**

---

## 注意事項・落とし穴

1. **`route()` を必ず使う** — `window.location.href = '/prepress/...'` はさくら本番で 404 になる
2. **CSRF は meta タグから取得** — さくら本番では XSRF-TOKEN クッキーが発行されない
3. **部署フラグの N+1 問題** — department リレーションのロードに注意（`Department::find($user->department_id)` 推奨）
4. **department.name の値** — Prepress は '製版' で確認済み。Typesetting/Ondemand は将来実装時に要確認
5. **レスポンシブナビ忘れ** — AppLayout.vue のモバイル用ハンバーガーメニュー内にも各タブを追加すること
6. **build 忘れ** — Vue/JS 変更後は必ず `npm run build` を実行
7. **Typesetting/Ondemand 実装時** — Prepress と同じパターンを繰り返す（コピーして名前変更）

---

## 作業ログ

| 日付 | フェーズ | 項目 | 状態 |
|------|---------|------|------|
| 2026-04-29 | — | 設計書（PREPRESS_PLAN.md）・管理書（PREPRESS_MANAGER.md）・プロンプト（PREPRESS_PROMPT.md）作成 | 完了 |

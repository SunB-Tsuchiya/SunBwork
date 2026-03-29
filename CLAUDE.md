# CLAUDE.md - SunBWork プロジェクト ルール

このファイルはすべての会話の開始時に必ず参照すること。コードの作成・修正はここに記載されたルールに従う。

---

## Claude へのワークフロー指示

- Vue / JS ファイルを変更したら、必ず最後に `npm run build` を実行すること（許可済み）。
- `npm run build` はプロジェクトルート（`/home/tchirosb/SunBWork`）で実行する。
- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`

### ユーザーから「gitにアップ」「さくらにデプロイ」を求められたときの手順

① `git status --short | grep -v "public/build"` で未コミット確認（Controller/Model/Migration/routes 漏れに注意）

② routes/web.php 変更があれば Ziggy 再生成:

```bash
docker compose exec laravel bash -lc "php artisan ziggy:generate resources/js/ziggy.js"
```

③ さくら用ビルド:

```bash
sed -i 's/^VITE_APP_BASE_PATH=$/VITE_APP_BASE_PATH=\/members/' /home/tchirosb/SunBWork/.env
docker compose exec laravel bash -lc "npm run build"
```

④ コミット:

```bash
git add <変更ファイル> public/build/ resources/js/ziggy.js
git commit -m "feat/fix/build: ..."
```

⑤ .env をローカル用に戻してローカルビルドも実行（コミット不要）:

```bash
sed -i 's/^VITE_APP_BASE_PATH=\/members$/VITE_APP_BASE_PATH=/' /home/tchirosb/SunBWork/.env
docker compose exec laravel bash -lc "npm run build"
```

⑥ ユーザーへ伝える:

```
【あなたの操作が必要です】
1. git push origin main
2. さくら SSH: cd ~/SunBWork && git pull && php artisan migrate && php artisan config:clear
   ※ マイグレーションがない場合は migrate は省略可
```

---

## プロジェクト概要

**SunBWork** は Laravel + Inertia.js (Vue 3) + Vite + Tailwind CSS で構築された SPA 型の業務管理システム。

- ロール: SuperAdmin / Admin / Coordinator / Leader / User
- 機能: ProjectJob 管理・割当（JobBox/MyJobBox）、カレンダー、チャット、日報、ワークロード解析
- DB: MySQL / Sanctum + Cookie SPA 認証 / Laravel Echo (WebSocket)

**主要フォルダ:**

```
app/Http/Controllers/
  Admin/  Coordinator/  Leader/  ProjectJobs/  User/  Bot/  Chat/  Diaries/
app/Models/
database/migrations/          # backups/ は読み飛ばす
resources/js/
  Pages/                      # Inertia ページ（ロール別サブディレクトリ）
  Components/                 # 大文字始まり = プロジェクト固有
  components/ui/              # 小文字始まり = shadcn/ui 系
  layouts/AppLayout.vue       # メインレイアウト（全ページ共通）
  ziggy.js                    # Ziggy ルート定義
routes/web.php                # SPA ルート（api.php には置かない）
z_instructions/               # 詳細ドキュメント（backups/ は読み飛ばす）
```

---

## UI / レイアウト ルール（最優先）

AppLayout は `py-12 > max-w-7xl` を内部に提供済み。ページ側はデフォルトスロットに直接カードを置く:

```vue
<AppLayout title="ページタイトル">
  <template #header><h2>見出し</h2></template>
  <div class="rounded bg-white p-6 shadow">
    <!-- コンテンツ -->
  </div>
</AppLayout>
```

**NG:** `<main>` タグ、`py-2/py-12` の重複ラップ、`mx-auto max-w-7xl` の重複ラップ、`shadow-xl sm:rounded-lg`

**AppLayout スロット:** `#header` / `#headerExtras` / `#tabs` / デフォルト

**AppLayout が provide する値:** `authUser`（ログインユーザー）/ `user`（ページの user prop）

**ToastUnified は AppLayout 内にグローバル配置済み。各ページで重複させない。**

**ロール別カラー:** SuperAdmin=黄 / Admin=赤 / Leader=オレンジ / Coordinator=緑 / User=青

**テーブル:** `min-w-full divide-y divide-gray-200`、ヘッダ `bg-gray-50`、行 `hover:bg-gray-50`

**Ziggy の route() 使用時はパラメータ名をオブジェクトで渡す:**

```js
route('coordinator.project_jobs.show', { projectJob: job.id });
```

---

## ロール共有ページのルーティングルール

AppLayout.vue の `currentRouteContext` computed はルート名プレフィックスでタブを決定:

- `superadmin.*` → superadmin / `admin.*` → admin
- `leader.*` or `workload_setting.*` → leader
- `coordinator.*` or `project_jobs.*` → coordinator
- それ以外 → 必ず `'user'` を返す（user_role にフォールバックしてはいけない）

複数ロール共有ページでは `routePrefix` computed（Vue）と `routePrefix()` メソッド（PHP）でロール別にルートを解決する。

**Clients テーブルの注意:** DBカラムは `notes`。フォームフィールド名 `detail` との乖離に注意。

---

## project_job_assignments 統合テーブル設計（重要）

### 設計概要

`project_job_assignments` が **JobBox・MyJobBox 両方の唯一のテーブル**。
`project_job_assignment_by_myself` テーブルは migration 500001 で統合・削除済み。

**区別ルール:**

- `sender_id = user_id` → **自己割当**（旧 project_job_assignment_by_myself）
- `sender_id ≠ user_id` or NULL → **Coordinator 割当**

**スコープ（ProjectJobAssignment モデル）:**

```php
scopeSelfAssigned()        // whereColumn('sender_id', 'user_id')
scopeCoordinatorAssigned() // sender_id != user_id OR sender_id IS NULL
```

**統合時に追加されたカラム:** `completed` / `scheduled` / `scheduled_at` / `read_at` / `start_time`

**`desired_start_date` は存在しない（意図的に除外）。**
**`WorkloadAnalyzerController` など `project_job_assignments` に対して期間フィルターをかける場合は `desired_end_date` を使うこと（`desired_start_date` を参照すると Column not found エラー）。**

### ProjectJobAssignmentByMyself モデル

`ProjectJobAssignment` を継承したエイリアス（後方互換のため残存）。
`$table = 'project_job_assignments'` / `booted()` で `whereColumn('sender_id', 'user_id')` のグローバルスコープを自動適用。
**新コードでは `ProjectJobAssignment::selfAssigned()` スコープを使うこと。**

### JobBox vs MyJobBox の違い

| 機能     | データソース                                                   | 完了ルート                      |
| -------- | -------------------------------------------------------------- | ------------------------------- |
| JobBox   | `job_assignment_messages` を `project_job_assignments` に JOIN | `jobbox.assignments.complete`   |
| MyJobBox | `project_job_assignments` 直接（`selfAssigned()` スコープ）    | `myjobbox.assignments.complete` |

`events.complete` ルートは `project_job_assignments.completed` のみ更新。

### MyJobBox インデックス仕様

- **Inertia props のローカルコピー:** `const toPlain = (arr) => arr.map(item => ({...item}))` で各要素を shallow copy（要素レベルで `{...item}` が必要。`[...arr]` では各要素が proxy のまま）
- **完了後のローカル更新:** `localAssignments.value.splice(idx, 1, {...obj, completed: true})` （`push/代入` では Vue リアクティビティがトリガーされない）
- **期間フィルター:** `COALESCE(desired_end_date, created_at)` でデフォルト当月
- **行クリック:** `events` テーブルは `project_job_assignment_by_myself_id` カラムを持たない。直接 `user.myjobbox.show` へ遷移する
- **ステータス優先順:** `completed` boolean フラグ → `status_model.key` → `scheduled` フラグ

### ステータス値とバッジカラー

| ステータス        | バッジ                          |
| ----------------- | ------------------------------- |
| 完了              | `bg-yellow-100 text-yellow-800` |
| セット済み        | `bg-blue-100 text-blue-800`     |
| 確認済み          | `bg-green-100 text-green-800`   |
| 進行中 / 受信済み | `bg-indigo-100 text-indigo-800` |
| その他            | `bg-gray-100 text-gray-700`     |

---

## セキュリティ・認証ルール

- SPA 向けエンドポイントは必ず `web` ミドルウェア（`routes/web.php`）に置く。`routes/api.php` には置かない
- HTML/Markdown は `HTMLPurifier`（サーバ）/ `DOMPurify`（フロント）でサニタイズ。`App\Services\HtmlSanitizer` を経由すること
- ファイルメタは最小情報のみ返す（`original_name`, `mime`, `size`, `path`, `url`）

---

## 添付ファイルルール

**保存先:** `storage/app/public/attachments/` 。命名: `<uuid>_<original_name>`（`..`, `/`, `\` は `_` に置換）

**配信:** ストリーミングエンドポイント経由（`/chat/attachments` / `/bot/attachments` / `/attachments/signed`）

**重要ファイル:** `AttachmentService.php`（保存・サムネイル）/ `AttachmentController.php`（配信）/ `Helpers/attachment.js`（フロント URL 正規化）

---

## カレンダー・イベント ルール

- FullCalendar には Vue の reactive Proxy をそのまま渡さない → `structuredClone` などで plain オブジェクトを渡す
- 日付: サーバは UTC、フロントは JST で変換に注意
- `EventController::complete()`: `project_job_assignments.completed` のみ更新
- Events/Show.vue パンくずバー: `events.project_job_assignment_id` は `project_job_assignments` への FK

---

## CSV アップロード実装パターン

ルート定義はリソースルートより前に: `csv/upload` / `csv/preview` / `csv/store` / `csv/sample`

Vue ページ: `CsvUpload.vue`（ファイル選択）→ `CsvPreview.vue`（確認後 store へ POST）

サンプル CSV は BOM 付き UTF-8 で返す（`"\xEF\xBB\xBF"` プレフィックス）。

---

## AI / チャット ルール

- OpenAI キーは環境変数 `OPENAI_API_KEY` で管理
- AI 生成コンテンツは必ず DOMPurify / HTMLPurifier でサニタイズ
- `BotController.php`: `totalCharsIncluded` の初期化漏れに注意

---

## ワークロード解析

**関連ファイル:** `WorkloadAnalyzerController.php`（全ロジック）/ `AnalysisPanel.vue`（カテゴリ別パネル）/ `WorkloadAnalyzer/` 配下の Index / Show / CategoryRank / Settings

**ルーティング:** 静的ルートをパラメータ化ルートより前に配置。`category-rank` と `index` は同じ `index()` メソッドを使用し、ルート名で Inertia コンポーネントを切り替える。

### ポイント計算の仕組み

**生スコア:**

```
ステージ/サイズ/種別 = Σ (ページ × 係数 × 難易度係数)
難易度 = Σ (ページ × 難易度係数)
イベント = Σ (時間[h] × イベント種別係数)
残業(通常, ≤180分/日) = 合計分 × 通常残業係数
残業(超過, >180分/日) = 合計分 × 超過残業係数
```

**職種グループ別パーセンタイル変換（0〜100）:**

- 同部署内で `assignment_name` ごとにグループ化
- グループ N ≥ 3 → 同職種内でパーセンタイル計算（`comparison_level = 'role'`）
- グループ N < 3 → 部署全体でフォールバック（`comparison_level = 'department'`）
- パーセンタイル式: `(N - (above + (tied+1)/2)) / (N-1) × 100`（N=1 は 100 固定）

**総合ポイント（0〜600）:** 6カテゴリのパーセンタイル合算

**偏差値（参考値）:** 同会社全体を母集団として `50 + 10z` で計算

**計算変更時の注意:** 生スコア計算式は `$calcAggregates`（index用）と `$computeUserCategoryScores`（show用）の**両方**を変更。残業閾値（180分）は3箇所に存在。

### 作業種別・サイズのグループ分類

`work_item_types` と `sizes` に `group` カラムあり。コントローラーで取得する際は **`group` カラムを必ず含めること**（`->get(['id','name'])` のように列指定する場合も同様）。

`AssignmentForm.vue`: `_type_filter`（作業種別）と `_medium_filter`（サイズ: paper/digital）でグループフィルタリング。

---

## 開発ワークフロー

- 設定変更後: `php artisan config:clear && php artisan cache:clear`
- storageパーミッションエラー時: `docker compose exec -u root laravel bash -lc "chmod -R 777 storage bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache"`

**ローカル .env 重要設定:**

```env
APP_URL=http://localhost:8000
VITE_APP_BASE_PATH=          # 空にする（さくらの /members は不要）
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
```

---

## CI（GitHub Actions）ルール

- `.github/workflows/lint.yml`: PHP Pint + Prettier + ESLint
- `.github/workflows/tests.yml`: Pest テスト（SQLite使用）

**MySQL固有マイグレーションには SQLite ガード必須:**

```php
if (DB::getDriverName() === 'sqlite') return;
```

`MODIFY`・`DROP FOREIGN KEY`・`AUTO_INCREMENT`・`information_schema` 参照を含むマイグレーションに追加。

**スキップ中のテスト:** `routes/settings.php` / `routes/auth.php` 未登録、Fortify emailVerification 無効、`project_schedule_assignments` / `password_reset_tokens` テーブル未実行、DiaryFactory 未作成。

---

## さくらレンタルサーバー デプロイ設定

**サーバー構成:**

- `~/SunBWork/` — Laravel ルート
- `~/www/members/` — 公開ディレクトリ（`index.php` のパスは通常と異なる）
- `~/www/members/build/` — `~/SunBWork/public/build/` へのシンボリックリンク

**本番 .env:**

```
APP_URL=https://silverlamb759.sakura.ne.jp/members
ASSET_URL=https://silverlamb759.sakura.ne.jp/members
VITE_APP_BASE_PATH=/members
APP_DEBUG=false
```

**制約:** `nano` 不可（`vi` を使う）/ `.htaccess` に `php_flag`/`php_value` 不可 / Reverb 不可 / `sed -i` は BSD版のため `-i ''` が必要

**デプロイ後:**

```bash
cd ~/SunBWork && git pull && php artisan migrate && php artisan config:clear && php artisan cache:clear
```

**`sessions` テーブルなどで migrate エラー時:** tinker で migrations テーブルに手動 insert。

**Vite ビルド:** `VITE_APP_BASE_PATH` が空なら `/build/assets/...`、`/members` なら `/members/build/assets/...`。`loadEnv` で読むこと（`process.env` では .env を読まない）。

---

## Migration / Seeder ルール

- 全本番 Seeder は冪等性必須: `firstOrCreate` / `updateOrCreate` / `upsert`（`insert` は使わない）
- `event_item_types` と `worktime_item_types` は Migration 内で初期データ INSERT
- **「その他」レコード:** `clients`（name='その他'）/ `project_jobs`（title='その他'）/ `work_item_types`（slug='other'）/ `event_item_types`（slug='other'）/ `assignments`（code='other'）は `OtherClientProjectSeeder` または Migration で事前登録済み
- 開発サンプルデータ: `DatabaseSeeder.php` の `$sampleData = true` で有効化

**本番必須 Seeder（主要）:**
`CompanySeeder` / `DepartmentSeeder` / `AssignmentSeeder` / `TeamSeeder` / `WorkItemTypesSeeder` / `SizesSeeder` / `StagesSeeder` / `DifficultiesSeeder` / `StatusesSeeder` / `OtherClientProjectSeeder` 他

---

## 詳細ドキュメント参照先

- `z_instructions/CONSOLIDATED_01_layout_and_ui.md` - UI ルール詳細
- `z_instructions/CONSOLIDATED_02_security_and_sessions.md` - セキュリティ・セッション
- `z_instructions/CONSOLIDATED_03_auth_and_cors.md` - 認証・CORS
- `z_instructions/CONSOLIDATED_04_ai_and_chat.md` - AI・チャット
- `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` - カレンダー・JobBox
- `z_instructions/CONSOLIDATED_06_messages_and_files.md` - メッセージ・ファイル
- `z_instructions/CONSOLIDATED_07_workload_and_handover.md` - ワークロード解析
- `z_instructions/CONSOLIDATED_08_attachment.md` - 添付ファイル詳細

> `z_instructions/backups/` 配下のファイルは読み飛ばす。

---

## 日報（Diary）実装ルール

- `DiaryController::store()` で `created_at` / `updated_at` を明示的にセットしない（Laravel に任せる）
- 既存日報チェックは `whereDate('created_at', $date)` ではなく `where('date', $date)` を使う
- Quill エディタへの外部コンテンツセット: `form.content` への代入と `editorInstance.setContents(delta)` の両方が必要
- 日報 Show ページ: 2カラムグリッド（本文＋コメント / TimelineDiary）+ 勤務情報バー

---

## TimelineDiary コンポーネント ルール

**ファイル:** `resources/js/Components/TimelineDiary.vue`

- `ResizeObserver` でラッパー幅をリアクティブ取得し `usedPxPerMin = containerWidth / windowMinutes` で自動フィット。`minWidth` をピクセルで強制しない（水平スクロールが発生する）
- `startHour` / `endHour` は `workRecord.start_time` / `end_time` から動的に決定（親から遅れて渡されるため `watch` で追随）
- 夜勤モード: `defaultWorktype.start_time >= 16:00` の場合 `slotMaxTime: '30:00:00'`（翌6時）

---

## Coordinator 割当フロー ルール

**案件選択中間ページ:** `coordinator.project_jobs.assignment_select` → `SelectProject.vue` → assignments.create へ遷移

**ルート定義順序:** パラメータ化ルート（`{projectJob}`）より前に静的ルートを置く。

**AssignmentForm.vue:** `mode='coordinator'`（デフォルト）と `mode='user'`（自己登録）で動作切り替え。

- Coordinator 保存は必ず `router.post()`（`inertiaFetch` では 419 CSRF エラー）
- `desired_end_date` は締め切り日。`desired_start_date` は `project_job_assignments` に存在しない（削除済み）

**ProjectJobs Show ページ:** タイトル行にボタン並置 / スケジュール・メンバーをインライン表示（モーダル廃止）

**案件カレンダー:** `coordinator.project_jobs.calendar` → `CalendarAll.vue` / 12色パレット自動割り当て / 「完了済み非表示」チェック付き

---

## 権限・ロール設計ルール

**ロール階層:** SuperAdmin > Admin > Leader / Coordinator > User

**Admin 権限フラグ（`admin_permissions`）:** `company_management` / `user_management` / `team_management` / `diary_management` / `client_management` / `workload_analysis` / `worktype_setting` / `work_record_management`

**Leader 権限フラグ（`leader_permissions`）:** `client_management` / `diary_management` / `workload_analysis` / `workload_setting` / `work_record_management` / `dispatch_management`

**権限チェック Trait:** `ChecksAdminPermission` / `ChecksLeaderPermission`。レコードなしは全権限 ON。

**Leader スコープ:** 部署リーダー（`team_type='department'`）→ 自部署全体 / ユニットリーダー → 自チームのみ / サブリーダー（`team_sub_leaders` ピボット）→ 担当チームのみ

**タブ表示制御:** `HandleInertiaRequests` で `auth.adminPermissions` / `auth.leaderPermissions` を共有。`perm === null`（レコードなし）は全フラグ ON として扱う。

**チーム管理・権限管理ページはフラグ制御対象外。**

**SuperAdmin が Admin 権限設定ページにアクセスする場合は `'admin'` プレフィックスを返す（`'superadmin'` ではない）。**

---

## ユーザー設定・カレンダー勤務日程ルール

**テーブル:**

- `user_settings` — デフォルト勤務形態（`worktype_id`）・カレンダー表示設定
- `user_monthly_schedules` — 日ごと上書き（`year_month` + `schedule` JSON: `{"01": 2, "15": 3}`）

**解決優先順:** `user_monthly_schedules` > `user_settings.worktype_id` > 一覧の先頭

`worktype_id` 変更時は `user_monthly_schedules` を全削除（日次設定リセット）。

**カレンダー連動:** 勤務形態名をヘッダーに表示 / 始業前グレー背景 / 夜勤モード / 初期スクロールは1時間前 / `scrollTimeReset: false`

---

## 雇用形態管理（派遣・業務委託）ルール

**テーブル:** `users.employment_type`（regular/contract/dispatch/outsource）/ `user_employment_settings`（日報義務フラグ上書き）/ `dispatch_profiles`（派遣会社情報）

**日報義務判定:** `user_employment_settings` レコードあり → そのフラグ / なし → regular/contract は必須、dispatch/outsource は任意

**User モデルメソッド:** `isDiaryRequired()` / `employmentTypeLabel()` / `employmentSetting()` / `dispatchProfile()`

**AssignmentForm:** dispatch/outsource ユーザー選択時にオレンジ警告ボックスを表示。コントローラーの members 配列に `employment_type` / `employment_type_label` を含めること。

**CSV 登録:** 7列目が `employment_type`（空欄時は `regular`）。日本語表記→英語キーに自動変換。

**dispatch_profiles:** dispatch/outsource のみ保存。regular/contract に変更時はレコード削除。

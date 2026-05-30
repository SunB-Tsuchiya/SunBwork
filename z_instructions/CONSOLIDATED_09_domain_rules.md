# CONSOLIDATED_09 - ドメインルール詳細リファレンス

CLAUDE.md から移した詳細ルール。機能追加・修正時に必ず参照すること。

---

## 権限・ロール設計ルール

**ロール階層:** SuperAdmin > Admin > Leader / Coordinator > **Clerk** > User

**Clerk ロール（2026-04-02 追加）:**
- `user_role = 'clerk'` / バッジカラー: 紫（`bg-purple-100 text-purple-800`）/ 表示名: 「事務・経理」
- Coordinator と同等以上の権限
- `AdminUserController` バリデーション: `in:admin,leader,coordinator,clerk,user`
- `UserTable.vue` / `Admin/Users/Index.vue` の `getAssignmentText()` / `getAssignmentBadgeClass()` 両方に定義が必要

**Admin 権限フラグ（`admin_permissions`）:** `company_management` / `user_management` / `team_management` / `diary_management` / `client_management` / `workload_analysis` / `worktype_setting` / `work_record_management`

**Leader 権限フラグ（`leader_permissions`）:** `client_management` / `diary_management` / `workload_analysis` / `workload_setting` / `work_record_management` / `dispatch_management` / `project_job_overview`

**権限チェック Trait:** `ChecksAdminPermission` / `ChecksLeaderPermission`。レコードなしは全権限 ON。

**Leader スコープ:** 部署リーダー（`team_type='department'`）→ 自部署全体 / ユニットリーダー → 自チームのみ / サブリーダー（`team_sub_leaders` ピボット）→ 担当チームのみ

**タブ表示制御:** `HandleInertiaRequests` で `auth.adminPermissions` / `auth.leaderPermissions` を共有。`perm === null` は全フラグ ON。チーム管理・権限管理ページはフラグ制御対象外。

**SuperAdmin が Admin 権限設定ページにアクセスする場合は `'admin'` プレフィックスを返す（`'superadmin'` ではない）。**

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

`project_job_assignments` が **JobBox・MyJobBox 両方の唯一のテーブル**。`project_job_assignment_by_myself` テーブルは migration 500001 で統合・削除済み。

**区別ルール:**
- `sender_id = user_id` → **自己割当**（旧 project_job_assignment_by_myself）
- `sender_id ≠ user_id` or NULL → **Coordinator 割当**

**スコープ（ProjectJobAssignment モデル）:**
```php
scopeSelfAssigned()        // whereColumn('sender_id', 'user_id')
scopeCoordinatorAssigned() // sender_id != user_id OR sender_id IS NULL
```

**統合時に追加されたカラム:** `completed` / `scheduled` / `scheduled_at` / `read_at` / `start_time`

**`desired_start_date` は存在しない（意図的に除外）。** 期間フィルターは `desired_end_date` を使うこと。

**`ProjectJobAssignmentByMyself` モデル:** `ProjectJobAssignment` を継承したエイリアス（後方互換）。新コードでは `ProjectJobAssignment::selfAssigned()` スコープを使うこと。

**JobBox vs MyJobBox:**
| 機能 | データソース | 完了ルート |
|------|-------------|-----------|
| JobBox | `job_assignment_messages` JOIN | `jobbox.assignments.complete` |
| MyJobBox | 直接（`selfAssigned()` スコープ） | `myjobbox.assignments.complete` |

**MyJobBox インデックス仕様:**
- Inertia props ローカルコピー: `const toPlain = (arr) => arr.map(item => ({...item}))`
- 完了後の更新: `localAssignments.value.splice(idx, 1, {...obj, completed: true})`
- 期間フィルター: `COALESCE(desired_end_date, created_at)` でデフォルト当月

**ステータス値とバッジカラー:**
| ステータス | バッジ |
|-----------|-------|
| 完了 | `bg-yellow-100 text-yellow-800` |
| セット済み | `bg-blue-100 text-blue-800` |
| 確認済み | `bg-green-100 text-green-800` |
| 進行中/受信済み | `bg-indigo-100 text-indigo-800` |
| その他 | `bg-gray-100 text-gray-700` |

---

## セキュリティ・認証ルール

- SPA 向けエンドポイントは必ず `web` ミドルウェア（`routes/web.php`）に置く
- HTML/Markdown は `HTMLPurifier`（サーバ）/ `DOMPurify`（フロント）でサニタイズ。`App\Services\HtmlSanitizer` を経由すること
- ファイルメタは最小情報のみ返す（`original_name`, `mime`, `size`, `path`, `url`）

---

## 添付ファイルルール

**保存先:** `storage/app/public/attachments/`。命名: `<uuid>_<original_name>`（`..`, `/`, `\` は `_` に置換）

**配信:** ストリーミングエンドポイント経由（`/chat/attachments` / `/bot/attachments` / `/attachments/signed`）

**重要ファイル:** `AttachmentService.php`（保存・サムネイル）/ `AttachmentController.php`（配信）/ `Helpers/attachment.js`（フロント URL 正規化）

---

## カレンダー・イベントルール

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

**関連ファイル:** `WorkloadAnalyzerController.php` / `AnalysisPanel.vue` / `WorkloadAnalyzer/` 配下の Index / Show / CategoryRank / Settings

**ルーティング:** 静的ルートをパラメータ化ルートより前に配置。

**生スコア計算式:**
```
ステージ/サイズ/種別 = Σ (ページ × 係数 × 難易度係数)
残業(通常, ≤180分/日) = 合計分 × 通常残業係数
残業(超過, >180分/日) = 合計分 × 超過残業係数
```

**パーセンタイル変換（0〜100）:** 同部署内 `assignment_name` グループ。N≥3 → 同職種内、N<3 → 部署全体フォールバック。

**計算変更時の注意:** `$calcAggregates`（index用）と `$computeUserCategoryScores`（show用）の**両方**を変更。残業閾値（180分）は3箇所に存在。

`work_item_types` と `sizes` に `group` カラムあり。コントローラーで取得する際は **`group` カラムを必ず含めること**。

---

## CI（GitHub Actions）ルール

- `.github/workflows/lint.yml`: PHP Pint + Prettier + ESLint
- `.github/workflows/tests.yml`: Pest テスト（SQLite使用）

**MySQL固有マイグレーションには SQLite ガード必須:**
```php
if (DB::getDriverName() === 'sqlite') return;
```
`MODIFY`・`DROP FOREIGN KEY`・`AUTO_INCREMENT`・`information_schema` 参照を含むマイグレーションに追加。

---

## Migration / Seeder ルール

- 全本番 Seeder は冪等性必須: `firstOrCreate` / `updateOrCreate` / `upsert`（`insert` は使わない）
- 開発サンプルデータ: `DatabaseSeeder.php` の `$sampleData = true` で有効化
- **「その他」レコード:** `clients` / `project_jobs` / `work_item_types` / `event_item_types` / `assignments` は `OtherClientProjectSeeder` または Migration で事前登録済み

**本番必須 Seeder:** `CompanySeeder` / `DepartmentSeeder` / `AssignmentSeeder` / `TeamSeeder` / `WorkItemTypesSeeder` / `SizesSeeder` / `StagesSeeder` / `DifficultiesSeeder` / `StatusesSeeder` / `OtherClientProjectSeeder`

---

## 日報（Diary）実装ルール

- `DiaryController::store()` で `created_at` / `updated_at` を明示的にセットしない
- 既存日報チェックは `where('date', $date)` を使う（`whereDate('created_at', $date)` は誤り）
- Quill エディタへの外部コンテンツセット: `form.content` への代入と `editorInstance.setContents(delta)` の両方が必要

**TimelineDiary コンポーネント（`resources/js/Components/TimelineDiary.vue`）:**
- `ResizeObserver` でラッパー幅をリアクティブ取得。`minWidth` をピクセルで強制しない（水平スクロールが発生する）
- 夜勤モード: `defaultWorktype.start_time >= 16:00` の場合 `slotMaxTime: '30:00:00'`（翌6時）

---

## ユーザー設定・カレンダー勤務日程ルール

- `user_settings` — デフォルト勤務形態（`worktype_id`）・カレンダー表示設定
- `user_monthly_schedules` — 日ごと上書き（`year_month` + `schedule` JSON）
- 解決優先順: `user_monthly_schedules` > `user_settings.worktype_id` > 一覧の先頭
- `worktype_id` 変更時は `user_monthly_schedules` を全削除（日次設定リセット）

---

## 雇用形態管理（派遣・業務委託）ルール

**テーブル:** `users.employment_type`（regular/contract/dispatch/outsource）/ `user_employment_settings` / `dispatch_profiles`

**日報義務判定:** `user_employment_settings` レコードあり → そのフラグ / なし → regular/contract は必須、dispatch/outsource は任意

**User モデルメソッド:** `isDiaryRequired()` / `employmentTypeLabel()` / `employmentSetting()` / `dispatchProfile()`

**AssignmentForm:** dispatch/outsource ユーザー選択時にオレンジ警告ボックスを表示。コントローラーの members 配列に `employment_type` / `employment_type_label` を含めること。

---

## Coordinator 割当フロー

**案件選択中間ページ:** `coordinator.project_jobs.assignment_select` → `SelectProject.vue` → assignments.create へ遷移

**AssignmentForm.vue:** `mode='coordinator'`（デフォルト）と `mode='user'`（自己登録）で動作切り替え。Coordinator 保存は必ず `router.post()`（`inertiaFetch` では 419 CSRF エラー）。

**AssignmentForm.vue（アサイン編集、2026-04-08 実装）:**
- `isEditMode` computed: `assignments[0].id` があれば既存レコードの編集
- 編集時: 「保存して再送信」（緑）＋「送信せず保存」（グレー）の2ボタン
- `send_immediately: true` の場合: 既存 `JobAssignmentMessage` を全削除 → 再作成 → broadcast

**ProjectJob 登録時の案件名重複チェック:**
- `ProjectJobController::checkDuplicate()` — `normalizeTitle()` 適用後に比較
- ルート: `POST coordinator/project_jobs/check-duplicate` → `coordinator.project_jobs.check_duplicate`
- 警告モーダルは任意登録可（クライアント重複チェックと違い強制ブロックしない）

**ProjectJob 登録時の受注番号（jobcode）重複チェック（2026-05-23 実装）:**
- `store()` および `update()` でバリデーション後に jobcode の重複を確認
- `update()` は自身を除外して判定（`where('id', '!=', $projectJob->id)`）
- 重複時は `withErrors(['jobcode' => 'この受注番号はすでに登録されています。'])` で差し戻し（強制ブロック）
- prepress（PrepressTicket）側は以前から同じ仕組みで実装済み

**Coordinator タブメニュー:** クライアント管理 → 案件一覧 → ジョブ一覧 → 案件カレンダー

**CoordinatorMiddleware:** Leader は `/coordinator/*` にアクセス不可。

---

## ProjectJob 共同管理

**ピボットテーブル:** `project_job_coordinators`（`project_job_id`, `user_id`, UNIQUE）

**候補者条件:** `user_role = 'coordinator'` **または** `assignments.code = 'shinko'`（進行管理）

**ProjectJob モデル:** `coordinators()` → `belongsToMany(User, 'project_job_coordinators')`

`store()/update()` — `sub_coordinator_ids` を受け取り sync（リーダー自身は除外）

---

## Leader 案件総覧

**ルート:** `leader/project-jobs` プレフィックス / コントローラ: `app/Http/Controllers/Leader/ProjectJobController.php`

**タブ表示条件:** `(isDepartmentLeader || isAdminOrAbove) && can('project_job_overview')`

**権限フラグ:** `leader_permissions.project_job_overview`（boolean, default true）

---

## Leader ユーザー管理タブ

- `LeaderNavigationTabs.vue`: 部署リーダーであれば権限フラグ不問でタブ最左端に表示
- `UserManagementController`: `requireLeaderPermission('user_management')` は全メソッドから削除済み

---

## ProjectJob 完了/未完了 機能

- ルート名: `coordinator.project_jobs.complete` / `coordinator.project_jobs.uncomplete`（必ず `coordinator.` プレフィックス。`project_jobs.complete` では 404）
- 完了済み案件ではジョブ作成をブロック: `ProjectJobAssignmentsController::create()` / `store()` の両方でチェック
- `User\ProjectJobController::projectsJson()`: `where('completed', false)` で完了済みを除外

**project_jobs テーブル注意（さくら本番）⚠️**
- `schedule` カラムはさくら本番に存在しない
- `ProjectJobController::update()` では `Arr::pull($data, 'schedule')` を `$projectJob->update($data)` の前に必ず呼ぶ

---

## ProgressSheet（進行管理表）実装ルール

**関連ファイル:**
- `app/Http/Controllers/Coordinator/ProgressSheetController.php`
- `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`
- `resources/js/Components/ProgressTable.vue` / `ProgressCell.vue`

**canEdit() 権限チェック:**
```php
private function canEdit(User $user, ProjectJob $projectJob, ?ProgressSheet $sheet = null): bool
{
    $isOwner   = $projectJob->user_id === $user->id;
    $isSub     = $projectJob->coordinators()->where('users.id', $user->id)->exists();
    $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
    $isCreator = $sheet && $sheet->created_by === $user->id;
    return $isOwner || $isSub || $isAdmin || $isCreator;
}
```
`show()` / `update()` / `destroy()` / `registerAsTemplate()` / `linkJob()` 全てに `$sheet` を渡すこと。

**jobLinkOnly パターン:** `canEdit=false` かつ `jobLinkOnly=true` で joblink セルのみ操作可能。

**User側 MyJob 登録フロー:**
ルート: `POST user/project-jobs/{job}/progress-sheets/{sheet}/link-job` → `user.project_jobs.progress_sheets.link_job`

**user型セル × joblinkセルのロック連動:** `ProgressTable.vue` の `lockedUserIdForCell()` が同グループ内 joblink セルの `assignment_user_id` を探し、user 型セルに `:locked-user-id` を渡す。

---

## 依頼ジョブとマイジョブの重複排除（2026-04-10 実装）

**`supersedes_assignment_id` FK:** マイジョブが依頼ジョブを置き換えたことを明示するカラム（自己参照FK）。

**Migration:** `2026_04_10_200001_add_supersedes_assignment_id_to_project_job_assignments.php`

**非表示ロジック:**
- JobBoxController: SQL `whereNotExists` サブクエリ
- Coordinator `show()`: PHP `array_filter` ＋ タイトル一致 fallback

**注意:** `source_assignment_id`（続きジョブチェーン用）とは別カラム。混同しないこと。

---

## 校正ジョブフロー（2026-05-30 統合）

**基本方針:** 校正ジョブ専用タブは廃止。通常の「依頼されたジョブ → マイジョブ」フローに統一。

**pja100（coordinator 割当の proof ジョブ）の特徴:**
- `job_type = 'proof'`
- `sender_id` = 校正管理者ID、`user_id` = 校正担当者ID（sender ≠ user）
- `coordinator_assignment_id = NULL`（旧 pja101 は廃止）

**pja101（廃止）:** 旧フローの中間作業スロット割当は廃止。代わりにカレンダーイベントを pja100 に直接作成。

**JobBox への表示:** `ProofRequestController::assignStore()` で `JobAssignmentMessage` を自動生成。プロが「依頼されたジョブ」タブに pja100 が自然に表示される。

**スケジュール設定:** `SavesProofWorkSlots` trait が pja100 に直接 `Event` を作成（`project_job_assignment_id = pja100.id`）。

**完了の連動（2パターン）:**
1. **PCなし校正者:** 校正管理者が `ProofRequestController::complete()` で完了 → ProofRequest 完了 + 通知
2. **PC有り校正者:** `MyProjectJobController::completeAssignment()` → `maybeCompleteProofRequest()` が ProofRequest を完了させ通知送信。supersedes_assignment_id 経由でも機能する（マイジョブにしたケース）

**進行表工数集計:** pja100 直接の Events + supersededBy な pja101 の Events の両方が集計対象（変更なし）。

**Coordinator 割当一覧の重複排除:**
```php
// job_type='proof' かつ coordinator_assignment_id IS NOT NULL（旧 pja101）を除外
->where(function ($q) {
    $q->where(function ($inner) {
        $inner->where('job_type', '!=', 'proof')->orWhereNull('job_type');
    })->orWhereNull('coordinator_assignment_id');
})
```

---

## マイジョブ 続きジョブ連動機能（2026-04-04 実装）

**`source_assignment_id`:** 日をまたいだジョブをチェーンするカラム（自己参照FK）。

**Migration:** `2026_04_04_200001_add_source_assignment_id_to_project_job_assignments.php`

**完了カスケード:** 続きジョブを完了にすると祖先ジョブも自動完了（深さ最大20）。`MyProjectJobController::completeAssignment()` 内で実装。

**⚠️ sender_id の注意:** `store()` で `sender_id` をフォームから渡さない場合は `$a['sender_id'] ?? $user->id` をデフォルトにすること（`null` になると `selfAssigned()` スコープに一致せず 404）。

---

## ジョブ通知機能（2026-04-07 実装）

**主要ファイル:** `app/Models/JobNotification.php` / `app/Services/JobNotificationService.php` / `app/Http/Controllers/JobNotificationController.php`

**Migration:** `2026_04_07_200001_create_job_notifications_table.php`

**通知タイプ:** `new_job` / `new_job_info` / `completed` / `completed_info` / `progress_registered` / `progress_completed`

**自己割当（`sender_id = user_id`）の完了は `completed` を送らない。**

**⚠️ 完了処理の注意:**
- `completeAssignment()` の引数型は `ProjectJobAssignment`（`ProjectJobAssignmentByMyself` にすると Coordinator 割当ジョブが 404）
- 通知時刻は UTC→JST 変換が必要（`toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo' })`）

**進行管理表アクセス制御:** `User/ProgressSheetController::authorizeView()` の条件: 案件オーナー / サブCo / チームメンバー / Admin / `project_job_assignments` に割り当て済み

---

## Admin/Users/Edit フォーム仕様

Create.vue と同等のフルフォーム。フィールド: name / email / password / company_id / department_id / assignment_id / user_role / employment_type / position_title_id。

company_id → department_id → assignment_id のカスケードリセットあり。`Admin/UserController::edit()` で companies（部署・担当付き）と positionTitles を渡す。

---

## イルカボード（在籍ボード, 2026-05-15）

**テーブル:** `user_presence_statuses`

| カラム | 型 | 内容 |
|-------|-----|------|
| user_id | FK → users | |
| status | enum | `in_office` / `remote` / `out` / `off` |
| status_detail | text (nullable) | 自由記述 |
| updated_at | timestamp | |

**コントローラ:** `app/Http/Controllers/User/UserPresenceController.php`

**Vue:** `resources/js/Pages/User/IrukaBoard/Index.vue`

**ルート:**
- `GET user/iruka-board` → `user.iruka_board.index`
- `PUT user/presence` → `user.presence.update`

**カレンダー連動:** FullCalendar の `eventContent` で在籍状況バッジを表示。`user_presence_statuses` を EventController と同 props に含めて渡す。

---

## ゴーストユーザー（テストユーザー機能, 2026-05-13）

**追加カラム（`users` テーブル）:**
- `is_ghost` — boolean, default false
- `ghost_owner_id` — FK → users.id, nullable（どの管理者が所有するゴーストか）

**用途:** テスト用・仮ユーザー。通常の一覧・割当・ワークロード集計から除外する。

**管理:**
- `Admin/UserController` — `ghost_owner_id` 経由で SuperAdmin が管理
- `Admin/Users/Index.vue` — ゴーストユーザー表示トグル（デフォルト非表示）

**スコープ:** 通常クエリには `whereIsGhost(false)` または `where('is_ghost', false)` を付けること。

---

## 更新ログ（Changelog, 2026-05-23）

**テーブル:** `changelogs`

| カラム | 型 | 内容 |
|-------|-----|------|
| version | varchar(30), unique | バージョンスラッグ（例: `repair-5`） |
| title | varchar(200) | 一覧表示タイトル |
| released_at | date | リリース日 |
| summary | text | 一覧用概要（プレーンテキスト） |
| body | longText | 詳細本文（HTML, DOMPurify でサニタイズ） |
| design_files | JSON, nullable | 関連設計ファイル名の配列 |
| claude_notes | text, nullable | Claude 向けメモ |

**モデル:** `app/Models/Changelog.php`（casts: released_at → date, design_files → array）

**コントローラ:** `app/Http/Controllers/ChangelogController.php`
- `index()` — released_at 降順、id/version/title/released_at/summary のみ取得
- `show(Changelog $changelog)` — 全カラムを Inertia で渡す

**Seeder:** `ChangelogSeeder`（`updateOrCreate(['version' => ...])` で冪等性確保）

**ルート（認証不要）:**
- `GET /changelogs` → `changelogs.index`
- `GET /changelogs/{changelog}` → `changelogs.show`

**Vue:**
- `resources/js/Pages/Changelogs/Index.vue` — カード一覧
- `resources/js/Pages/Changelogs/Show.vue` — 詳細 + 設計ファイル折りたたみ

**SuperAdmin 専用:** `auth.user.isSuperAdmin` が true の場合、`design_files` を折りたたみセクションで表示（`auth.isSuperAdmin` は存在しない — 必ず `auth.user.isSuperAdmin` を参照）

**Claude 参照指示:** 概要・詳細を読み、必要なら `z_instructions/archived/` 内の設計ファイルを参照する。

---

## 管理シート（WorkflowSheets / Process, 2026-05-14）

**テーブル:**
- `workflow_sheets` — シート本体（project_job_id, title 等）
- `workflow_sheet_rows` — 行定義（sheet_id, order 等）
- `workflow_sheet_cells` — セルデータ（row_id, column_key, value 等）

**コントローラ:** `app/Http/Controllers/Coordinator/WorkflowSheetController.php`

**Vue:** `resources/js/Pages/Coordinator/WorkflowSheets/`

---

## スクリプトセクション（Scripts, 2026-05-16）

**アクセス制御:** `auth.canAccessScripts`（`auth.user` 配下ではなく **`auth` 直下**）でアイコン表示制御。

**実装規約:** `z_instructions/SCRIPTS_SECTION_GUIDELINES.md`（新規ツール追加時は必読）

**コンポーネント:** `resources/js/Components/Scripts/` に配置。`Show.vue` の `componentMap` にキーを登録する。

**AppLayout:** スパナアイコンボタンとして AppLayout ヘッダー右に配置済み（auth.canAccessScripts が true のユーザーのみ表示）。

---

## クライアント ID（client_code, 2026-05-21）

**テーブル:** `clients`

| カラム | 型 | 内容 |
|-------|-----|------|
| client_code | varchar(20), nullable, unique | クライアント固有ID（CSV 突合キー） |
| is_registered | boolean, default false | CSV 登録フロー完了フラグ |

**用途:**
- CSV インポート時に `client_code` で既存クライアントを特定・突合する
- `is_registered = false` のクライアントは未確認状態。Coordinator が確認後に `true` にする

**注意:** `client_code` はさくら本番 Migration 済み。新規クライアント作成時に任意入力。

---

## 製版ボード（Prepress Board, 2026-04-28）

**Vue:** `resources/js/Pages/Coordinator/PrepressBoard/`

**ルート プレフィックス:** `coordinator.prepress_board.*`

案件（ProjectJob）の製版工程を Kanban ボード形式で表示・管理する。伝票詳細モーダル・インラインクライアント表示あり。

---

## 一括案件登録（BulkCreate, 2026-04-20）

**Vue:** `resources/js/Pages/Coordinator/BulkCreate/Index.vue`

**ルート:** `coordinator.project_jobs.bulk_create`

CSV から複数の ProjectJob を一括登録する機能。`NormalizesCsvEncoding` Trait を使用。

# CLKCAL_PLAN1 — Clerk カレンダー機能 移設計画

## 背景・目的

`https://sun-brain.co.jp/members/team-rooms/22?tab=schedule` で使われている「スケジュール」タブの機能一式
（月カレンダー／週間プランナー／週の掲示板／CSV入出力／スケジュール一覧パネル）を、Clerk のタブメニューに
「カレンダー」という新項目として移設する。

**方針: 基本は忠実に移設する。ただし予定の編集・削除権限のみ、Clerk の運用実態（2〜3人で予定を共同管理したい）
に合わせて最初から調整する。その他の Clerk 向け仕様調整（表示など）は次フェーズで行う。**

## ユーザー確認済み事項

- **データ共有範囲: 会社単位（company_id）で共有。** 同じ会社の Clerk / Admin / SuperAdmin / 部署リーダーが
  同じカレンダーを閲覧・編集できる（`ClerkMiddleware` のアクセス許可ロールと同じ）。
- Clerk は「チーム」ではなく、`leader > clerk > coordinator > user` という権限階層の1つ（上位ロールは下位ロールの
  権限を含む）。既存の `teams` テーブルには依存しない。

## 移設元（コピー元）

| 種別 | ファイル |
|---|---|
| ページ | `resources/js/Pages/TeamRoom/Show.vue`（`activeTab === 'schedule'` セクション） |
| コンポーネント | `resources/js/Components/TeamRoom/TeamScheduleCalendar.vue`（月カレンダー・週間プランナー切替・CSV・スケジュールパネル） |
| コンポーネント | `resources/js/Components/TeamRoom/TeamWeekPlanner.vue`（週間プランナー・週の掲示板） |
| コントローラ | `app/Http/Controllers/TeamRoom/TeamEventController.php` |
| コントローラ | `app/Http/Controllers/TeamRoom/TeamWeekPostController.php` |
| モデル | `app/Models/TeamEvent.php` |
| モデル | `app/Models/TeamWeekPost.php` |

`TeamRoom/Components/TeamSchedule.vue`（FullCalendar 単体の旧版・現在 Show.vue からは未参照）は移設対象外。

## DB 設計（新規テーブル）

### `clerk_events`（`team_events` 相当・team_id → company_id に変更）

```php
$table->id();
$table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
$table->string('title');
$table->text('description')->nullable();
$table->dateTime('starts_at');
$table->dateTime('ends_at')->nullable();
$table->boolean('all_day')->default(false);
$table->timestamps();
```

### `clerk_week_posts`（`team_week_posts` 相当）

```php
$table->id();
$table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
$table->unsignedSmallInteger('year');
$table->unsignedTinyInteger('week');
$table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
$table->text('body');
$table->foreignId('parent_id')->nullable()->constrained('clerk_week_posts')->cascadeOnDelete();
$table->timestamps();
```

## 会社スコープの決定ロジック（Clerk/AnnouncementController と同じパターンを踏襲）

```php
$companyId = $user->isSuperAdmin()
    ? session('superadmin_context.company_id')
    : $user->company_id;
```

## 変更・追加ファイル一覧

### 新規

| ファイル | 内容 |
|---|---|
| `database/migrations/xxxx_create_clerk_events_table.php` | 上記DB設計 |
| `database/migrations/xxxx_create_clerk_week_posts_table.php` | 上記DB設計 |
| `app/Models/ClerkEvent.php` | `TeamEvent.php` 移設（`company()` リレーションに変更） |
| `app/Models/ClerkWeekPost.php` | `TeamWeekPost.php` 移設 |
| `app/Http/Controllers/Clerk/ClerkCalendarController.php` | `index()` のみ。`Inertia::render('Clerk/Calendar/Index')` |
| `app/Http/Controllers/Clerk/ClerkEventController.php` | `TeamEventController.php` 移設（index/store/update/destroy/csvExport/csvImport、`NormalizesCsvEncoding` 使用） |
| `app/Http/Controllers/Clerk/ClerkWeekPostController.php` | `TeamWeekPostController.php` 移設（index/store/destroy） |
| `resources/js/Pages/Clerk/Calendar/Index.vue` | AppLayout + `#tabs`（`ClerkNavigationTabs` active="calendar"）+ カレンダー本体 |
| `resources/js/Components/Clerk/ClerkScheduleCalendar.vue` | `TeamScheduleCalendar.vue` 移設（`teamId` prop 削除、route名を `clerk.calendar.events.*` に変更） |
| `resources/js/Components/Clerk/ClerkWeekPlanner.vue` | `TeamWeekPlanner.vue` 移設（`teamId` prop 削除、route名を `clerk.calendar.week_posts.*` に変更） |

### 変更

| ファイル | 内容 |
|---|---|
| `routes/web.php` | 既存の `clerk.` prefix グループ内にカレンダー関連ルートを追加 |
| `resources/js/Components/Tabs/ClerkNavigationTabs.vue` | タブに「カレンダー」を追加（`clerk.calendar`） |

## ルート設計（既存 `clerk.` グループ内に追加、ミドルウェアは既存のまま）

```
GET    /clerk/calendar                     clerk.calendar
GET    /clerk/calendar/events              clerk.calendar.events.index
POST   /clerk/calendar/events              clerk.calendar.events.store
PUT    /clerk/calendar/events/{event}      clerk.calendar.events.update
DELETE /clerk/calendar/events/{event}      clerk.calendar.events.destroy
GET    /clerk/calendar/events/csv-export   clerk.calendar.events.csv_export
POST   /clerk/calendar/events/csv-import   clerk.calendar.events.csv_import
GET    /clerk/calendar/week-posts          clerk.calendar.week_posts.index
POST   /clerk/calendar/week-posts          clerk.calendar.week_posts.store
DELETE /clerk/calendar/week-posts/{post}   clerk.calendar.week_posts.destroy
```

アクセス制御は既存の `clerk` ミドルウェア（`ClerkMiddleware`）をそのまま使う
（許可: clerk / admin / superadmin / 部署リーダー）。これがそのまま「会社単位で共有するメンバー」の範囲になる。

## 予定の編集・削除権限（移設元と変更する点）

Clerk は2〜3人で予定管理を全員で行いたいとのことなので、**作成者本人に限定せず、カレンダーにアクセスできる人
（clerk / admin / superadmin / 部署リーダー = `ClerkMiddleware` を通過した人）なら誰でも編集・削除可能**にする。
`ClerkEventController::update / destroy` では `TeamEventController` にあった
`if ($event->user_id !== Auth::id() && ! Auth::user()->isSuperAdmin()) { abort(403); }` の所有者チェックを行わない
（ルートに到達している時点で `clerk` ミドルウェアを通過済み＝編集権限ありとみなす）。
週の掲示板（`ClerkWeekPostController::destroy`）も同様に所有者チェックを外す。

## 移設時に忠実にコピーする挙動（あえて変えない点）

- CSV入出力フォーマット・エラーメッセージも同一。
- 週の掲示板（返信スレッド・ロール別カラー表示）もそのまま。
- 日付の扱い（`starts_at`/`ends_at` を `datetime` cast、フロントの `toLocaleDateString('sv-SE')` 使用等）も
  移設元のまま変更しない。CLAUDE.md の UTC/JST ルールに抵触する箇所は見当たらない
  （all_day 予定は日付文字列のみを保存・比較しており、events テーブルの proof/UTC 問題とは無関係）。

## 次フェーズ（今回のスコープ外・別途相談）

- Clerk ダッシュボードとの統合（今後どのように見せるか）
- 不要な機能（CSV等）を削るかどうかの取捨選択

## 作業完了後に行うこと（CLAUDE.md ルール）

1. `ChangelogSeeder` に追記 → `php artisan db:seed --class=ChangelogSeeder`
2. `z_instructions/CONSOLIDATED_01_layout_and_ui.md`（UI変更）・`CONSOLIDATED_09_domain_rules.md`（Clerk領域の新機能）を更新
3. `CLKCAL_PLAN1.md` / `CLKCAL_MANAGER1.md` / `CLKCAL1_PROMPT.md` を `z_instructions/archived/` に移動

# Clerkカレンダー・リマインダー 設計図

作成: 2026-09-15 / Codex / 状態: 設計確認待ち

## 実装後の仕様変更（2026-09-15）

- 表示先は一般ユーザー権限の`/calendar`のみ。`/clerk/calendar`には表示しない。
- 各リマインダーは一段の帯とし、「事務からのお知らせ：内容」を中央揃え・太字で表示する。表示期間は帯へ出さない。
- 設定画面でClerk予定と同じ11色から選ぶ。濃い色を枠線、同系統の薄い色を背景に使う。
- `color_key`追加用migrationは`2026_09_15_120001_add_color_key_to_clerk_calendar_reminders_table.php`。
- 以下の当初設計にあるClerkカレンダー表示、琥珀色固定、期間表示はこの変更で置き換えられた。

## 1. 目的

交通費精算など、全ユーザーに確実に見てほしい期限案内を、通知一覧とは別に日々使うカレンダー画面へ表示する。Clerk権限者が内容と表示期間を会社単位で設定できるようにする。

## 2. 調査結果

- 一般ユーザーが予定を登録・確認する画面は`Calendar/Index.vue`、Clerkの会社共有カレンダーは`Clerk/Calendar/Index.vue`。
- どちらも`AppLayout`のheaderとデフォルトスロットを使い、カレンダー本体を白カードに入れている。
- Clerkの上部メニューは`ClerkNavigationTabs.vue`。ここへ「リマインダー設定」を追加できる。
- 既存の予定日設定は、会社スコープ、SuperAdminの会社コンテキスト、Clerk紫の一覧・フォーム、開始日・終了日、有効/停止を実装済み。リマインダー管理もこの構成を踏襲する。
- リマインダーは予定や通知の受信レコードを生成する必要がない。表示期間中にカレンダーへ案内帯として出す独立データが適する。
- 日付だけを扱うため、DBとJSONは`Y-m-d`、今日の判定は`Asia/Tokyo`で統一する。

## 3. 画面仕様

### 3.1 ユーザーへの表示

- 対象: 一般ユーザーの予定表`/calendar`と、Clerkの会社共有カレンダー`/clerk/calendar`。
- 位置: AppLayoutのナビゲーション直下、カレンダー本体の白カード直前。画像の「白い帯とカレンダーの間」に相当する。
- タイトル横へ長文を押し込まず、横幅を使える共通コンポーネント`CalendarReminderBanner.vue`を置く。
- 表示中の会社に属する、有効かつ「開始日 <= JSTの今日 <= 終了日」のリマインダーをすべて表示する。
- 複数件は同じ帯の中で縦に並べる。各行にリマインド内容と表示期間を出す。
- 見落としにくい薄い琥珀色の背景、濃い文字、注意アイコン、左境界線を使用する。Clerkの操作ボタンは紫のまま維持する。
- 内容はプレーンテキストとして表示し、改行を保持する。HTMLは受け付けない。
- 表示期間外・停止中・削除済みは表示しない。ユーザー側の既読、閉じる、非表示設定は設けない。
- デスクトップとモバイルで本文を省略しない。長い内容は折り返す。

### 3.2 Clerk管理画面

- Clerk上部メニューに「リマインダー設定」タブを追加する。
- 一覧URL: `/clerk/reminders`。新規作成・編集ページを設ける。
- 入力項目:
  - リマインド内容（必須、1〜1000文字）
  - 表示開始日（必須）
  - 表示終了日（必須、開始日以降）
  - 有効/停止
- 日付範囲は両端を含む。1日のみなら開始日と終了日を同日にする。
- 一覧には内容、表示期間、現在の状態（表示中・予定・終了・停止）、編集、停止/再開、削除を表示する。
- 並び順は表示開始日の新しい順。同日の場合は新しい登録順。
- 作成・更新後は一覧へ戻り、既存のグローバルトーストで結果を表示する。
- 削除前には確認を出す。通常の物理削除でよく、ユーザーの業務予定や通知データには影響しない。

### 3.3 レイアウト規則

- 新規ページは`AppLayout`を使い、独自`main`、`py-12`、`max-w-7xl`を追加しない。
- header内に灰色の戻るリンクと見出しを横並びで置く。
- 本文は`rounded bg-white p-6 shadow`。テーブルは既存の共通スタイルを使う。
- 主操作はClerkの紫、キャンセルは灰色、削除は赤。
- `ToastUnified`は追加しない。Ziggyの名前付き引数はオブジェクト形式にする。

## 4. 権限と会社スコープ

- CRUDルートは既存`clerk`ミドルウェア内に置く。
- リマインダーは`company_id`単位。別会社のIDを指定した閲覧・更新・削除は404。
- SuperAdminは選択中の会社コンテキストを優先し、未選択なら既存の会社解決ルールに従う。会社を解決できない場合は403。
- 一般ユーザーの予定表は、実際に表示しているユーザーの所属会社のリマインダーを渡す。管理者が別ユーザーの予定表を表示する場合も、その対象ユーザーの会社を使う。
- ClerkカレンダーはClerkCalendarControllerで解決した会社コンテキストを使う。

## 5. DB設計

### clerk_calendar_reminders（新規）

| 列 | 型・制約 | 用途 |
| --- | --- | --- |
| id | bigint PK | ID |
| company_id | FK companies, cascade | 表示対象会社 |
| created_by | FK users, cascade | 登録者 |
| content | text | リマインド内容 |
| starts_on | date | 表示開始日、含む |
| ends_on | date | 表示終了日、含む |
| is_active | boolean default true | 停止制御 |
| timestamps | timestamps | 作成・更新日時 |

- 検索用に`(company_id, is_active, starts_on, ends_on)`の複合インデックスを付ける。
- モデルの日付キャストは`date:Y-m-d`。`is_active`はboolean。
- 同一内容・同一期間の重複登録は業務上あり得るため、一意制約は付けない。

## 6. サーバー設計

- `ClerkCalendarReminder`モデルに会社スコープと「指定日に表示中」のスコープを用意する。
- `ClerkCalendarReminderRequest`へ入力検証を集約する。
- `ClerkCalendarReminderController`で一覧、作成、保存、編集、更新、有効切替、削除を扱う。
- 一般ユーザーとClerkカレンダーへの表示データ整形は共通サービス、またはモデルの共通クエリに集約する。
- 表示判定は必ずサーバー側で行う。ブラウザ時計だけに依存しない。
- Inertia propは`calendarReminders`とし、`id/content/starts_on/ends_on`だけを渡す。
- 通知テーブル、`announcement_recipients`、`events`、`clerk_events`には書き込まない。未読ランプや個人予定を増やさない。

## 7. 予定変更ファイル

| ファイル | 作業 |
| --- | --- |
| `database/migrations/*_create_clerk_calendar_reminders_table.php` | 会社別リマインダーテーブル |
| `app/Models/ClerkCalendarReminder.php` | casts、会社・表示期間スコープ |
| `app/Http/Requests/ClerkCalendarReminderRequest.php` | 内容・期間の検証 |
| `app/Http/Controllers/Clerk/ClerkCalendarReminderController.php` | 管理CRUD、有効切替、会社分離 |
| `app/Http/Controllers/Clerk/ClerkCalendarController.php` | Clerkカレンダーへ表示中データを渡す |
| `app/Http/Controllers/EventController.php` | 通常予定表へ対象ユーザー会社の表示中データを渡す |
| `resources/js/Components/Calendar/CalendarReminderBanner.vue` | 共通の案内帯 |
| `resources/js/Pages/Calendar/Index.vue` | 一般ユーザー予定表へ案内帯を配置 |
| `resources/js/Pages/Clerk/Calendar/Index.vue` | Clerkカレンダーへ案内帯を配置 |
| `resources/js/Pages/Clerk/Calendar/Reminders/Index.vue` | 管理一覧 |
| `resources/js/Pages/Clerk/Calendar/Reminders/Form.vue` | 作成・編集フォーム |
| `resources/js/Components/Tabs/ClerkNavigationTabs.vue` | 「リマインダー設定」タブ |
| `routes/web.php` | Clerk管理ルート |
| `tests/Unit/ClerkCalendarReminderTest.php` | 日付境界、会社分離、状態、検証 |
| `database/seeders/ChangelogSeeder.php` | 完了時の更新履歴 |
| `z_instructions/CONSOLIDATED_01_layout_and_ui.md` | 管理画面・案内帯のUI規則 |
| `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` | データ・期間・運用仕様 |

実装中は既存のClerkカレンダー、予定日設定、通知修正、売上分析などの未コミット差分を保護する。

## 8. 検証計画

- 開始日前、開始日当日、期間中、終了日当日、終了日翌日の表示判定。
- 1日だけの期間、月末、年越し、うるう日。
- 停止・再開・更新・削除の反映。
- A社の設定がB社へ表示されず、別会社IDの更新・削除が404になること。
- SuperAdminの会社コンテキストと、別ユーザーの予定表表示時の会社解決。
- 通常予定表とClerkカレンダーで同じ内容・期間になること。
- 長文、改行、複数件、モバイル幅でレイアウトが崩れないこと。
- 通知未読件数、個人予定数、Clerk予定数が変化しないこと。
- PHPテストは独立インメモリSQLiteを使用し、`Tests\TestCase`と`RefreshDatabase`を使わない。
- Vue変更後に`npm run build`、PHP構文、ルート一覧、`git diff --check`を確認する。
- ローカルmigrationは接続先DB名を確認してから追加migrationだけを適用する。本番配備は別指示とする。

## 9. 実施順

設計承認 → DB・モデル・検証 → Clerk管理CRUD → 共通案内帯 → 通常予定表/Clerkカレンダー接続 → 自動テスト/build → ローカルmigration → 画面確認 → 更新履歴・統合資料 → 設計3資料をarchivedへ移動。

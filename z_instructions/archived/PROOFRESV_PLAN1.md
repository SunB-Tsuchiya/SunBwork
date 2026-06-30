# PROOFRESV_PLAN1

## 目的

Coordinator の案件一覧から、案件に紐づく「校正予約」を送信できるようにする。
予約は通常の校正依頼とは分離し、proof-admin（proof_coordinator）の「校正予約一覧」で確認する。

## 確定仕様

- 案件一覧のステータス列の右に「校正予約」ボタンを表示する。
- ボタンから専用モーダルを開き、案件IDと案件名を引き継ぐ。
- 入力項目:
  - タイトル
  - 依頼予定日時
  - 締め切り日時
  - 備考
- 依頼予定日時・締め切り日時は、それぞれ独立して「日時」と「テキスト」を切り替えられる。
- 日時入力は既存校正依頼モーダルと同じく、日付・時・分で入力する。
- 送信ボタンは「校正予約を送る」とする。
- 通常校正依頼と予約が混ざらないよう、予約専用テーブルを使用する。
- proof-admin タブに「校正予約一覧」を追加する。
- 一覧の検索、年月絞り込み、依頼予定日/締め切り日の基準切替はジョブ管理画面を踏襲する。
- 一覧行をクリックすると予約詳細へ遷移する。
- 詳細は校正依頼詳細のカードレイアウトを踏襲し、予約時の全入力内容を表示する。
- 依頼予定と締め切りが両方とも日時入力で、開始 < 終了の場合のみ「カレンダーに登録」できる。
- 登録後は `/proof-coordinator/calendar` の月表示に、依頼予定日時を開始、締め切り日時を終了とする1本の期間ストリップを表示する。
- FullCalendar の終了日は排他的であるため、終了日時を含む日まで表示されるよう日付を補正する。
- 自由記述を含む予約はカレンダー登録不可。

## DB設計

新規 `proof_reservations`:

| カラム | 用途 |
| --- | --- |
| `project_job_id` | 関連案件 |
| `requester_id` | 予約送信者 |
| `title` | 校正予約タイトル |
| `requested_at_mode` | `datetime` / `text` |
| `requested_at` | 依頼予定日時（UTC保存） |
| `requested_at_text` | 依頼予定の自由記述 |
| `deadline_mode` | `datetime` / `text` |
| `deadline_at` | 締め切り日時（UTC保存） |
| `deadline_text` | 締め切りの自由記述 |
| `note` | 備考 |
| `calendar_registered_at` | 校正カレンダー登録日時 |

## 影響ファイル

- `database/migrations/*_create_proof_reservations_table.php`
- `app/Models/ProofReservation.php`
- `app/Http/Controllers/ProofCoordinator/ProofReservationController.php`
- `app/Http/Controllers/ProofCoordinator/CalendarController.php`
- `routes/web.php`
- `resources/js/Components/ProofReservationModal.vue`
- `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue`
- `resources/js/Pages/Coordinator/ProjectJobs/Index.vue`
- `resources/js/Pages/ProofCoordinator/Reservations/Index.vue`
- `resources/js/Pages/ProofCoordinator/Reservations/Show.vue`
- `resources/js/Pages/ProofCoordinator/Calendar.vue`
- 関連統合文書・ChangelogSeeder

## 既存誤実装の整理

- 通常の `ProofRequestModal` は予約に流用しない。
- `proof_requests` を予約一覧の保存・検索対象にしない。
- 依頼されていない予約の作成・編集・削除CRUD画面は削除する。
- AppLayout に追加された不要な予約専用アクティブ判定は削除する。

## 検証

- PHP構文チェック
- ルート一覧
- マイグレーション（SQLiteを使う関連テストを含む）
- 予約作成・一覧絞り込み・カレンダー登録のFeatureテスト
- `npm run build`

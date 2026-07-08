# OPCAL2_PROMPT.md — 新セッション開始用プロンプト（オペレーターカレンダー Phase 2）

---

## 概要

オペレーターカレンダー（`/coordinator/operator-calendar`）Phase 1 は完成済み。
Phase 2 として「二重予約リクエスト機能」を実装する。

詳細仕様: `z_instructions/OPCAL_PLAN2.md`
進捗管理: `z_instructions/OPCAL_MANAGER2.md`
Phase 1 資料: `z_instructions/OPCAL_PLAN1.md` / `OPCAL_MANAGER1.md`

---

## Phase 2 設計サマリー

### 目的
Phase 1 は二重予約を無警告で許容するのみだったが、「相手に確認を取ってから確定させたい」ケース向けに
任意のリクエスト（承諾/拒否）ワークフローを追加する。既存の自由編集（誰でも編集・削除可）は変更しない。

### トリガーと承諾/拒否の挙動
- 予約**新規作成**モーダルで、同一オペレーター・重複時間帯の既存予約を検出した場合のみリクエスト導線を表示
- 承諾 → 既存予約を削除し、リクエスト内容で新規予約を作成
- 拒否 → 変更なし、リクエストを rejected に
- 1件承諾されたら、同一既存予約への他の pending リクエストは自動的に rejected

### 新規DBテーブル
- `operator_reservation_requests`（pending/approved/rejected、対象予約・申請内容を保持）
- `operator_reservation_notifications`（`ScheduleNotification`と同様の read_at 方式）

### 通知の表示範囲
オペレーターカレンダーページ内のみ。サイト全体の AppLayout ヘッダーや HandleInertiaRequests ミドルウェアは変更しない。

### 参考実装
| 流用元 | 用途 |
|--------|------|
| `app/Models/ScheduleAttendee.php` / `ScheduleAttendeeController::respond()` | pending→approved/rejected の承諾/拒否パターン |
| `app/Models/ScheduleNotification.php` / `ScheduleNotificationController.php` | read_at 方式の軽量通知 |

### コントローラー追加（`app/Http/Controllers/Coordinator/OperatorCalendarController.php`）
```
notifications()          自分宛の未読通知一覧
markNotificationRead()   既読化
storeRequest()           リクエスト作成＋対象予約者への通知
respondRequest()         承諾/拒否処理（承諾時は既存予約削除→新規作成、他のpendingは自動却下）
```
`index()`/`data()` にも `pendingRequestReservationIds` を追加（点滅表示用）。

### Vue変更（`resources/js/Pages/Coordinator/OperatorCalendar.vue`）
- 予約作成モーダル: `localReservations` と分ベースで競合検出 → 競合時は「そのまま保存」／「リクエストを送る」の2ボタン
- ツールバーに🔔通知ベル＋未読バッジ＋ドロップダウン（承諾/拒否ボタン付き）
- 保留中リクエストがある予約ブロックに `animate-pulse` ＋ 赤枠で点滅表示

---

## 現在の実装フェーズ

`OPCAL_MANAGER2.md` の進捗テーブルを参照して作業を続けてください。

---

## 重要ルール（CLAUDE.md より）

- Vue / JS ファイルを変更したら必ず最後に `npm run build` を実行
- Artisan は必ずコンテナ内: `docker compose exec laravel bash -lc "php artisan ..."`
- `starts_at`/`ends_at` は JST文字列そのまま格納（`Y-m-d H:i:s`、UTC変換なし）。Phase 1 のUTC/JSTバグ修正を参照し、同じ方式を厳守すること
- 完了後: ChangelogSeeder への追記、CONSOLIDATED_05/09 の更新、本ファイル群を `z_instructions/archived/` へ移動

# OPCAL_PLAN2.md — オペレーターカレンダー Phase 2: 二重予約リクエスト機能 詳細仕様

---

## 概要

Phase 1 では二重予約（同一オペレーター・同一時間帯の複数予約）を無警告で許容した。
Phase 2 では、それに加えて「相手に確認を取ってから確定させる」任意のリクエスト機能を追加する。
Phase 1 の「誰でも自由に重ねて予約できる」動作は変更しない — リクエストはその上に乗る任意の礼儀的ワークフロー。

参考実装:
- 承諾/拒否ワークフロー → `app/Models/ScheduleAttendee.php` / `app/Http/Controllers/Schedule/ScheduleAttendeeController.php::respond()`
- 通知（read_at方式） → `app/Models/ScheduleNotification.php` / `app/Http/Controllers/Schedule/ScheduleNotificationController.php`

---

## 確定した設計方針

| 項目 | 決定内容 |
|---|---|
| トリガー | 予約**新規作成**モーダルで、選択時間帯が同一オペレーターの既存予約と重なる場合のみ。ドラッグ移動・リサイズでの重複は対象外（従来通り無警告） |
| 承諾時の挙動 | 既存予約を削除し、リクエスト内容で新規予約を作成（置き換え） |
| 拒否時の挙動 | 何も変更せず、リクエストを rejected にするのみ |
| 通知の表示範囲 | オペレーターカレンダーページ内のみ（AppLayout・HandleInertiaRequestsは変更しない） |
| 応答できる人 | Phase 1 の編集権限方針を踏襲し、利用ロール内なら誰でも承諾・拒否可能（対象予約の予約者本人に限定しない） |
| 複数リクエストの整合性 | 1件が承諾されたら、同じ既存予約に対する他の pending リクエストは自動的に rejected にし、それぞれの申請者に通知する |

---

## DB 設計

### 1. operator_reservation_requests（リクエスト本体）

```sql
CREATE TABLE operator_reservation_requests (
  id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conflicting_reservation_id  BIGINT UNSIGNED NULL,   -- 対象の既存予約（削除後もNULLで履歴を残す）
  operator_user_id            BIGINT UNSIGNED NOT NULL,
  requested_by_user_id        BIGINT UNSIGNED NOT NULL,
  job_name                    VARCHAR(255) NOT NULL,
  memo                        TEXT NULL,
  starts_at                   DATETIME NOT NULL,
  ends_at                     DATETIME NOT NULL,      -- JST文字列そのまま格納（Phase1と同方式）
  status                      VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending/approved/rejected
  responded_by_user_id        BIGINT UNSIGNED NULL,
  responded_at                TIMESTAMP NULL,
  created_at, updated_at      TIMESTAMP NULL,
  FOREIGN KEY (conflicting_reservation_id) REFERENCES operator_reservations(id) ON DELETE SET NULL,
  FOREIGN KEY (operator_user_id)      REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (requested_by_user_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (responded_by_user_id)  REFERENCES users(id) ON DELETE SET NULL
);
```

### 2. operator_reservation_notifications（通知）

```sql
CREATE TABLE operator_reservation_notifications (
  id                               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  operator_reservation_request_id  BIGINT UNSIGNED NOT NULL,
  user_id                          BIGINT UNSIGNED NOT NULL,  -- 通知の宛先
  type                              VARCHAR(30) NOT NULL,      -- request_created/request_approved/request_rejected
  read_at                           TIMESTAMP NULL,
  created_at, updated_at            TIMESTAMP NULL,
  FOREIGN KEY (operator_reservation_request_id) REFERENCES operator_reservation_requests(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## モデル

```
app/Models/
  OperatorReservationRequest.php
    fillable: conflicting_reservation_id, operator_user_id, requested_by_user_id, job_name, memo,
              starts_at, ends_at, status, responded_by_user_id, responded_at
    casts: starts_at/ends_at/responded_at => datetime
    belongsTo: conflictingReservation(OperatorReservation), operatorUser, requestedByUser, respondedByUser
    hasMany: notifications

  OperatorReservationNotification.php
    fillable: operator_reservation_request_id, user_id, type, read_at
    casts: read_at => datetime
    belongsTo: request(OperatorReservationRequest), user
    scopeUnread(): whereNull('read_at')
```

---

## ルート追加（既存 `coordinator/operator-calendar` グループ内）

```php
Route::get('/notifications',  [OperatorCalendarController::class, 'notifications'])->name('notifications.index');
Route::put('/notifications/{operatorReservationNotification}/read', [OperatorCalendarController::class, 'markNotificationRead'])->name('notifications.read');
Route::post('/requests',      [OperatorCalendarController::class, 'storeRequest'])->name('requests.store');
Route::put('/requests/{operatorReservationRequest}/respond', [OperatorCalendarController::class, 'respondRequest'])->name('requests.respond');
```

---

## コントローラー追加（`OperatorCalendarController`）

- `notifications()`: 自分宛の未読通知一覧（関連リクエスト情報付き）を返す
- `markNotificationRead()`: 本人の通知のみ既読化
- `storeRequest()`: バリデーション（`conflicting_reservation_id`必須・時間範囲チェック）→ `pending` で作成 → 対象予約の `reserved_by_user_id` に `request_created` 通知
- `respondRequest()`: `decision: approved|rejected`
  - approved: 既存予約削除 → 新規予約作成 → 同一既存予約への他の pending リクエストを自動 rejected（各申請者に通知）→ 申請者に `request_approved` 通知
  - rejected: ステータス更新のみ → 申請者に `request_rejected` 通知
  - 既に処理済みなら 409

`index()`/`data()` にも `pendingRequestReservationIds`（pending リクエストが紐づく予約ID配列）を追加し、該当ブロックの点滅表示に使う。

---

## Vue 変更（`OperatorCalendar.vue`）

1. **予約作成モーダル**: `formMode==='create'` のとき、選択オペレーター×時間帯と `localReservations` を分ベースで突き合わせて競合を検出（`isoToMinutes()`を再利用、日付文字列比較はしない）。競合があれば警告ブロックを表示し、ボタンを「そのまま保存」／「リクエストを送る」の2つに分岐。競合なしなら従来通り「保存」ボタンのみ。
2. **通知ベル**: ツールバーに🔔＋未読件数バッジを追加。クリックでドロップダウン表示。
   - `request_created` 型: 申請内容表示＋「承諾」「拒否」ボタン
   - `request_approved`/`request_rejected` 型: 結果表示のみ（クリックで既読）
3. **点滅表示**: `pendingRequestReservationIds` に含まれる予約ブロックに `animate-pulse` ＋ 赤枠を適用。
4. 日付切替（`changeDate`）時、`pendingRequestReservationIds` も併せて再取得する（`data()` レスポンスに含める）。

---

## 変更ファイル一覧

```
database/migrations/
  xxxx_create_operator_reservation_requests_table.php
  xxxx_create_operator_reservation_notifications_table.php

app/Models/
  OperatorReservationRequest.php
  OperatorReservationNotification.php

app/Http/Controllers/Coordinator/OperatorCalendarController.php   （既存修正：4メソッド追加＋index/data拡張）
routes/web.php                                                    （追記：4ルート）
resources/js/Pages/Coordinator/OperatorCalendar.vue               （既存修正：通知ベル・競合検出・点滅表示）
```

**合計: 約7ファイル（新規4・修正3）**

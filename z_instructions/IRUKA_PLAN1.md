# IRUKA_PLAN1.md — イルカ（在席管理）機能 詳細仕様

作成日: 2026-05-15  
更新日: 2026-05-16  
ステータス: Phase 1〜9 完了 / Phase 10 設計中

---

## 概要

社内の在席・ステータス管理機能（通称: イルカ）。  
全ロールのユーザーが互いのステータスをリアルタイム（30秒ポーリング）で確認・変更できる。  
参考: https://iruca.co/rooms/sample

---

## 要件まとめ

| 項目 | 内容 |
|---|---|
| 閲覧権限 | 全ロール・全ユーザーが全員のステータスを閲覧可能 |
| 編集権限 | 全ロール・全ユーザーが全員のステータスを変更可能 |
| 更新方式 | 30秒ポーリング（さくらレンタルサーバーでWebSocketが動かないため） |
| 部署フィルター | 情報出版 / 製版 / オンデマンド / 全部署 のボタン切り替え |
| 他人モーダル | 他人名クリック → ステータスのみ変更可（コメント編集は自分のみ） |
| ダッシュボード | User: カレンダー+イルカタブ切替 / 他ロール: イルカボードに置換 |
| カレンダー連携 | 設計のみ（マッピング後日定義） |
| 退社自動日報 | 退社ステータス設定時・日報未作成ならDiary+WorkRecord自動生成 |
| **在席ボード管理** | **Admin: 全部署 / Leader: 自部署のみ。並び順変更・表示/非表示切替** |

---

## ステータス一覧（Phase 9 更新後・18種）

モーダルのボタン配置は 6行 × 3列。各行はグループカラーで統一（パステル調）。

| 行 | ステータス名 | slug | ボタン色（Tailwind） | 備考 |
|---|---|---|---|---|
| 1 | 在席 | present | green-100 / green-700 | デフォルト |
| 1 | 小台在席 | present_kodai | emerald-100 / emerald-700 | |
| 1 | 退社 | left | gray-100 / gray-600 border-gray-400 | 自動日報トリガー |
| 2 | 会議 | meeting | purple-100 / purple-700 | ※旧"会議・打合せ"を分割 |
| 2 | 打合せ | discussion | violet-100 / violet-700 | 新規slug |
| 2 | 来客対応 | client_reception | indigo-100 / indigo-700 | 新規slug |
| 3 | テレワーク | telework | sky-100 / sky-700 | |
| 3 | 遅刻 | late | amber-100 / amber-700 | |
| 3 | 早退 | early_leave | orange-100 / orange-700 | |
| 4 | 移動中 | moving | cyan-100 / cyan-700 | |
| 4 | 外出 | out | teal-100 / teal-700 | |
| 4 | 外出NR | out_nr | teal-200 / teal-800 | NR=No Return |
| 5 | 有給休暇 | paid_leave | rose-100 / rose-700 | |
| 5 | AM半休 | half_am | pink-100 / pink-700 | |
| 5 | PM半休 | half_pm | fuchsia-100 / fuchsia-700 | |
| 6 | 離席 | away | yellow-100 / yellow-700 | |
| 6 | 電車遅延 | train_delay | amber-100 / amber-800 | |
| 6 | 特別休暇 | special_leave | red-100 / red-700 | |

**注意:** `meeting` スラッグは既存DBの `status = 'meeting'` との互換性のため維持。旧「会議・打合せ」ラベルは「会議」に変更。

---

## DB設計

### 新規テーブル: `user_presence_statuses`

```sql
CREATE TABLE user_presence_statuses (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NOT NULL UNIQUE,  -- 1ユーザー1行
    status          VARCHAR(50)     NOT NULL DEFAULT 'present',
    comment         VARCHAR(200)    NULL,
    updated_by_id   BIGINT UNSIGNED NULL,             -- 誰が変更したか
    status_source   VARCHAR(20)     NULL DEFAULT 'manual', -- 'manual' or 'calendar'
    sort_order      INT             NOT NULL DEFAULT 0,  -- ボード表示順（追加）
    is_hidden       TINYINT(1)      NOT NULL DEFAULT 0,  -- ボード非表示フラグ（追加）
    created_at      TIMESTAMP       NULL,
    updated_at      TIMESTAMP       NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by_id) REFERENCES users(id) ON DELETE SET NULL
);
```

- 1ユーザーにつき1行（`updateOrCreate`）
- `updated_at` = 最終操作時間として表示
- `status_source = 'calendar'` = カレンダー連携で自動変更されたことを示す
- `sort_order` = 在席ボードでの表示順（小さいほど先頭）
- `is_hidden = 1` = 在席ボードに表示しない（登録済みだが非表示）

---

## 画面仕様

### ① ヘッダー（全ページ共通）

AppLayout の通知ベル左に配置:
```
[🐬] [自分のステータス名]
```

- 🐬 アイコン: Heroicons の `beaker` or カスタム絵文字（🐬）
- ステータス名をクリック or アイコンクリック → 自分用モーダルを開く

### ② ステータス更新モーダル（IrukaStatusModal.vue）

画像の通りのレイアウト:
- お名前フィールド（自分の場合のみ表示・編集ボタン付き）
- ひとこと欄（自分の場合のみ編集可・クリアボタン付き）
- ステータスボタン16個（4列グリッド）
- 削除する / キャンセル ボタン

他人モーダル:
- お名前表示のみ（編集不可）
- ひとこと欄は読み取り専用（編集ボタン・クリアボタンなし）
- ステータスボタンのみ操作可

### ③ イルカボード（IrukaBoard.vue）

**Phase 10 仕様（2026-05-16 更新）:**
- 部署タブ: 各部署を個別タブで切り替え。「全部署」タブなし。
- レイアウト: 縦リスト1行形式（旧カードグリッド廃止）
  - `[名前] [ひとことタグ] ⋯ [●ステータス] [最終操作時間]`
- グループ分け:
  - 上段「出社中」: 退社/有給/特別休暇/早退 以外のステータス
  - 下段「退社・休暇」: `left`, `paid_leave`, `special_leave`, `early_leave`（薄表示）
- デフォルトステータス: `present` → `left`（操作なしは退社扱い）
  ※デフォルト変更は API 返却値の変更のみ。`maybeAutoCreateDiary()` は呼ばない。
- 部署名をカードから削除（タブで部署が分かるため）
- 30秒ごとに自動ポーリング（`setInterval` + axios）
- `is_hidden = true` のユーザーは表示しない
- `sort_order` 順で表示（同値は名前順）

### ④ 在席ボード管理（IrukaBoardSettings.vue）

**アクセス権限:**
| ロール | 管理対象 |
|---|---|
| Admin | 全部署の全ユーザー |
| Leader | Teamテーブルで `leader_id = 自分` かつ `team_type = 'department'` の部署に属するユーザーのみ |

**管理画面の機能:**
- 部署別ユーザー一覧（テーブル形式）
- 各行: ユーザー名 / 部署 / 表示/非表示トグル / ↑↓ 並び順ボタン
- 保存ボタン（一括保存）

**アクセス方法:**
- Admin ダッシュボードに「在席ボード管理」ボタン
- Leader ダッシュボードに「在席ボード管理」ボタン（自部署のみ）

**ルート:**
```
GET  /presence/board-settings        → PresenceBoardSettingsController@index
POST /presence/board-settings        → PresenceBoardSettingsController@update
```

---

## カレンダー連携設計（Phase 9B）

### 対象イベント

ユーザーは `Event` モデルを通じて以下の予定を登録できる:
- **案件打合せ・外出**: `event_item_type_id` で種別指定（`event_item_types` テーブル参照）
- **社内予定**: 同様に `event_item_type_id` で種別指定

### EventItemType → presence status マッピング

DB調査（2026-05-16）で確認した `event_item_types` テーブルの全レコード:

| slug | 名前 | 自動セット先ステータス |
|---|---|---|
| `conference` | 会議 | `meeting`（会議） |
| `meeting_internal` | 打合せ（社内） | `discussion`（打合せ） |
| `meeting_client` | 打合せ（顧客） | `discussion`（打合せ） |
| `customer_visit` | 顧客訪問 | `out`（外出） |
| `client_visit` | 来社対応 | `client_reception`（来客対応） |
| `outing` | 外出 | `out`（外出） |
| `other` | そのほか | 自動セットなし（手動選択） |

有給・テレワークはカレンダーEventに対応する種別がないため、ユーザーが手動で選択する。

### 実装方針

**タイミング:** `UserPresenceController::index()`（30秒ポーリング）のレスポンス生成時に、**自分自身**の現在進行中イベントを確認して自動更新する。

```
GET /presence  を呼ぶたびに:
  → 認証ユーザーの現時刻が start ≤ now ≤ end の Event を1件取得
  → イベントがある かつ status_source ≠ 'manual':
       status を マッピングに従い更新 (status_source = 'calendar')
  → イベントが終了 かつ status_source = 'calendar':
       status = 'present' に自動リセット
```

**手動優先:** ユーザーが手動でステータスを変えた瞬間に `status_source = 'manual'` を記録し、その後はカレンダーが上書きしない。カレンダーイベントが新たに開始したタイミングで再び自動セット（手動→カレンダー上書きOK）。

**実装クラス:** `UserPresenceController` に `private function syncCalendarStatus(User $user): void` を追加。`index()` の冒頭で呼ぶ。

```php
private function syncCalendarStatus(User $user): void
{
    $now = now();
    $event = Event::where('user_id', $user->id)
        ->whereNotNull('event_item_type_id')
        ->where('starts_at', '<=', $now)
        ->where('ends_at', '>=', $now)
        ->with('eventItemType')
        ->latest('starts_at')
        ->first();

    $presence = $user->presenceStatus;

    if (!$event) {
        // イベントなし + calendar ソースなら present に戻す
        if ($presence && $presence->status_source === 'calendar') {
            $presence->update(['status' => 'present', 'status_source' => 'manual']);
        }
        return;
    }

    static $map = [
        'conference'       => 'meeting',
        'meeting_internal' => 'discussion',
        'meeting_client'   => 'discussion',
        'customer_visit'   => 'out',
        'client_visit'     => 'client_reception',
        'outing'           => 'out',
    ];

    $slug = $event->eventItemType?->slug;
    $targetStatus = $map[$slug] ?? null;

    if (!$targetStatus) return;

    // 手動変更は上書きしない（ただしイベント開始後に手動変更した場合のみ保護）
    // status_source = 'calendar' または まだ変更されていない場合のみ更新
    if (!$presence || $presence->status_source !== 'manual') {
        UserPresenceStatus::updateOrCreate(
            ['user_id' => $user->id],
            ['status' => $targetStatus, 'status_source' => 'calendar']
        );
    }
}
```

**注意事項:**
- `events.starts_at` / `ends_at` の JST/UTC 混在問題（`CalculatesEventTime` トレイト参照）。カレンダー連携実装時は proof 以外のイベントは JST 直接保存のため `Carbon::parse()` で問題ない。
- `event_item_type_id` が NULL の予定（ジョブイベント等）は対象外。

---

## 退社時自動日報作成

**トリガー:** `status = 'left'`（退社）に変更されたとき

**処理フロー:**
```
1. 対象ユーザーの当日 Diary を確認
   → 既存あり: 何もしない
   → 既存なし:
       2a. user_monthly_schedules の当日 JSON から worktype_id を取得
       2b. worktypes テーブルから start_time を取得
       2c. Diary を content='' で作成 (user_id, date, content='')
       2d. WorkRecord を updateOrCreate (既存work_recordなし時のみ):
           - start_time = worktype.start_time
           - end_time   = 現在時刻 (退社ボタン押下時刻)
           - scheduled_start = worktype.start_time
           - scheduled_end   = worktype.end_time
           - overtime_minutes = 計算
```

**user_monthly_schedules JSON 解釈:**
- `{"日": worktype_id}` 形式（例: `{"15": 1}` → 当日は worktype_id=1 = A日程）
- 対象日のデータがない場合 → company のデフォルト worktype（sort_order=1）を使用

**注意:** 自動作成した日報はユーザーが後から編集できる。`diaries/edit` で通常どおり修正可能。

---

## 変更ファイル一覧

### 新規作成

| ファイル | 役割 |
|---|---|
| `database/migrations/xxxx_create_user_presence_statuses_table.php` | テーブル作成 |
| `app/Models/UserPresenceStatus.php` | Eloquentモデル |
| `app/Http/Controllers/UserPresenceController.php` | API: 一覧取得・更新 |
| `resources/js/Components/Iruka/IrukaStatusModal.vue` | ステータス更新モーダル |
| `resources/js/Components/Iruka/IrukaBoard.vue` | イルカボード本体 |
| `resources/js/Components/Iruka/IrukaStatusBadge.vue` | ヘッダー用バッジ |

### 変更

| ファイル | 変更内容 |
|---|---|
| `routes/web.php` | presence ルート追加 |
| `resources/js/layouts/AppLayout.vue` | ヘッダーにイルカバッジ追加 |
| `resources/js/Pages/Dashboard.vue` | カレンダー+イルカ タブ切替 |
| `resources/js/Pages/Admin/Dashboard.vue` | イルカボードに置換 |
| `resources/js/Pages/Coordinator/Dashboard.vue` | イルカボードに置換 |
| `resources/js/Pages/Leader/Dashboard.vue` | イルカボードに置換 |
| `resources/js/Pages/Clerk/Dashboard.vue` | イルカボードに置換 |
| `resources/js/Pages/SuperAdmin/Dashboard.vue` | イルカボードに置換 |
| `resources/js/Pages/Prepress/Dashboard.vue` | イルカボードに置換 |
| `app/Http/Controllers/DiaryController.php` | autoCheckout メソッド参照 |

---

## フェーズ別タスク

### Phase 1: DB・バックエンド
- [ ] migration 作成・実行
- [ ] UserPresenceStatus モデル作成
- [ ] UserPresenceController 作成（index / update / autoCheckout）
- [ ] routes/web.php にルート追加

### Phase 2: ヘッダー統合
- [ ] IrukaStatusBadge.vue 作成（ヘッダー用）
- [ ] IrukaStatusModal.vue 作成（モーダル本体）
- [ ] AppLayout.vue にバッジ組み込み
- [ ] 自分のステータス取得・表示確認

### Phase 3: イルカボード
- [ ] IrukaBoard.vue 作成
- [ ] 部署フィルター実装
- [ ] 30秒ポーリング実装
- [ ] 他人モーダル（ステータス変更のみ）動作確認

### Phase 4: ダッシュボード統合
- [ ] Dashboard.vue (User): カレンダー+イルカ タブ切替（localStorage保存）
- [ ] Admin/Coordinator/Leader/Clerk/SuperAdmin/Prepress の各Dashboard.vue: イルカボードに置換

### Phase 5: 退社時自動日報
- [ ] UserPresenceController に autoCheckout ロジック追加
- [ ] user_monthly_schedules から当日 worktype 取得
- [ ] Diary + WorkRecord 自動作成

### Phase 6: カレンダー連携（設計のみ・実装保留）
- [x] status_source フラグ設計済み → Phase 9B で実装

### Phase 7: 在席ボード管理機能 ✅完了
- [x] migration: sort_order / is_hidden カラム追加
- [x] PresenceBoardSettingsController 作成
- [x] routes/web.php にルート追加
- [x] IrukaBoard.vue: is_hidden フィルタ・sort_order 対応
- [x] IrukaBoardSettings.vue 作成（管理画面）
- [x] Admin/Leader タブメニューに「在席ボード管理」追加

### Phase 8: ボード設定UI改善 ✅完了 (2026-05-16)
- [x] LAYOUT_SPEC_V2 準拠（戻るボタン・max-w-2xl・indigo保存）
- [x] Admin: 部署フィルターボタン追加
- [x] 全 Leader 対応（department_id ベース）
- [x] テーブル固定幅・中央寄せ・▲▼常時表示

### Phase 9A: ステータスUI刷新
- [ ] `statusConfig.js` を18ステータスに更新（会議・打合せ分割）
  - `meeting`: 会議（旧 "会議・打合せ" ラベル変更のみ）
  - `discussion`: 打合せ（新規スラッグ追加）
  - `client_reception`: 来客対応（新規スラッグ追加）
- [ ] `IrukaStatusModal.vue`: 6行3列レイアウト・パステル色ボタン・「・」削除
- [ ] `IrukaStatusBadge.vue`: dot/テキストカラー更新

### Phase 9B: カレンダー連携実装
- [ ] `UserPresenceController::syncCalendarStatus()` 実装
- [ ] `index()` 冒頭で自分のイベントチェック・自動ステータス更新
- [ ] EventItemType → status マッピング（上記テーブル参照）
- [ ] `Event` モデルへの `eventItemType` リレーション確認

### Phase 9C: ボード設定にステータス順序管理を追加
- [ ] 新テーブル `iruka_status_orders`（company_id, slug, sort_order, is_active）
  - デフォルト18スラッグをシーダーで投入
- [ ] `IrukaStatusOrder` モデル作成
- [ ] `PresenceBoardSettingsController` に `statusIndex()` / `statusUpdate()` 追加
- [ ] ルート追加: `GET/POST /presence/board-settings/statuses`
- [ ] `BoardSettings.vue` に「ステータス管理」タブを追加
  - ▲▼並び替え + 表示/非表示トグル（ユーザー管理と同様のUI）
- [ ] `IrukaStatusModal.vue`: statusConfig.js を廃止し、Inertia props または `/presence` レスポンスからステータス一覧を受け取る

---

## 備考

- さくら本番では WebSocket（Reverb）が動かないため、ポーリング方式を採用
- イルカという名称はユーザーが使う通称。システム内の正式名称は未定（変数名・クラス名は `Presence` or `UserPresence` で統一）
- 部署データ: departments テーブル（情報出版/製版/オンデマンドの3件。`users.department_id` で紐づき）

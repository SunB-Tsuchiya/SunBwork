# ACTUALCOPY_PLAN1.md — カレンダー/スケジュール分離（会議の実績記録機能）詳細設計

最終更新: 2026-07-03
ステータス: 設計確定・実装未着手

---

## 背景・課題

- **カレンダー**（`/calendar`）＝ 自分の1日の作業タイムテーブルを記録するもの。本人が自由に修正できるべき。
- **スケジュール**（`/schedule`）＝ 会議予定などを扱うもの。他人から招待されたり、自分の空き時間を見せたりする。
- 両者は同一の `events` テーブルの同一行を共有しており、招待された側（attendee）は `EventPolicy` / `ScheduleEventController::authorizeEdit()` の判定（`event.user_id === 自分` のみ許可）により**編集・削除が一切できない**。
- そのため「会議が予定より早く終わり、残り時間を自分の作業として記録したい」といったケースに対応できず、主催者が変更しない限りカレンダー上の表示がそのまま残ってしまう。
- ユーザー報告の具体事例（user_id=1, 2026/07/02, 13:30の会議）は本番DB調査の結果、**現在は該当イベントが存在せず、DB上の孤児レコードや削除漏れはなかった**。フロント側にスケジュール変更をリアルタイムに反映する仕組み（Echo/WebSocket・ポーリング）が無いため、画面を開きっぱなしにしていたことによる表示の遅延だった可能性が高い（本設計の対象外・別途「今後の検討事項」に記載）。

## 決定した設計方針

**個人実績コピー方式**：招待された会議について、ユーザーが明示的に「実績として記録する」ボタンを押した時点で、その会議のタイトル・時刻をコピーした**自分名義の新規 `events` 行**を作成する。以後はそのコピーを通常のカレンダー予定として自由に編集・削除できる。元のスケジュール側イベントが後で変更・削除されても、既に作成したコピーには一切影響しない。

- 生成トリガー：**手動ボタンのみ**（自動生成・cron等は行わない）
- 工数分析（`WorkloadAnalyzerController`）は `Event::where('user_id', $userId)` で通常のイベントを拾う実装のため、コピーは自分名義の通常イベントとして自然にカウントされる（**工数分析側のコード改修は不要**）
- 日報（Diaries）は現状 Event を直接参照していないため影響なし

---

## DB設計

### `events` テーブルに追加（マイグレーション1本）

| カラム | 型 | 説明 |
|---|---|---|
| `source_schedule_event_id` | unsignedBigInteger, nullable | このイベントがどの元スケジュール会議（他人主催）から複製された「自分の実績コピー」かを示す参照。FK → `events.id`、`onDelete('set null')`（マスター削除時もコピー側は消えない） |

- unique制約: `(user_id, source_schedule_event_id)` — 同じ会議に対する重複コピー生成を防止
- 既存の `events` テーブルの他カラム（`is_company_event`, `visibility` 等）はコピー生成時に以下のデフォルトで設定:
  - `user_id` = 自分
  - `is_company_event` = false（個人予定として扱う。会社全体への公開は不要）
  - `visibility` = 'private'
  - `title` / `starts_at` / `ends_at` / `body` / `event_item_type_id` = 元イベントの値をコピー
  - `source_schedule_event_id` = 元イベントの id

### Event モデル (`app/Models/Event.php`)

- `sourceScheduleEvent()`: `belongsTo(Event::class, 'source_schedule_event_id')`
- `personalCopies()`: `hasMany(Event::class, 'source_schedule_event_id')`

---

## 表示ロジックの変更（重複表示の防止）

`app/Http/Controllers/Schedule/ScheduleEventController.php::range()` の「参加者として招待されたイベント」セクション（142〜164行目）で、**既に自分がそのイベントの実績コピーを作成済みの場合は、招待イベント側の表示を除外する**必要がある。

- 実装イメージ: `$myMaterializedSourceIds = Event::where('user_id', $user->id)->whereNotNull('source_schedule_event_id')->pluck('source_schedule_event_id')`
- `attendeeEvents` 抽出時に `whereNotIn('id', $myMaterializedSourceIds)` を追加
- これにより、コピー未作成の会議は従来通り「招待イベント」として表示（編集不可）、コピー作成後はコピー（自分名義・編集可）のみが表示される

---

## API設計

### 新規エンドポイント

`POST /schedule/events/{event}/materialize`（ルート名: `schedule.events.materialize`）

- 認可: 自分がその event の `schedule_attendees` に行を持つこと（招待されていること）。オーナー自身への materialize は不要（既に own のため 422 で弾く）
- 処理: `event`（マスター）の値を元に新規 `Event::create()`（上記デフォルト値）
- 既に同じ `source_schedule_event_id` でコピー済みなら 422（重複防止）
- レスポンス: 作成した personal copy の event データ（`is_own: true` を付与）

`app/Http/Controllers/Schedule/ScheduleEventController.php` に `materialize()` メソッドを追加。

### ルート追加 (`routes/web.php`)

```php
Route::post('/events/{event}/materialize', [ScheduleEventController::class, 'materialize'])
    ->name('schedule.events.materialize');
```

---

## フロントエンド変更

### `resources/js/Components/Schedule/EventDetailModal.vue`

- `!event.is_own` かつ `!event.source_schedule_event_id`（＝招待イベントでまだコピー未作成）の場合に「実績として記録する」ボタンを表示
- ボタンの隣に「？」アイコン（ホバー/タップでツールチップ表示、`AppLayout.vue` の既存ツールチップパターン `absolute ... opacity-0 group-hover:opacity-100` を踏襲）を設置し、以下の説明文を表示する:
  「この会議の内容をコピーして、あなた専用の予定として複製します。複製後は時刻や内容を自由に編集・削除でき、元の会議が変更・削除されても複製には影響しません。」
- クリックで `schedule.events.materialize` を POST → 成功したら `emit('responded')` 相当で親（`UserCalendar.vue`）に再読み込みさせる

### `resources/js/Components/Calendar/UserCalendar.vue`

- `EventDetailModal` からの新規イベント（`@materialized` 等）を受けて `loadEvents()` を呼び直す（既存の `onSaved`/`onDeleted` と同様のパターンで実装可能、大きな変更は不要）

---

## 影響ファイル一覧

| ファイル | 変更内容 |
|---|---|
| `database/migrations/xxxx_add_source_schedule_event_id_to_events_table.php`（新規） | `source_schedule_event_id` カラム追加 |
| `app/Models/Event.php` | リレーション2つ追加 |
| `app/Http/Controllers/Schedule/ScheduleEventController.php` | `materialize()` 追加、`range()` の重複除外ロジック追加 |
| `routes/web.php` | 新規ルート追加 |
| `resources/js/Components/Schedule/EventDetailModal.vue` | 「実績として記録する」ボタン追加 |
| `resources/js/Components/Calendar/UserCalendar.vue` | materialize後の再読み込みハンドラ追加 |

---

## 今後の検討事項（今回のスコープ外）

- スケジュール側の変更・削除をカレンダー画面にリアルタイム反映する仕組み（Echo/WebSocket、もしくはポーリング/フォーカス時リフェッチ）が現状無い。今回の実装で「コピー済みなら無関係」という状態にはなるが、**コピー未作成の招待イベントが変更・削除された場合の画面反映漏れ**は引き続き起こり得る。別途対応を検討する場合は本ドキュメントを更新すること。

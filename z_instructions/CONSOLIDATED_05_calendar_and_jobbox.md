# カレンダー・JobBox・イベント（統合）
最終更新: 2026-08-13

---

## UTC / JST 混在ルール（重要）

`events.starts_at / ends_at` の保存形式が2種類ある:

| イベント種別 | 保存形式 | 読み出し時の注意 |
|---|---|---|
| 通常イベント（社内予定・外出・client-event・internal-event 等） | **JST 文字列**をそのまま保存 | `Carbon::parse($v)` で JST として扱える |
| 校正ジョブイベント（`job_type='proof'`） | **UTC 文字列**で保存 | そのまま parse すると 9 時間ずれる |

**必ずこのルールに従うこと:**

```php
// NG: proof イベントで 9 時間ずれる
$start = Carbon::parse($event->starts_at);

// OK: JST Carbon を返す
$event->load('projectJobAssignment:id,job_type');
$start = $this->resolveJstCarbon($event, 'starts_at');
```

**`CalculatesEventTime` Trait:**
- パス: `app/Http/Controllers/Concerns/CalculatesEventTime.php`
- メソッド: `resolveJstCarbon($event, 'starts_at')` — JST の Carbon を返す
- メソッド: `computeLunchMinutes($start, $end, $userId, $cache)` — 昼休憩分計算（UserMonthlyBreak → UserSetting → デフォルト 12:00–13:00 優先順）
- 使い方: コントローラで `use CalculatesEventTime;` を宣言し、`projectJobAssignment:id,job_type` を eager load してから呼ぶ

### 書き込み・期間フィルタ・Vue の日付（2026-08-13 追加）

読み出しだけルールを守っても意味がない。**書き込み側・期間フィルタ側も同じ規則に従うこと。**

| 場面 | 使うもの |
|---|---|
| **書き込み**（JST 日時 → 保存値） | `toEventStorageString($event, $jstDateTime)` |
| 読み出し（保存値 → JST） | `resolveJstCarbon($event, $field)` |
| 生値（退避した旧値など）→ JST | `rawToJstCarbon($event, $raw)` |
| 保存 TZ の判定 | `eventStorageTimezone($event)` |
| 重複時間の再計算 | `recalcInterruptionMinutes($event, $oldStart, $oldEnd)` |

- **書き込み**: `$event->start = $date.' '.$h.':'.$m` のような直接代入は proof で 9 時間ずれ、保存のたびに累積する。
  新規作成時は `project_job_assignment_id` をセットしてから変換すること（未保存モデルは `setRelation()` で渡す）
- **期間フィルタ**: DB の文字列比較だけで絞らない。**±9 時間のバッファで取得 → `resolveJstCarbon()` で JST 判定**。
  日境界は JST のまま持つこと（UTC 化して比較すると通常イベントの JST 15:00 以降が当日から消える）。
  模範実装は `CalendarEventsController::range()`
- **カレンダーのドラッグ**: `events` だけ更新するとジョブ修正ページで古い時刻が復元される。
  `project_job_assignments.start_time`（開始）も同期する。`desired_time`（＝自己割当では作業終了時刻、
  Coordinator 割当では締め切り時刻）の同期は**自己割当のときのみ**
- **Vue の日付**: 「今日」や日付移動は `toLocaleDateString('sv-SE')` を使う。
  ただし allDay の ±1 日計算（日付のみ入力で UTC 一貫）と、`+09:00` を明示した `toISOString()` は**正しいので変更しない**。
  一括置換は禁止、1 箇所ずつ用途を確認すること

詳細と NG/OK 例は `CLAUDE.md`「UTC / JST 混在ルール ④〜⑦」を参照。

---

## FullCalendar 注意点

- Vue の reactive Proxy をそのまま渡すと空になる等の問題が発生する → `structuredClone` などで plain オブジェクトを渡す
- `JobBox 側の "予定を編集" からカレンダーを開く際は URL に `?date=YYYY-MM-DD&user_id=...` を付与し、Calendar 側はそのパラメータを受け取って `gotoDate` を呼ぶ

---

## TimelineDiary コンポーネント（R5-14 / R5-16, 2026-05-23）

**パス:** `resources/js/Components/TimelineDiary.vue`

**Props:**
- `:date` — 表示日（YYYY-MM-DD）
- `:events` — イベント配列
- `:editable` — 編集可否（true でドラッグ/リサイズ有効）

**編集モード（`:editable="true"`）のイベント:**

| emit | 発火タイミング | ペイロード |
|------|-------------|-----------|
| `@update:events` | ドラッグ/リサイズ完了 | `{ id, start, end, date }` |
| `@open-edit` | イベントクリック | `{ id, date }` |
| `@open-create` | 空スペースクリック | `{ date, hour }` |

**`@update:events` の処理パターン:**

```js
async function onTimelineUpdate(payload) {
  await axios.put(`/events/${payload.id}/calendar`, {
    starts_at: payload.start,
    ends_at: payload.end,
  });
  await fetchDayEvents(payload.date);
}
```

**ルート:** `PUT /events/{event}/calendar` → `events.update_from_calendar`

**Edit.vue での日付変更対応（`watch`）:**

```js
watch(() => form.date, (newDate) => {
  if (newDate) fetchDayEvents(newDate);
});
```

**その他:**
- `ResizeObserver` でラッパー幅をリアクティブ取得。`minWidth` をピクセルで強制しない（水平スクロールが発生する）
- 夜勤モード: `defaultWorktype.start_time >= 16:00` の場合 `slotMaxTime: '30:00:00'`（翌6時）

---

## イベント種別（event_item_types）

| 種別 | job_type 相当 | 保存形式 |
|------|-------------|---------|
| 通常予定・外出等 | `internal-event` / `client-event` 等 | JST |
| 校正ジョブ | `proof` | UTC |

---

## JobBox / MyJobBox

- JobBox 側の完了ルート: `jobbox.assignments.complete`
- MyJobBox 側の完了ルート: `myjobbox.assignments.complete`
- `JobBox 側データソース: `job_assignment_messages` JOIN
- MyJobBox データソース: `project_job_assignments` の `selfAssigned()` スコープ

---

## 校正予約（2026-06-30）

- Coordinator 案件一覧から案件に紐づく校正予約を送信する。
- 通常校正依頼の `proof_requests` とは分離し、`proof_reservations` を正規データとする。
- 依頼予定と締め切りは、それぞれ `datetime` または自由記述 `text` を選択できる。
- 日時入力はJSTとして受け取り、`requested_at` / `deadline_at` にUTC文字列で保存する。
- 両方が日時入力かつ依頼予定 < 締め切りの場合のみ、予約詳細から校正カレンダーへ登録できる。
- 登録状態は `calendar_registered_at` で管理する。`proof_schedules` は校正員別の日次作業枠なので予約期間の保存には流用しない。
- 校正カレンダー月表示では、依頼予定日を開始、締め切り日を終了とする1本の期間ストリップを表示する。
- FullCalendar の all-day `end` は排他的なので、締め切り日の翌日を描画用 `end` として渡す。
- 期間ストリップをクリックすると `proof_coordinator.reservations.show` へ遷移する。
- 予約モーダルの「送信予約一覧」は、同じ案件に送信済みの予約を依頼予定・締め切り・カレンダー登録状態とともに表示する。
- 同じ案件で「タイトルが一致」または「依頼予定日と締め切り日の両方が一致（時間は無視）」する予約を重複候補とする。
- 重複確認は事前確認APIだけでなく保存処理でも再判定し、`duplicate_confirmed=true` の場合のみ重複候補を送信できる。
- 予約ステータスは `reserved`（予約受付）/ `in_progress`（校正中）/ `completed`（完了）/ `deleted`（削除）の4状態。
- 削除は履歴を保つ論理状態であり、レコードを物理削除しない。削除状態の予約は校正カレンダーに表示しない。
- 予約一覧の「完了を表示しない」はデフォルトONで、`sbw_proof_reservations_hide_completed` としてlocalStorageへ保存する。
- proof-admin の受信箱・校正予約一覧・ジョブ管理は、`created_at` を基準に新しい順/古い順を切り替える。指定がない場合は新しい順（`desc`）。
- 検索・年月・タブ切替時も `sort_order` をクエリへ引き継ぐ。

---

## 管理シートテンプレート（2026-06-30）

- 管理シート用テンプレートの正規データは `progress_templates` の `sheet_type = management`
- 専用画面: `/coordinator/management-templates`
- Controller: `Coordinator/ManagementTemplateController`
- Inertiaページ: `Coordinator/ManagementTemplates/Index.vue`, `Edit.vue`
- 一覧には共有テンプレート、またはログインユーザー自身が作成したテンプレートを表示
- 編集・削除は作成者、Admin、SuperAdminのみ可能
- 管理シート作成時は `column_config` をコピーし、`workflow_sheets.template_id` には保存しない
  - `workflow_sheets.template_id` は旧 `workflow_templates` への外部キーであり、`progress_templates.id` を保存してはいけない
- 旧 `WorkflowTemplate` / `workflow_templates` は `stage_config` 形式の別系統。互換性維持のため残すが、新しい管理シートテンプレート機能には接続しない
- `sheet_type = null` または `progress` は進行管理表側、`management` は管理シート側として一覧と作成モーダルを分離する

---

## CSV インポート（NormalizesCsvEncoding Trait）

**全 CSV インポートは Shift-JIS + CRLF + BOM に対応すること。**

- Trait パス: `app/Http/Controllers/Concerns/NormalizesCsvEncoding.php`
- 各コントローラで `use NormalizesCsvEncoding;` を宣言

| メソッド | 用途 |
|---------|------|
| `$this->normalizeCsvStoredFile($storagePath)` | store() で保存済みファイルを正規化（上書き） |
| `$this->normalizeCsvToTemp($file)` | UploadedFile を正規化した一時ファイルパスを返す（使用後 `@unlink` 必須） |
| `$this->normalizeCsvContent($raw)` | バイト列を UTF-8 文字列として返す |

---

## EventController

- `EventController::complete()`: `project_job_assignments.completed` のみ更新
- `EventController::store()`: `job_id` の有無に応じて `ProjectJobAssignment` を参照し、必要なら通知 Message を作成
- パンくず: `Events/Show.vue` の `events.project_job_assignment_id` は `project_job_assignments` への FK

---

## ルーティング

SPA ルートは必ず `routes/web.php` に置く（`routes/api.php` は StartSession が通らず SPA 認証が失敗する）。

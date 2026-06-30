# カレンダー・JobBox・イベント（統合）
最終更新: 2026-05-23

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

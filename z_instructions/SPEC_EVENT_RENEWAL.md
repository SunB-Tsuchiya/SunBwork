# イベント予定機能 リニューアル仕様書
作成日: 2026-05-02

---

## 目次

1. [概要・目的](#1-概要目的)
2. [影響ファイル一覧](#2-影響ファイル一覧)
3. [DB設計（マイグレーション）](#3-db設計マイグレーション)
4. [バックエンド設計](#4-バックエンド設計)
5. [フロントエンド設計](#5-フロントエンド設計)
6. [Leader / Admin 会議設定機能](#6-leader--admin-会議設定機能)
7. [ルーティング設計](#7-ルーティング設計)
8. [レイアウト基準](#8-レイアウト基準)
9. [作業フロー](#9-作業フロー)

---

## 1. 概要・目的

### 背景

従来の「予定作成」ボタン1つを廃止し、以下の2種類に分割する。

| 新ボタン名 | 用途 | 旧対応 |
|---|---|---|
| 案件打合せ・外出 | クライアント・案件に紐づく予定（顧客訪問・来社応対・外出） | 予定作成 |
| 社内予定 | 案件と無関係な社内の打合せ・会議・その他 | 予定作成 |

ボタンの追加・ラベル変更は**実装済み**（2026-05-02）。今回は**フォーム・DB・設定機能**の実装を行う。

### 既存リソースの活用方針

- `Events/Create.vue`（既存）：現行の予定作成フォーム。今後は **社内予定** フォームの土台として流用。
- `AssignmentForm.vue`：ジョブ割当用の複雑なコンポーネント。**直接複製しない**。必要な UI パターン（チームメンバー選択モーダルなど）を参考に、独立したコンポーネントとして新規作成。
- `event_item_types` テーブル：種類マスタとして既存データを活用。`来社応対` slug を追加する。

---

## 2. 影響ファイル一覧

### 新規作成

```
database/migrations/
  xxxx_add_project_job_id_to_events_table.php         # events に project_job_id FK 追加
  xxxx_add_client_name_to_events_table.php            # events に client_name（外出先代替）追加
  xxxx_create_meeting_definitions_table.php           # 会議定義テーブル
  xxxx_create_meeting_definition_members_table.php    # 会議定義メンバー中間テーブル

app/Models/
  MeetingDefinition.php
  MeetingDefinitionMember.php

app/Http/Controllers/
  Events/ClientEventController.php    # 案件打合せ・外出
  Events/InternalEventController.php  # 社内予定
  Leader/MeetingDefinitionController.php
  Admin/MeetingDefinitionController.php

resources/js/Pages/Events/
  CreateClientEvent.vue     # 案件打合せ・外出フォームページ
  CreateInternalEvent.vue   # 社内予定フォームページ

resources/js/Pages/Leader/
  MeetingDefinitions/Index.vue
  MeetingDefinitions/Create.vue
  MeetingDefinitions/Edit.vue

resources/js/Pages/Admin/
  MeetingDefinitions/Index.vue   # Admin用（Leaderと同じ構成、対象メンバーが全社員）
  MeetingDefinitions/Create.vue
  MeetingDefinitions/Edit.vue
```

### 変更

```
app/Models/Event.php                    # fillable に project_job_id / client_name 追加
resources/js/Components/Calendar.vue    # 案件打合せ・外出ボタン → CreateClientEvent へ遷移
                                        # 社内予定ボタン → CreateInternalEvent へ遷移
                                        # 日付クリックモーダルも同様
resources/js/Pages/Diaries/Show.vue     # 同上
resources/js/Components/Tabs/LeaderNavigationTabs.vue   # 会議設定タブ追加
resources/js/Components/Tabs/AdminNavigationTabs.vue    # 会議設定タブ追加
routes/web.php                          # 新規ルート追加
event_item_types シーダー or マイグレーション  # 「来社応対」追加
```

---

## 3. DB設計（マイグレーション）

### 3-1. events テーブルへのカラム追加

```php
// xxxx_add_columns_to_events_table.php
Schema::table('events', function (Blueprint $table) {
    // 案件連携（顧客訪問・来社応対用）
    $table->unsignedBigInteger('project_job_id')->nullable()->after('user_id');
    $table->foreign('project_job_id')->references('id')->on('project_jobs')->nullOnDelete();

    // 外出先（外出選択時のみ使用。nullable）
    $table->string('destination')->nullable()->after('project_job_id');
});
```

> **設計方針:** 中間テーブルではなく `events.project_job_id` の直接FK。  
> 理由: 1イベントにつき1案件の関係であり、中間テーブルより単純で十分。

### 3-2. meeting_definitions テーブル

```php
Schema::create('meeting_definitions', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('created_by')->comment('作成者 user_id');
    $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
    $table->string('title');
    $table->text('description')->nullable();
    // スケジュール
    $table->enum('recurrence', ['weekly', 'biweekly', 'monthly'])->comment('毎週/隔週/毎月');
    $table->unsignedTinyInteger('day_of_week')->comment('0=日〜6=土');
    $table->time('start_time');
    $table->time('end_time');
    $table->timestamps();
});
```

### 3-3. meeting_definition_members テーブル（中間テーブル）

```php
Schema::create('meeting_definition_members', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('meeting_definition_id');
    $table->foreign('meeting_definition_id')->references('id')->on('meeting_definitions')->onDelete('cascade');
    $table->unsignedBigInteger('user_id');
    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
    $table->unique(['meeting_definition_id', 'user_id']);
    $table->timestamps();
});
```

### 3-4. event_item_types への「来社応対」追加

既存マイグレーションには手を加えず、**新規マイグレーションまたは seeder** で追加する:

```php
DB::table('event_item_types')->insertOrIgnore([
    'name'        => '来社応対',
    'slug'        => 'client_visit',
    'coefficient' => 1.000,
    'description' => 'クライアントが来社しての応対・打合せ',
    'sort_order'  => 4,   // 既存の sort_order と重複しないよう調整
    'created_at'  => now(),
    'updated_at'  => now(),
]);
```

---

## 4. バックエンド設計

### 4-1. ClientEventController（案件打合せ・外出）

```
GET  /events/client-event/create          → create()  CreateClientEvent.vue に必要な props を渡す
POST /events/client-event                 → store()   バリデーション・保存
GET  /events/client-event/{event}/edit    → edit()
PUT  /events/client-event/{event}         → update()
```

**create/edit の props:**
- `eventItemTypes`: slug が `client_visit`, `customer_visit`, `outing` の3件
- `clients`: 全クライアント一覧（社名・ID）
- `date`, `startHour`, `startMinute`, `endHour`, `endMinute`: カレンダーから渡す初期値

**store バリデーション:**
```php
$rules = [
    'event_item_type_id' => 'required|exists:event_item_types,id',
    'title'              => 'required|string|max:255',
    'date'               => 'required|date',
    'startHour'          => 'required',
    'startMinute'        => 'required',
    'endHour'            => 'required',
    'endMinute'          => 'required',
    'description'        => 'nullable|string',
    'project_job_id'     => 'nullable|exists:project_jobs,id',
    'destination'        => 'nullable|string|max:255',
];
```

**保存時の注意:**
- `starts_at` / `ends_at` は日付＋時刻を組み合わせて生成
- `project_job_id` は `来社応対` / `顧客訪問` の場合のみ設定（`外出` の場合は null）
- 保存後は `calendar.index` にリダイレクト

### 4-2. InternalEventController（社内予定）

```
GET  /events/internal-event/create        → create()
POST /events/internal-event               → store()
GET  /events/internal-event/{event}/edit  → edit()
PUT  /events/internal-event/{event}       → update()
```

**create/edit の props:**
- `eventItemTypes`: slug が `meeting_internal`, `conference`, `other` の3件
- `meetingDefinitions`: ログインユーザーが参加メンバーとなっている meeting_definitions 一覧

**store バリデーション:**
```php
$rules = [
    'event_item_type_id'    => 'required|exists:event_item_types,id',
    'title'                 => 'required|string|max:255',
    'date'                  => 'required|date',
    'startHour'             => 'required',
    'startMinute'           => 'required',
    'endHour'               => 'required',
    'endMinute'             => 'required',
    'description'           => 'nullable|string',
    'meeting_definition_id' => 'nullable|exists:meeting_definitions,id',
];
```

**注意:** `meeting_definition_id` は参照情報のみ（events テーブルには保存不要。自動入力トリガーとして使うだけ）。

### 4-3. Leader/MeetingDefinitionController

```
GET    /leader/meeting-definitions              → index()   一覧
GET    /leader/meeting-definitions/create       → create()  新規作成フォーム
POST   /leader/meeting-definitions              → store()
GET    /leader/meeting-definitions/{id}/edit    → edit()
PUT    /leader/meeting-definitions/{id}         → update()
DELETE /leader/meeting-definitions/{id}         → destroy()
```

**index の props:**
- `meetingDefinitions`: ログイン Leader が作成した一覧（members をロード）

**store バリデーション:**
```php
$rules = [
    'title'       => 'required|string|max:255',
    'description' => 'nullable|string',
    'recurrence'  => 'required|in:weekly,biweekly,monthly',
    'day_of_week' => 'required|integer|min:0|max:6',
    'start_time'  => 'required|date_format:H:i',
    'end_time'    => 'required|date_format:H:i|after:start_time',
    'members'     => 'required|array|min:1',
    'members.*'   => 'exists:users,id',
];
```

**store 処理:**
```php
$def = MeetingDefinition::create([
    'created_by'   => auth()->id(),
    'title'        => $validated['title'],
    'description'  => $validated['description'],
    'recurrence'   => $validated['recurrence'],
    'day_of_week'  => $validated['day_of_week'],
    'start_time'   => $validated['start_time'],
    'end_time'     => $validated['end_time'],
]);
$def->members()->sync($validated['members']);
```

**メンバーの取得範囲:**
- Leader: 自部署のユーザー一覧（`users` where `department_id` = Leader の部署）
- Admin: 全ユーザー一覧

### 4-4. Admin/MeetingDefinitionController

Leader と同一ロジック。`members` の取得範囲のみ全ユーザーに変更。
Leaderの Controller を継承、または共通 trait 化して重複を避けること。

### 4-5. Event モデルへの追加

```php
// app/Models/Event.php の fillable に追加
'project_job_id',
'destination',

// リレーション追加
public function projectJob(): BelongsTo
{
    return $this->belongsTo(ProjectJob::class);
}
```

---

## 5. フロントエンド設計

### 5-1. CreateClientEvent.vue（案件打合せ・外出）

**ページパス:** `resources/js/Pages/Events/CreateClientEvent.vue`

#### フォームフィールドと表示条件

| フィールド | 必須 | 表示条件 |
|---|---|---|
| 種類（event_item_type_id） | ◎ | 常に表示。`来社応対` / `顧客訪問` / `外出` の3択 |
| クライアント（select） | - | 種類が `来社応対` or `顧客訪問` のとき表示 |
| プロジェクト名（select） | - | クライアント選択後に表示 |
| タイトル（input） | ◎ | 常に表示。自動生成（後述）・手動上書き可 |
| 外出先（input） | - | 種類が `外出` のとき表示（nullable） |
| 概要（textarea） | - | 常に表示 |
| 日付（date picker） | ◎ | 常に表示 |
| 開始時刻（select × 2） | ◎ | 常に表示 |
| 終了時刻（select × 2） | ◎ | 常に表示 |

#### プロジェクトセレクターの選択肢

1. 通常の案件（`project_jobs` から API or props 経由で取得）
2. 「新規案件（仮）」（virtual option, `project_job_id = null` + タイトルで識別）
3. 「その他」（virtual option, `project_job_id = null`）

#### タイトルの自動生成ロジック（Vue 側 computed/watch）

```js
// 種類名 + クライアント名 + 案件名 をアンダーバー区切りで生成
// 例: 顧客訪問_株式会社ABC_パンフレット制作
const autoTitle = computed(() => {
    const typeName = selectedTypeName.value ?? '';
    const clientName = selectedClientName.value ?? '';
    const projectName = selectedProjectName.value ?? '';
    return [typeName, clientName, projectName].filter(Boolean).join('_');
});

// 初回自動セット・種類/クライアント/案件変更時に再生成
// ただし、ユーザーが手動で変更した場合は上書きしない（autoEdited フラグで管理）
```

#### クライアント→プロジェクト連動

- クライアント選択後、そのクライアントに紐づく `project_jobs` を props から絞り込んで表示
- `project_jobs` の一覧は Controller から props として渡す（`{ id, title, client_id }[]`）

#### 分のセレクターは5分刻み

```js
const minuteOptions = ['00','05','10','15','20','25','30','35','40','45','50','55'];
```

---

### 5-2. CreateInternalEvent.vue（社内予定）

**ページパス:** `resources/js/Pages/Events/CreateInternalEvent.vue`

#### フォームフィールドと表示条件

| フィールド | 必須 | 表示条件 |
|---|---|---|
| 種類（event_item_type_id） | ◎ | 常に表示。`打合せ（社内）` / `会議` / `そのほか` の3択 |
| 会議種類（select） | - | 種類が `会議` のとき表示 |
| タイトル（input） | ◎ | 常に表示。会議選択時は自動入力（手動上書き可） |
| 概要（textarea） | - | 常に表示 |
| 日付（date picker） | ◎ | 常に表示 |
| 開始時刻（select × 2） | ◎ | 常に表示 |
| 終了時刻（select × 2） | ◎ | 常に表示 |

#### 会議種類セレクターの選択肢

- Leader / Admin が登録した `meeting_definitions`（ログインユーザーが参加メンバーのもの）
- 末尾に「そのほか（自由入力）」の virtual option を追加

#### 会議選択時の自動入力

```js
// meeting_definitions から選択した際に以下を自動セット（手動上書き可）
form.title = selectedMeeting.title;
form.description = selectedMeeting.description;
form.startHour = selectedMeeting.start_time.split(':')[0];
form.startMinute = selectedMeeting.start_time.split(':')[1];
form.endHour = selectedMeeting.end_time.split(':')[0];
form.endMinute = selectedMeeting.end_time.split(':')[1];
// 日付は recurrence + day_of_week から「現在日時以降の最短の該当曜日」を計算
form.date = calcNextDate(selectedMeeting.recurrence, selectedMeeting.day_of_week);
```

#### calcNextDate の実装方針

```js
// 今日（JST）から最短で meeting.day_of_week に該当する日付を返す
// recurrence='biweekly' の場合は隔週のため、定義作成曜日基準で奇数/偶数週を考慮
// （簡易実装として最初は次の該当曜日を返せば十分）
function calcNextDate(recurrence, dayOfWeek) {
    const today = new Date(/* JST today */);
    const todayDow = today.getDay();
    let diff = dayOfWeek - todayDow;
    if (diff <= 0) diff += 7; // 今日以降の次の曜日
    const next = new Date(today);
    next.setDate(today.getDate() + diff);
    return next.toISOString().split('T')[0];
}
```

---

### 5-3. カレンダーボタンからの遷移

`Calendar.vue` および `Diaries/Show.vue` の各ボタンを以下のように変更する:

```js
// 案件打合せ・外出ボタン → CreateClientEvent へ遷移
// 現在は openEventModal を呼んでいるので router.get に変更
function goToClientEvent() {
    router.get(route('events.client-event.create'), {
        date: selectedDate.value ?? '',
    });
}

// 社内予定ボタン → CreateInternalEvent へ遷移
function goToInternalEvent() {
    router.get(route('events.internal-event.create'), {
        date: selectedDate.value ?? '',
    });
}
```

---

## 6. Leader / Admin 会議設定機能

### 6-1. Leader/MeetingDefinitions/Create.vue（兼 Edit.vue）

**ページパス:** `resources/js/Pages/Leader/MeetingDefinitions/Create.vue`

#### フォームフィールド

| フィールド | 入力形式 | 説明 |
|---|---|---|
| タイトル | input[text] | 必須 |
| 概要 | textarea | nullable |
| 繰り返し設定 | radio（3択） | 毎週 / 隔週 / 毎月 |
| 曜日 | radio（7択：月〜日） | 必須 |
| 開始時刻 | select（時） × select（分：5分刻み） | 必須 |
| 終了時刻 | select（時） × select（分：5分刻み） | 必須 |
| 参加メンバー | チェックボックス一覧 + 絞り込み検索 | 必須・複数選択 |

#### メンバー選択 UI

`Coordinator/ProjectJobs/Create.vue` の「チームメンバー選択モーダル」を参考に実装する。  
具体的なパターン:
- 「メンバーを選択」ボタン → モーダルオープン
- モーダル内: 名前・部署で絞り込み可能なテーブル
- 全選択チェックボックスあり
- 確定すると選択済みメンバーを badge 形式で表示

Leader の場合: `users` where `department_id` = 自部署 のみ表示  
Admin の場合: 全 `users` 表示

#### 繰り返し・曜日のラベル

```js
const recurrenceOptions = [
    { value: 'weekly',   label: '毎週' },
    { value: 'biweekly', label: '隔週' },
    { value: 'monthly',  label: '毎月' },
];

const dayOfWeekOptions = [
    { value: 0, label: '日' },
    { value: 1, label: '月' },
    { value: 2, label: '火' },
    { value: 3, label: '水' },
    { value: 4, label: '木' },
    { value: 5, label: '金' },
    { value: 6, label: '土' },
];
```

### 6-2. Leader/MeetingDefinitions/Index.vue

- 一覧表示（タイトル・繰り返し・曜日・時間・メンバー数）
- 編集・削除ボタン
- 「会議を追加」ボタン（`#headerExtras` スロット）

### 6-3. ナビゲーションタブへの追加

**LeaderNavigationTabs.vue:**
```vue
<Link :href="route('leader.meeting_definitions.index')" :class="tab('meeting_definitions')">
    会議設定
</Link>
```

**AdminNavigationTabs.vue:**（既存の admin タブに追加）
```vue
<Link :href="route('admin.meeting_definitions.index')" :class="tab('meeting_definitions')">
    会議設定
</Link>
```

---

## 7. ルーティング設計

`routes/web.php` に以下を追加:

```php
// 案件打合せ・外出
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/events/client-event/create', [ClientEventController::class, 'create'])
        ->name('events.client-event.create');
    Route::post('/events/client-event', [ClientEventController::class, 'store'])
        ->name('events.client-event.store');
    Route::get('/events/client-event/{event}/edit', [ClientEventController::class, 'edit'])
        ->name('events.client-event.edit');
    Route::put('/events/client-event/{event}', [ClientEventController::class, 'update'])
        ->name('events.client-event.update');
});

// 社内予定
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/events/internal-event/create', [InternalEventController::class, 'create'])
        ->name('events.internal-event.create');
    Route::post('/events/internal-event', [InternalEventController::class, 'store'])
        ->name('events.internal-event.store');
    Route::get('/events/internal-event/{event}/edit', [InternalEventController::class, 'edit'])
        ->name('events.internal-event.edit');
    Route::put('/events/internal-event/{event}', [InternalEventController::class, 'update'])
        ->name('events.internal-event.update');
});

// Leader 会議設定
Route::middleware(['auth', 'verified', 'role:Leader,Admin,SuperAdmin'])->prefix('leader')->name('leader.')->group(function () {
    Route::resource('meeting-definitions', Leader\MeetingDefinitionController::class)
        ->names('meeting_definitions');
});

// Admin 会議設定
Route::middleware(['auth', 'verified', 'role:Admin,SuperAdmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('meeting-definitions', Admin\MeetingDefinitionController::class)
        ->names('meeting_definitions');
});
```

---

## 8. レイアウト基準

**必読:** `z_instructions/LAYOUT_SPEC_V2.md`

### 全ページ共通

```vue
<AppLayout title="ページタイトル">
    <template #header>
        <div class="flex items-center gap-3">
            <!-- 戻るボタン（LAYOUT_SPEC_V2.md 準拠） -->
            <Link :href="route('calendar.index')" class="rounded bg-gray-100 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-200">
                ← 戻る
            </Link>
            <h2 class="text-xl font-semibold text-gray-800">ページタイトル</h2>
        </div>
    </template>
    <!-- #headerExtras には保存ボタンは置かない（フォーム内に submit ボタン） -->
    <div class="rounded bg-white p-6 shadow">
        <!-- フォームコンテンツ -->
    </div>
</AppLayout>
```

### 参考ページ

- `Pages/Coordinator/ProjectJobs/Create.vue` → チームメンバー選択モーダルの実装パターン
- `Pages/Events/Create.vue` → 既存イベントフォームの構造（日時 picker, submit ロジック）
- `Pages/Coordinator/ProjectJobs/Show.vue` → ヘッダー・タブ構成の参考

---

## 9. 作業フロー

### STEP 1（事前確認）

1. `docker compose exec laravel bash -lc "php artisan migrate:status"` で現在の適用状況を確認
2. `event_item_types` テーブルに `client_visit`（来社応対）が未存在のことを確認

### STEP 2（DB）

1. マイグレーションファイルを作成
2. `docker compose exec laravel bash -lc "php artisan migrate"` を実行
3. `event_item_types` に来社応対を追加（マイグレーションまたはシーダー）

### STEP 3（バックエンド）

1. Model 更新（Event.php）
2. Controller 新規作成（ClientEventController, InternalEventController）
3. Leader/Admin MeetingDefinitionController 作成
4. ルート追加（web.php）

### STEP 4（フロントエンド）

1. `CreateClientEvent.vue` 作成
2. `CreateInternalEvent.vue` 作成
3. `Leader/MeetingDefinitions/Index.vue` + `Create.vue` + `Edit.vue` 作成
4. `Admin/MeetingDefinitions/` 同上
5. `Calendar.vue`, `Diaries/Show.vue` のボタンクリックハンドラを `openEventModal` → `router.get(route(...))` に変更
6. ナビゲーションタブに会議設定を追加

### STEP 5（ビルド・確認）

1. `npm run build`（プロジェクトルートで実行）
2. カレンダーページで各ボタンの遷移を確認
3. 各フォームの送信・バリデーション・リダイレクトを確認
4. Leader で会議定義を登録 → 社内予定フォームに反映されることを確認

---

---

## 10. イベント重複計算の修正（E-08）

### 症状

既存の `Events/Create.vue` では、新しいイベントを作成する際に他のイベントと時間が重複した場合、**ジョブ紐付きイベント**（`project_job_assignment_id` が設定されているもの）に対してのみ重複時間の除算が行われていた。

「予定作成」で作成した純粋な予定イベント（`project_job_assignment_id = null`）同士の重複では、確認ダイアログは出るが **`interrupted_event_ids` / `own_interruption_minutes` は 0 のまま** となり、実作業時間への差し引きが機能していない。

### 原因箇所

`resources/js/Pages/Events/Create.vue` の `submit()` 内:

```js
const jobLinked = overlapping.filter((ev) => ev.project_job_assignment_id);
const otherOverlap = overlapping.filter((ev) => !ev.project_job_assignment_id);

if (jobLinked.length > 0) {
    // ← ここで除算計算（jobLinked のみが対象）
} else {
    confirmMsg = '同じ時間に予定があります。登録しますか？'; // ← 除算なし
}
```

### 修正方針

`jobLinked` と `otherOverlap` の区別をなくし、**すべての重複イベント**に対して同じ「時間が長い方から差し引く」ロジックを適用する。

```js
// 修正後イメージ
const allOverlapping = overlapping; // jobLinked/otherOverlap の区別を廃止

const lines = allOverlapping.map((ev) => {
    // 既存と同じ duration 比較ロジック
    if (newDuration >= evDuration) {
        ownOverlapMins += overlapMins;
        return `...今回の予定から差し引き`;
    } else {
        interruptedIds.push(ev.id);
        return `...既存の予定から差し引き`;
    }
});

confirmMsg = '以下の予定と時間が重複しています。登録しますか？\n\n';
confirmMsg += lines.join('\n');
confirmMsg += '\n\n【OK】を押すと、時間の長い方の予定から重複時間が差し引かれます。';
```

### 適用対象ファイル

| ファイル | 対応内容 |
|---|---|
| `resources/js/Pages/Events/Create.vue` | 既存の重複計算ロジックを全イベント対象に修正 |
| `resources/js/Pages/Events/CreateClientEvent.vue` | 新規作成時から全イベント対象の重複計算を実装 |
| `resources/js/Pages/Events/CreateInternalEvent.vue` | 同上 |

### バックエンド（EventController）は修正不要

`EventController::store()` は `interrupted_event_ids` と `own_interruption_minutes` をフロントから受け取って処理する仕組みであり、すでに全イベントタイプに対応している。フロント側の計算ロジック修正のみで機能する。

---

## 付記: 設計上の判断事項（実装者へ）

| 判断事項 | 採用方針 |
|---|---|
| 案件連携は中間テーブル vs 直接FK | 直接FK（`events.project_job_id`）。1イベント1案件のため中間テーブル不要 |
| AssignmentForm.vue の複製 vs 独立コンポーネント | 独立コンポーネントとして新規作成。AssignmentForm はジョブ割当専用で複雑すぎる |
| CreateClientEvent と CreateInternalEvent の共有 vs 分離 | 分離。フィールドとロジックが十分に異なるため |
| 来社応対でのプロジェクト連携 | あり（顧客訪問と同じ扱い） |
| meeting_definition_id を events テーブルに保存するか | 保存しない。フォームの自動入力トリガーとして使うのみ |
| 重複計算の対象範囲 | ジョブ紐付きイベントのみ → **全イベント**に拡張（E-08）。バックエンドは修正不要、フロントのみ |

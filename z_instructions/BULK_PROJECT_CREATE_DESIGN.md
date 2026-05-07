# 案件一括作成機能 詳細設計書

**作成日:** 2026-04-20
**対象ロール:** Coordinator
**ステータス:** 実装完了（2026-05-06 確認）

---

## コード調査結果サマリー

| 調査項目 | 確認内容 |
|---------|---------|
| `ProjectJob` fillable | `jobcode, title, user_id, client_id, detail, size_id, page_count, schedule, completed` |
| `ProjectTeamMember` fillable | `project_job_id, user_id`（assignment_idカラムは存在しない） |
| サブCo ピボット | `project_job_coordinators`（users と belongsToMany） |
| `ProgressTemplate` | `name, description, column_config(JSON), row_config(JSON), is_shared, created_by` |
| さくら本番制約 | `schedule`カラムなし → `Arr::pull($data, 'schedule')` 必須 |
| ルート順序制約 | 静的パスを `{projectJob}` より必ず前に定義 |
| CSRF取得 | `meta[name="csrf-token"]`から取得（クッキーは使わない） |
| クライアント検索 | `route('coordinator.clients.json')` エンドポイント既存 |

---

## 1. DB設計

### 1-1. 新規テーブル

#### `project_job_templates`

| カラム | 型 | NULL | デフォルト | 備考 |
|--------|-----|------|-----------|------|
| `id` | bigint unsigned | NO | auto_increment | PK |
| `name` | varchar(255) | NO | — | テンプレート名 |
| `description` | text | YES | NULL | 説明文 |
| `fixed_fields` | json | YES | NULL | 固定項目（後述） |
| `team_members` | json | YES | NULL | チームメンバー（後述） |
| `is_shared` | tinyint(1) | NO | 0 | 共有フラグ |
| `created_by` | bigint unsigned | NO | — | FK → users.id |
| `created_at` | timestamp | YES | NULL | |
| `updated_at` | timestamp | YES | NULL | |

**`fixed_fields` JSON 構造:**
```json
{
  "user_id": 5,
  "sub_coordinator_ids": [3, 7],
  "size_id": 2,
  "page_count": 128,
  "detail": "通常校正あり"
}
```
- 設定しない項目はキーごと省略する（null ではなく absent）

**`team_members` JSON 構造:**
```json
[
  { "user_id": 10, "user_name": "田中太郎" },
  { "user_id": 11, "user_name": "山田花子" }
]
```
- `user_name` は表示用キャッシュ。保存時に解決して入れる（更新時は再解決）

### 1-2. マイグレーションファイル名

```
2026_04_20_000001_create_project_job_templates_table.php
```

### 1-3. 既存テーブルへのカラム追加

**なし**（3機能ともカラム追加不要）

---

## 2. API / ルート設計

### 2-1. 追加ルート一覧

`routes/web.php` の coordinator グループ内に追加。
**重要：静的パス（`bulk-create` 等）は必ず `{projectJob}` より前に定義すること。**

```php
// ─────────────────────────────────────────
// 機能1: ワンクリック複製
// ─────────────────────────────────────────
// project_jobs/{projectJob}/complete の直後あたりに追加
Route::post('project_jobs/{projectJob}/clone',
    [ProjectJobController::class, 'clone'])
    ->name('project_jobs.clone');

// ─────────────────────────────────────────
// 機能2: CSVテンプレート一括登録
// ─────────────────────────────────────────
// 静的パスのため project_jobs/create の後・{projectJob} の前に定義
Route::get('project-jobs/bulk-create',
    [BulkProjectJobController::class, 'index'])
    ->name('project_jobs.bulk_create.index');

Route::get('project-jobs/bulk-create/sample',
    [BulkProjectJobController::class, 'downloadSample'])
    ->name('project_jobs.bulk_create.sample');

Route::post('project-jobs/bulk-create/preview',
    [BulkProjectJobController::class, 'preview'])
    ->name('project_jobs.bulk_create.preview');

Route::post('project-jobs/bulk-create/store',
    [BulkProjectJobController::class, 'store'])
    ->name('project_jobs.bulk_create.store');

// テンプレートCRUD（静的パスのため {projectJob} より前に定義）
Route::get('project-job-templates',
    [ProjectJobTemplateController::class, 'index'])
    ->name('project_job_templates.index');

Route::post('project-job-templates',
    [ProjectJobTemplateController::class, 'store'])
    ->name('project_job_templates.store');

Route::put('project-job-templates/{template}',
    [ProjectJobTemplateController::class, 'update'])
    ->name('project_job_templates.update');

Route::delete('project-job-templates/{template}',
    [ProjectJobTemplateController::class, 'destroy'])
    ->name('project_job_templates.destroy');

// ─────────────────────────────────────────
// 機能3: クライアントプリセット
// ─────────────────────────────────────────
// clients グループ内（既存 clients.json の直後あたり）に追加
Route::get('clients/{client}/last-job-config',
    [ClientController::class, 'lastJobConfig'])
    ->name('clients.last_job_config');
```

### 2-2. エンドポイント仕様

#### `POST coordinator/project_jobs/{projectJob}/clone`
- **権限:** `isJobCoordinator()` で判定（Admin/SuperAdmin/Clerk は全案件許可）
- **処理:** 元案件の `user_id, client_id, size_id, page_count, detail` をコピー。`title = 'コピー - ' + 元タイトル`、`jobcode = null`
- **副作用:** `project_job_coordinators`（サブCo）、`project_team_members` を新案件にコピー
- **さくら対応:** `Arr::pull($data, 'schedule')` を必ず実行
- **レスポンス:** `redirect()->route('coordinator.project_jobs.edit', $newJob->id)` + flash `success`

#### `GET coordinator/project-jobs/bulk-create`
- **レスポンス:** `Inertia::render('Coordinator/ProjectJobs/BulkCreate', [...])`
- **Props:**

```php
[
  'templates'              => ProjectJobTemplate[]  // 自分所有 or is_shared
  'coordinatorCandidates'  => [{id, name}]
  'sizes'                  => [{id, name, group}]
  'users'                  => [{id, name, assignment_id, assignment_name}]  // チームメンバー選択用
]
```

#### `GET coordinator/project-jobs/bulk-create/sample?template_id=1`
- **処理:** テンプレートの `fixed_fields` を参照して CSV ヘッダーを動的生成
- **レスポンス:** CSV ファイルダウンロード（`Content-Type: text/csv; charset=UTF-8`）
- **BOM付き UTF-8**（Excel 対応）: `"\xEF\xBB\xBF"` をプレフィックスに付ける

#### `POST coordinator/project-jobs/bulk-create/preview`
- **リクエスト:** `multipart/form-data` で `csv_file` + `template_id`
- **処理:** CSV パース → バリデーション → `client_name` を `client_id` に解決（複数候補は警告）
- **レスポンス:** `Inertia::render('Coordinator/ProjectJobs/BulkCreate', [...])` + `previewData` prop を追加

```php
// previewData 構造
[
  'rows' => [
    [
      'rowNum'    => 1,
      'data'      => [...フィールドデータ...],
      'errors'    => [],     // バリデーションエラー
      'warnings'  => [],     // 候補複数など警告
      'valid'     => true,
    ],
    ...
  ],
  'validCount'   => 8,
  'errorCount'   => 2,
  'templateId'   => 1,
]
```

#### `POST coordinator/project-jobs/bulk-create/store`
- **リクエスト:** `{ rows: [...], template_id: 1 }`（プレビュー後の確認済みデータ）
- **処理:** DB::transaction で一括 INSERT。各行に対して:
  1. `Arr::pull($data, 'schedule')` — さくら本番対応
  2. `ProjectJob::create(...)` — 案件作成
  3. サブCo sync（テンプレート固定値 or CSV値）
  4. チームメンバー INSERT（テンプレート固定値）
- **レスポンス:** `redirect()->route('coordinator.project_jobs.index')` + flash `{ created: [job ids] }`

#### `GET coordinator/project-job-templates`
- **レスポンス:** JSON（`is_shared=true` OR `created_by=自分` のテンプレート一覧）

#### `POST coordinator/project-job-templates`
**リクエスト:**
```json
{
  "name": "テンプレート名",
  "description": "説明（任意）",
  "fixed_fields": { "user_id": 5, "size_id": 2 },
  "team_members": [{ "user_id": 10, "user_name": "田中太郎" }],
  "is_shared": false
}
```
- **バリデーション:** `name required|max:255`, `fixed_fields nullable|array`, `team_members nullable|array`
- **レスポンス:** JSON（作成したテンプレート）

#### `PUT coordinator/project-job-templates/{template}`
- 権限: 作成者 or Admin/SuperAdmin
- **レスポンス:** JSON（更新後テンプレート）

#### `DELETE coordinator/project-job-templates/{template}`
- 権限: 作成者 or Admin/SuperAdmin
- **レスポンス:** `204 No Content`

#### `GET coordinator/clients/{client}/last-job-config`
- **処理:** そのクライアントの最新案件を取得し、設定情報を JSON 返却
- **レスポンス:**
```json
{
  "job_title":            "前回案件タイトル",
  "job_created_at":       "2026年3月",
  "user_id":              5,
  "user_name":            "田中Co",
  "sub_coordinator_ids":  [3, 7],
  "sub_coordinator_names":["山田", "佐藤"],
  "size_id":              2,
  "size_name":            "B5",
  "page_count":           128,
  "detail":               "通常校正あり",
  "team_members": [
    { "user_id": 10, "user_name": "田中太郎" }
  ]
}
```
- 案件なし: `null` (200)

---

## 3. コンポーネント設計

### 3-1. 新規作成ファイル

| ファイル | 役割 |
|---------|------|
| `app/Models/ProjectJobTemplate.php` | テンプレートモデル |
| `app/Http/Controllers/Coordinator/ProjectJobTemplateController.php` | テンプレートCRUD |
| `app/Http/Controllers/Coordinator/BulkProjectJobController.php` | 一括作成ロジック |
| `resources/js/Pages/Coordinator/ProjectJobs/BulkCreate.vue` | 一括作成ハブ画面 |
| `database/migrations/2026_04_20_000001_create_project_job_templates_table.php` | マイグレーション |

### 3-2. 変更ファイル

| ファイル | 変更内容 |
|---------|---------|
| `app/Http/Controllers/Coordinator/ProjectJobController.php` | `clone()` メソッド追加 |
| `app/Http/Controllers/ClientController.php` | `lastJobConfig()` メソッド追加 |
| `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` | 「複製して新規作成」ボタン追加 |
| `resources/js/Pages/Coordinator/ProjectJobs/Index.vue` | 「一括作成」ボタン追加 |
| `resources/js/Pages/Coordinator/ProjectJobs/Create.vue` | クライアントプリセット追加 |
| `routes/web.php` | 新規ルート追加 |

### 3-3. BulkCreate.vue Props定義

```js
defineProps({
  templates:             { type: Array, default: () => [] }, // ProjectJobTemplate[]
  coordinatorCandidates: { type: Array, default: () => [] }, // {id, name}[]
  sizes:                 { type: Array, default: () => [] }, // {id, name, group}[]
  users:                 { type: Array, default: () => [] }, // {id, name}[] チームメンバー選択用
  previewData:           { type: Object, default: null },    // CSV プレビュー結果
});
```

### 3-4. BulkCreate.vue レイアウト構成

```
AppLayout
└── .rounded.bg-white.p-6.shadow
    ├── h1: 「案件一括作成」+ [案件一覧へ]リンク
    ├── タブ切替（テンプレート管理 / CSV取込）
    │
    ├── [タブ: テンプレート管理]
    │   ├── テンプレート一覧（ラジオ or select）
    │   ├── テンプレート新規/編集フォーム
    │   │   ├── テンプレート名（required）
    │   │   ├── 説明
    │   │   ├── 共有フラグ
    │   │   ├── 固定項目セクション
    │   │   │   ├── リーダー（select, 空=CSV入力）
    │   │   │   ├── サブCo（チェックボックス複数, 空=CSV入力）
    │   │   │   ├── サイズ（select, 空=CSV入力）
    │   │   │   ├── 総ページ数（input, 空=CSV入力）
    │   │   │   └── 詳細（textarea, 空=CSV入力）
    │   │   └── チームメンバーセクション
    │   │       ├── メンバー追加ボタン → users から選択
    │   │       └── 追加済みメンバー一覧（削除可）
    │   └── [保存] [削除]ボタン
    │
    └── [タブ: CSV取込]
        ├── テンプレート選択（select）
        ├── [サンプルCSVをダウンロード]ボタン（テンプレート選択後有効化）
        ├── CSVファイル選択 + [プレビュー]ボタン
        └── [previewData がある場合]
            ├── プレビューテーブル（エラー行赤ハイライト・警告行黄ハイライト）
            ├── サマリー（有効N件、エラーN件）
            └── [登録実行]ボタン（エラーなしの場合のみ有効）
```

### 3-5. Show.vue の変更点

```vue
<!-- 既存の [編集] ボタン付近に追加 -->
<button
  type="button"
  class="rounded border border-blue-400 px-3 py-1.5 text-sm text-blue-700 hover:bg-blue-50"
  @click="cloneJob"
>
  この案件を複製する
</button>

<!-- script -->
function cloneJob() {
  if (!confirm('この案件をもとに新規案件を作成します。よいですか？\n（タイトル・伝票番号・クライアントは複製先で修正できます）')) return;
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
  fetch(route('coordinator.project_jobs.clone', { projectJob: props.job.id }), {
    method: 'POST',
    headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin',
  }).then(res => {
    if (res.redirected) {
      window.location.href = res.url;
    }
  });
}
// または Inertia router.post() で実装（推奨）
```

**推奨実装（Inertia）:**
```js
import { router } from '@inertiajs/vue3';
function cloneJob() {
  if (!confirm('この案件をもとに新規案件を作成します。よいですか？')) return;
  router.post(route('coordinator.project_jobs.clone', { projectJob: props.job.id }));
}
```

### 3-6. Index.vue の変更点

```vue
<!-- 既存の [新規作成] ボタン行に追加 -->
<div class="mb-6 flex items-center justify-between">
  <h1 class="text-2xl font-bold">案件一覧</h1>
  <div class="flex gap-2">
    <Link
      :href="route('coordinator.project_jobs.bulk_create.index')"
      class="rounded border border-green-600 px-4 py-2 text-green-700 hover:bg-green-50 text-sm"
    >
      テンプレートから一括作成
    </Link>
    <Link
      :href="route('coordinator.project_jobs.create')"
      class="rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700"
    >
      新規作成
    </Link>
  </div>
</div>
```

### 3-7. Create.vue の変更点（クライアントプリセット）

```js
// selectClient() 関数の末尾に追記
async function selectClient(client) {
  form.client_id = client.id;
  form.client_name = client.name;
  closeClientModal();
  closeClientListModal();

  // ── クライアントプリセット ──────────────────────────────
  try {
    const res = await fetch(
      route('coordinator.clients.last_job_config', { client: client.id }),
      { headers: { Accept: 'application/json' }, credentials: 'same-origin' }
    );
    if (res.ok) {
      const config = await res.json();
      if (config) {
        lastJobConfig.value = config;
        showPresetBanner.value = true;
      }
    }
  } catch { /* ignore */ }
}

// 引き継ぐボタン
function applyPreset() {
  const c = lastJobConfig.value;
  if (!c) return;
  if (c.user_id)             form.user_id = c.user_id;
  if (c.sub_coordinator_ids) form.sub_coordinator_ids = [...c.sub_coordinator_ids];
  if (c.size_id)             form.size_id = c.size_id;
  if (c.page_count)          form.page_count = c.page_count;
  if (c.detail)              form.detail = c.detail;
  showPresetBanner.value = false;
}
```

**追加リアクティブ変数:**
```js
const showPresetBanner = ref(false);
const lastJobConfig = ref(null);
```

**テンプレートに追加（クライアント選択直後に表示）:**
```vue
<!-- クライアントプリセットバナー -->
<div v-if="showPresetBanner && lastJobConfig"
     class="mb-4 rounded border border-blue-300 bg-blue-50 px-4 py-3 text-sm">
  <p class="font-semibold text-blue-800">前回の設定を引き継ぎますか？</p>
  <p class="mt-1 text-blue-700">
    （{{ lastJobConfig.job_created_at }}「{{ lastJobConfig.job_title }}」より）
    リーダー: {{ lastJobConfig.user_name }}、
    サイズ: {{ lastJobConfig.size_name || 'なし' }}、
    メンバー: {{ lastJobConfig.team_members?.length || 0 }}名
  </p>
  <div class="mt-2 flex gap-2">
    <button type="button"
            class="rounded bg-blue-600 px-3 py-1 text-white text-xs"
            @click="applyPreset">引き継ぐ</button>
    <button type="button"
            class="rounded border px-3 py-1 text-gray-600 text-xs"
            @click="showPresetBanner = false">使わない</button>
  </div>
</div>
```

---

## 4. CSVフォーマット仕様

### 4-1. CSV列の動的生成ロジック

```
常時 CSV に含まれる列:
  1. jobcode    （任意）
  2. title      （必須）
  3. client_name（client_id との片方必須）

テンプレートの fixed_fields に存在しない場合に追加される列:
  4. leader_name  （fixed_fields.user_id がない場合）
  5. size_name    （fixed_fields.size_id がない場合）
  6. page_count   （fixed_fields.page_count がない場合）
  7. detail       （fixed_fields.detail がない場合）
```

### 4-2. サンプル CSV 生成ロジック（PHP）

```php
public function downloadSample(Request $request)
{
    $templateId = $request->query('template_id');
    $template   = $templateId ? ProjectJobTemplate::findOrFail($templateId) : null;
    $fixed      = $template?->fixed_fields ?? [];

    $headers = ['jobcode', 'title', 'client_name'];
    if (empty($fixed['user_id']))    $headers[] = 'leader_name';
    if (empty($fixed['size_id']))    $headers[] = 'size_name';
    if (!array_key_exists('page_count', $fixed)) $headers[] = 'page_count';
    if (!array_key_exists('detail',    $fixed))  $headers[] = 'detail';

    $sampleRows = [
        $headers,
        array_map(fn($h) => $this->sampleValue($h), $headers),
        array_map(fn($h) => '', $headers), // 空行サンプル
    ];

    $csv = "\xEF\xBB\xBF"; // UTF-8 BOM（Excel対応）
    foreach ($sampleRows as $row) {
        $csv .= implode(',', array_map(
            fn($v) => '"' . str_replace('"', '""', (string) $v) . '"',
            $row
        )) . "\n";
    }

    return response($csv, 200, [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="project_jobs_sample.csv"',
    ]);
}

private function sampleValue(string $col): string
{
    return match ($col) {
        'jobcode'     => '2026-001',
        'title'       => '〇〇書籍 本文',
        'client_name' => '株式会社サンプル',
        'leader_name' => '田中Co',
        'size_name'   => 'B5',
        'page_count'  => '128',
        'detail'      => '通常校正あり',
        default       => '',
    };
}
```

### 4-3. バリデーションルール（preview メソッド）

| フィールド | ルール |
|-----------|-------|
| `title` | required, max:255 |
| `client_name` | required（`client_id` がなければ）, DB部分一致検索。0件 → エラー、複数 → 警告（最初にマッチを使用） |
| `client_id` | nullable, exists:clients,id |
| `jobcode` | nullable, `^[0-9\-]+$` |
| `leader_name` | nullable（テンプレート未固定時）, users.name の完全一致検索 |
| `size_name` | nullable, sizes.name の完全一致検索 |
| `page_count` | nullable, integer, 1–99999 |
| `detail` | nullable, string |

**`client_name` 解決ロジック:**
```php
$clients = Client::where('name', 'like', "%{$clientName}%")->get(['id', 'name']);
if ($clients->count() === 0)  → エラー: "クライアント「{名前}」が見つかりません"
if ($clients->count() === 1)  → $clientId = $clients->first()->id（OK）
if ($clients->count() > 1)   → 警告: "複数候補あり（先頭を使用: {名前}）", 先頭を使用
```

---

## 5. 実装順序

### Phase 1: ワンクリック複製（最小実装）

**実装ファイル:**
1. `routes/web.php` — `project_jobs/{projectJob}/clone` ルート追加
2. `app/Http/Controllers/Coordinator/ProjectJobController.php` — `clone()` メソッド追加
3. `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` — 「複製して新規作成」ボタン追加
4. `npm run build`

**clone() 実装骨格:**
```php
public function clone(Request $request, ProjectJob $projectJob)
{
    $user = $request->user();
    if (!$this->isJobCoordinator($projectJob, $user)) {
        abort(403);
    }

    $newJob = null;
    DB::transaction(function () use ($projectJob, &$newJob) {
        $data = $projectJob->only([
            'user_id', 'client_id', 'size_id', 'page_count', 'detail'
        ]);
        $data['title'] = 'コピー - ' . $projectJob->title;
        $data['jobcode'] = null;
        Arr::pull($data, 'schedule'); // さくら本番：schedule カラムなし

        $newJob = ProjectJob::create($data);

        // サブCo 複製
        $subIds = $projectJob->coordinators()->pluck('users.id')->toArray();
        $syncIds = array_values(array_filter($subIds, fn($id) => $id != $newJob->user_id));
        if (!empty($syncIds)) {
            $newJob->coordinators()->sync($syncIds);
        }

        // チームメンバー複製
        foreach ($projectJob->teamMembers as $member) {
            \App\Models\ProjectTeamMember::create([
                'project_job_id' => $newJob->id,
                'user_id'        => $member->user_id,
            ]);
        }
    });

    return redirect()
        ->route('coordinator.project_jobs.edit', $newJob->id)
        ->with('success', '案件を複製しました。タイトル・伝票番号・クライアントを確認・修正してください。');
}
```

---

### Phase 2: クライアントプリセット

**実装ファイル:**
1. `routes/web.php` — `clients/{client}/last-job-config` ルート追加
2. `app/Http/Controllers/ClientController.php` — `lastJobConfig()` 追加
3. `resources/js/Pages/Coordinator/ProjectJobs/Create.vue` — `selectClient()` 拡張 + バナー UI
4. `npm run build`

**lastJobConfig() 実装骨格:**
```php
public function lastJobConfig(Request $request, \App\Models\Client $client)
{
    $lastJob = \App\Models\ProjectJob::where('client_id', $client->id)
        ->with(['user', 'teamMembers.user', 'coordinators', 'size'])
        ->orderBy('created_at', 'desc')
        ->first();

    if (!$lastJob) {
        return response()->json(null);
    }

    return response()->json([
        'job_title'             => $lastJob->title,
        'job_created_at'        => $lastJob->created_at?->format('Y年n月'),
        'user_id'               => $lastJob->user_id,
        'user_name'             => $lastJob->user?->name,
        'sub_coordinator_ids'   => $lastJob->coordinators->pluck('id'),
        'sub_coordinator_names' => $lastJob->coordinators->pluck('name'),
        'size_id'               => $lastJob->size_id,
        'size_name'             => $lastJob->size?->name,
        'page_count'            => $lastJob->page_count,
        'detail'                => $lastJob->detail,
        'team_members'          => $lastJob->teamMembers->map(fn($m) => [
            'user_id'   => $m->user_id,
            'user_name' => $m->user?->name,
        ]),
    ]);
}
```

---

### Phase 3: CSVテンプレート一括登録

#### Phase 3-1: DB・モデル

**実装ファイル:**
1. `database/migrations/2026_04_20_000001_create_project_job_templates_table.php`
2. `app/Models/ProjectJobTemplate.php`

**マイグレーション:**
```php
Schema::create('project_job_templates', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->json('fixed_fields')->nullable();
    $table->json('team_members')->nullable();
    $table->boolean('is_shared')->default(false);
    $table->unsignedBigInteger('created_by');
    $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
    $table->timestamps();
});
```

**モデル:**
```php
class ProjectJobTemplate extends Model
{
    protected $fillable = [
        'name', 'description', 'fixed_fields', 'team_members', 'is_shared', 'created_by',
    ];

    protected $casts = [
        'fixed_fields' => 'array',
        'team_members' => 'array',
        'is_shared'    => 'boolean',
    ];

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }
}
```

#### Phase 3-2: コントローラ

**実装ファイル:**
1. `app/Http/Controllers/Coordinator/ProjectJobTemplateController.php`
2. `app/Http/Controllers/Coordinator/BulkProjectJobController.php`

#### Phase 3-3: Vue + ルート

**実装ファイル:**
1. `routes/web.php` — 新規ルート追加
2. `resources/js/Pages/Coordinator/ProjectJobs/BulkCreate.vue` — 新規作成
3. `resources/js/Pages/Coordinator/ProjectJobs/Index.vue` — 一括作成ボタン追加
4. `npm run build`

---

## 補足: さくら本番での注意事項（全フェーズ共通）

1. **`Arr::pull($data, 'schedule')`** — `ProjectJob::create()` / `update()` 前に必ず実行
2. **ナビゲーションは `route()` を使う** — `window.location.href = '/coordinator/...'` は禁止
3. **CSRF は `meta[name="csrf-token"]`** から取得 — `document.cookie` からの取得は禁止
4. **`php artisan migrate`** — Phase 3-1 実装後、本番デプロイ時に必ず実行
5. **さくら上の `sed -i` は `-i ''`** — デプロイスクリプト変更時に注意

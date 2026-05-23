# ジョブ管理ページ 設計書
作成日: 2026-05-04

---

## 概要

proof-coordinator の「割り振り管理」と「案件校正履歴」を統合し、「ジョブ管理」という1ページにまとめる。

### ゴール
- 進行中と完了を1ページでタブ切り替えで管理
- 表のカラムを両タブで統一
- 検索・グループ表示・ステータス移動を1か所で完結

---

## 変更ファイル一覧

| ファイル | 変更種別 | 内容 |
|---------|---------|------|
| `resources/js/Pages/ProofCoordinator/Assignments/Index.vue` | 完全書き換え | ジョブ管理ページ（2タブ・検索・グループ表示） |
| `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue` | 修正 | 「割り振り管理」→「ジョブ管理」にリネーム、「案件校正履歴」タブを削除 |
| `app/Http/Controllers/ProofCoordinator/ProofRequestController.php` | 修正 | jobManagement()追加・assignStore()ステータス変更・uncomplete()追加 |
| `routes/web.php` | 修正 | 新ルート2件追加 |

---

## P-01：ルート追加

### 追加ルート

```php
// ジョブ管理（進行中・完了 統合ページ）
Route::get('/proof-coordinator/jobs', [ProofRequestController::class, 'jobManagement'])
    ->name('proof_coordinator.jobs');

// 完了 → 未完了に戻す
Route::put('/proof-coordinator/assignments/{proofRequest}/uncomplete', [ProofRequestController::class, 'uncomplete'])
    ->name('proof_coordinator.assignments.uncomplete');
```

### 既存ルートの扱い
- `proof_coordinator.assignments`（割り振り管理）→ **残す**（後方互換）
- `proof_coordinator.history`（案件校正履歴）→ **残す**（後方互換）
- `proof_coordinator.assignments.start`（開始する）→ **残す**（UIからは削除、APIとして保持）

---

## P-02：コントローラー変更

### 2-1. `assignStore()` の変更

**変更箇所（1行のみ）:**

```php
// 変更前
'status' => 'assigned',

// 変更後
'status' => 'in_progress',
```

**影響:** 校正ジョブ割り当て時に「割り当て済み」をスキップし、即「校正中」になる。
過去の `assigned` レコードは引き続き「進行中」として扱う（DB変更なし）。

---

### 2-2. `jobManagement()` メソッド追加

```php
public function jobManagement(Request $request): Response
{
    $tab = $request->input('tab', 'active'); // 'active' | 'completed'

    // 共通クエリ
    $baseQuery = fn() => ProofRequest::with(['requester', 'proofCoordinator', 'proofreader', 'projectJob']);

    // --- 進行中タブ ---
    $active = $baseQuery()
        ->whereIn('status', ['assigned', 'in_progress'])
        ->orderBy('deadline');

    // --- 完了タブ ---
    $completedQuery = $baseQuery()
        ->where('status', 'completed')
        ->orderByDesc('completed_at');

    // 検索（タイトル・案件名）
    if ($search = $request->input('search')) {
        $active->where(fn($q) =>
            $q->where('title', 'like', "%{$search}%")
              ->orWhereHas('projectJob', fn($q2) => $q2->where('title', 'like', "%{$search}%"))
        );
        $completedQuery->where(fn($q) =>
            $q->where('title', 'like', "%{$search}%")
              ->orWhereHas('projectJob', fn($q2) => $q2->where('title', 'like', "%{$search}%"))
        );
    }

    // 年月フィルター（依頼日 or 締め切り日）
    $period    = $request->input('period');
    $dateField = $request->input('date_field', 'created_at'); // 'created_at' | 'deadline'
    if ($period) {
        $col = $dateField === 'deadline' ? 'deadline' : 'created_at';
        $active->whereRaw("DATE_FORMAT({$col}, '%Y-%m') = ?", [$period]);
        $completedQuery->whereRaw("DATE_FORMAT({$col}, '%Y-%m') = ?", [$period]);
    }

    // 完了タブ：年月未指定時はデフォルトで直近3か月
    if (! $period && $tab === 'completed') {
        $completedQuery->where('completed_at', '>=', now()->subMonths(3)->startOfMonth());
    }

    $activeRequests    = $active->get();
    $completedRequests = $completedQuery->get();

    // 年月セレクター用オプション（completed_at or created_at ベース）
    $monthOptions = ProofRequest::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as value")
        ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
        ->orderByDesc('value')
        ->pluck('value')
        ->map(fn($m) => [
            'value' => $m,
            'label' => sprintf('%d年%d月', (int)explode('-',$m)[0], (int)explode('-',$m)[1]),
        ])
        ->values()
        ->toArray();

    return Inertia::render('ProofCoordinator/Assignments/Index', [
        'activeRequests'    => $activeRequests,
        'completedRequests' => $completedRequests,
        'tab'               => $tab,
        'search'            => $request->input('search', ''),
        'period'            => $period ?? '',
        'dateField'         => $dateField,
        'monthOptions'      => $monthOptions,
    ]);
}
```

---

### 2-3. `uncomplete()` メソッド追加

```php
public function uncomplete(ProofRequest $proofRequest)
{
    if ($proofRequest->status !== 'completed') {
        return back()->with('error', 'この校正はまだ完了していません。');
    }

    DB::transaction(function () use ($proofRequest) {
        // ProofRequest を in_progress に戻す
        $proofRequest->update([
            'status'       => 'in_progress',
            'completed_at' => null,
        ]);

        // 元ジョブ（pja_operator）の proof_completed_at を戻す
        if ($proofRequest->project_job_assignment_id) {
            ProjectJobAssignment::where('id', $proofRequest->project_job_assignment_id)
                ->update(['proof_completed_at' => null]);
        }

        // pja100（校正割当ジョブ）の completed フラグを戻す
        if ($proofRequest->proofreader_id && $proofRequest->proof_coordinator_id) {
            ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
                ->where('user_id', $proofRequest->proofreader_id)
                ->where('sender_id', $proofRequest->proof_coordinator_id)
                ->update(['completed' => false]);
        }
    });

    return back()->with('success', '校正を未完了に戻しました。');
}
```

---

## P-03：Vue ページ（ジョブ管理）

### ファイル: `resources/js/Pages/ProofCoordinator/Assignments/Index.vue`

### 3-1. props

```js
defineProps({
    activeRequests:    { type: Array,  default: () => [] },
    completedRequests: { type: Array,  default: () => [] },
    tab:               { type: String, default: 'active' },
    search:            { type: String, default: '' },
    period:            { type: String, default: '' },
    dateField:         { type: String, default: 'created_at' },
    monthOptions:      { type: Array,  default: () => [] },
});
```

### 3-2. 検索UIレイアウト

```
[ タイトル・案件名で検索 _________ ] [ 検索 ] [ クリア ]

● 依頼日  ○ 締め切り日    年月: [ 2026年5月 ▼ ]

[ 案件ごと ] [ 校正員ごと ] [ 締め切りごと ]
```

- 検索ボタン: `bg-pink-600` (proof-coordinator テーマ色)
- ラジオボタン: 依頼日（created_at）/ 締め切り日（deadline）
- 年月セレクト: `monthOptions` を使用。未選択 = 全期間（進行中タブ）/ 直近3か月（完了タブ）
- ソートピル: 選択中は `bg-pink-100 text-pink-700 font-semibold`、未選択は `text-gray-600`

### 3-3. タブ切り替え

```html
<div class="flex border-b mb-4">
  <button
    @click="switchTab('active')"
    :class="currentTab === 'active'
      ? 'border-b-2 border-pink-600 text-pink-600 font-semibold'
      : 'text-gray-500 hover:text-gray-700'"
    class="px-4 py-2 text-sm"
  >
    進行中のジョブ
    <span class="ml-1 rounded-full bg-pink-100 px-2 py-0.5 text-xs text-pink-700">
      {{ activeRequests.length }}
    </span>
  </button>
  <button
    @click="switchTab('completed')"
    :class="currentTab === 'completed'
      ? 'border-b-2 border-pink-600 text-pink-600 font-semibold'
      : 'text-gray-500 hover:text-gray-700'"
    class="px-4 py-2 text-sm"
  >
    完了したジョブ
  </button>
</div>
```

タブ切り替え: `router.get(route('proof_coordinator.jobs'), { ...filters, tab: 'active'|'completed' })`

### 3-4. テーブルカラム（両タブ共通）

| 依頼日 | タイトル | 案件 | 依頼者 | 校正員 | 締め切り | 完了日 | ステータス | 操作 |

- **依頼日**: `created_at` を `ja-JP` ロケールで日付のみ表示
- **タイトル**: `req.title`
- **案件**: `req.project_job?.title ?? '—'`
- **依頼者**: `req.requester?.name ?? '—'`
- **校正員**: `req.proofreader?.name ?? '—'`（校正員未割り当ての場合はグレー表示）
- **締め切り**: `fmtDeadline(req.deadline)`（期限超過は赤太字）
- **完了日**: `req.completed_at ? toDateString : '—'`
- **ステータス**: バッジ表示（assigned=青/in_progress=インジゴ/completed=黄）
- **操作**:
  - 進行中タブ: 「完了にする」`bg-green-600` ボタン + クリックで confirm
  - 完了タブ: 「未完了に戻す」`border border-gray-300` ボタン

行クリック → `proof_coordinator.assignments.show` に遷移（操作列は `@click.stop`）

### 3-5. 視覚的グループ表示

ソートピル選択によりクライアントサイドで computed でグループ化する。

```js
// グループ化ロジック
const groupedRows = computed(() => {
    const rows = currentTab.value === 'active' ? props.activeRequests : props.completedRequests;

    const getKey = (req) => {
        if (groupMode.value === 'project')    return req.project_job?.title ?? '案件なし';
        if (groupMode.value === 'proofreader') return req.proofreader?.name ?? '未割り当て';
        if (groupMode.value === 'deadline') {
            if (!req.deadline) return '締め切りなし';
            const d = new Date(req.deadline);
            return `${d.getFullYear()}年${d.getMonth()+1}月${d.getDate()}日`;
        }
        return '';
    };

    // グループ化してソート
    const map = new Map();
    for (const req of rows) {
        const key = getKey(req);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(req);
    }
    // deadline モードは日付順、他はアルファベット順
    const keys = [...map.keys()].sort();
    return keys.map(key => ({ key, rows: map.get(key) }));
});
```

**グループヘッダー行:**
```html
<tr class="bg-pink-50">
  <td colspan="9" class="px-4 py-2 text-sm font-semibold text-pink-800">
    {{ group.key }}
    <span class="ml-2 text-xs font-normal text-gray-500">{{ group.rows.length }}件</span>
  </td>
</tr>
```

### 3-6. ステータスバッジ定義（両タブ統一）

```js
const statusLabel = {
    assigned:    '割り当て済み',
    in_progress: '校正中',
    completed:   '完了',
};
const statusBadge = {
    assigned:    'bg-blue-100 text-blue-800',
    in_progress: 'bg-indigo-100 text-indigo-800',
    completed:   'bg-yellow-100 text-yellow-800',
};
```

---

## P-04：ナビゲーションタブ変更

### ファイル: `resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue`

#### 変更内容

| 変更前 | 変更後 |
|--------|--------|
| タブ名「割り振り管理」、route: `proof_coordinator.assignments` | タブ名「ジョブ管理」、route: `proof_coordinator.jobs` |
| タブ名「案件校正履歴」、route: `proof_coordinator.history` | **削除** |

#### `active` prop 値の対応（変更前 → 変更後）

| 旧 active 値 | 新 active 値 |
|-------------|-------------|
| `"assignments"` | `"jobs"` |
| `"history"` | 廃止（このページ自体がジョブ管理に統合） |

---

## P-05：提案事項

### 提案1: `assigned` ステータスの表示統一
`assignStore()` 変更後は新規レコードが `assigned` にならないが、過去データが残る。  
→ 表示時に `assigned` も「割り当て済み（旧）」ではなく「校正中」相当として扱う。  
→ DBマイグレーション不要、フロントのバッジ設定だけで対応済み。

### 提案2: 完了タブのデフォルト3か月表示
完了タブ初期表示は直近3か月（`completed_at >= 3ヶ月前の月初`）。  
→ 全期間表示は年月フィルター未選択のまま「すべて」オプションを追加する方法も可。  
→ 本計画では「直近3か月 + 年月フィルターで絞り込み」方式を採用。

### 提案3: 行クリックで詳細ページへ
現行 `Assignments/Index.vue` と同様、行クリック → `proof_coordinator.assignments.show` へ遷移。  
操作ボタンは `@click.stop` で行クリックと競合しない。

---

## 作業順序

```
STEP 1: routes/web.php に2ルートを追加
STEP 2: ProofRequestController.php を修正（assignStore + jobManagement + uncomplete）
STEP 3: ProofCoordinatorNavigationTabs.vue を修正（タブ名・URL変更）
STEP 4: Assignments/Index.vue を完全書き換え（ジョブ管理ページ）
STEP 5: npm run build → 動作確認依頼
```

---

## 影響なし（変更しないファイル）

- `ProofCoordinator/Assignments/Show.vue` — 詳細ページは変更なし
- `ProofCoordinator/Assignments/Edit.vue` — 編集ページは変更なし
- `ProofCoordinator/History/Index.vue` — 旧ページは残す（旧ルートから到達可能）
- `ProofCoordinator/Inbox/` — 受信ボックスは変更なし
- `ProofRequestController::complete()` — 変更なし（すでに正しく動作）
- `ProofRequestController::start()` — 変更なし（UI削除のみ、APIは保持）

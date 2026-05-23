# ジョブ管理ページ Claude向けプロンプトファイル
作成日: 2026-05-04

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「PROOF_JOBS_PROMPT.md を読んで実装を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトの「ジョブ管理ページ」実装作業を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/PROOF_JOBS_MANAGER.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/PROOF_JOBS_PLAN.md`（各作業の詳細仕様）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・完了の件数）
- 次に着手すべき推奨作業

作業は PROOF_JOBS_MANAGER.md に記載された「作業フロー（4ステップ）」と「安全ルール」に従って進めてください。

各P-xx作業の完了・進捗状況は必ず PROOF_JOBS_MANAGER.md に記録してください。
```

---

## 設計サマリー（Claude向け補足）

### 変更の全体像

proof-coordinator の「割り振り管理」（Assignments/Index.vue）と「案件校正履歴」（History/Index.vue）を
1ページ「ジョブ管理」に統合する。

#### 新ページの構成

```
ジョブ管理ページ（/proof-coordinator/jobs）
├── 検索UI
│   ├── テキスト検索（タイトル・案件名）+ 検索/クリアボタン
│   ├── ラジオ: 依頼日 / 締め切り日（年月フィルターの基準）
│   ├── 年月セレクター（monthOptions使用）
│   └── ソートピル: 案件ごと / 校正員ごと / 締め切りごと（クライアントサイドグループ化）
├── タブ
│   ├── 進行中のジョブ（status: assigned + in_progress）
│   └── 完了したジョブ（status: completed、デフォルト直近3か月）
└── テーブル（両タブ共通カラム）
    依頼日 | タイトル | 案件 | 依頼者 | 校正員 | 締め切り | 完了日 | ステータス | 操作
    ─── 操作列 ───
    進行中タブ: 「完了にする」ボタン（bg-green-600）
    完了タブ: 「未完了に戻す」ボタン（border border-gray-300）
```

#### ステータスフロー変更

```
変更前: pending → assigned → in_progress → completed
変更後: pending → in_progress → completed
```

`assignStore()` の `'status' => 'assigned'` を `'status' => 'in_progress'` に1行変更するだけ。
過去の `assigned` レコードは「進行中」タブに表示される（DB変更なし）。

#### 新規エンドポイント

```
GET  /proof-coordinator/jobs                                → proof_coordinator.jobs        → jobManagement()
PUT  /proof-coordinator/assignments/{proofRequest}/uncomplete → proof_coordinator.assignments.uncomplete → uncomplete()
```

### 重要な実装メモ

#### グループ化（クライアントサイド）

```js
// groupMode: 'project' | 'proofreader' | 'deadline'
const groupedRows = computed(() => {
    const rows = currentTab.value === 'active' ? props.activeRequests : props.completedRequests;
    const getKey = (req) => {
        if (groupMode.value === 'project')     return req.project_job?.title ?? '案件なし';
        if (groupMode.value === 'proofreader') return req.proofreader?.name  ?? '未割り当て';
        if (groupMode.value === 'deadline') {
            if (!req.deadline) return '締め切りなし';
            const d = new Date(req.deadline);
            return `${d.getFullYear()}年${d.getMonth()+1}月${d.getDate()}日`;
        }
        return '';
    };
    const map = new Map();
    for (const req of rows) {
        const key = getKey(req);
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(req);
    }
    return [...map.keys()].sort().map(key => ({ key, rows: map.get(key) }));
});
```

グループヘッダー行: `colspan="9"`, クラス `bg-pink-50 text-pink-800 font-semibold`

#### 検索・フィルター送信

```js
function doSearch() {
    router.get(route('proof_coordinator.jobs'), {
        tab:        currentTab.value,
        search:     searchInput.value,
        period:     periodInput.value,
        date_field: dateFieldInput.value,   // 'created_at' | 'deadline'
    }, { preserveState: false, replace: true });
}
```

タブ切り替え時も同じ `doSearch()` を呼ぶ（currentTab を変えてから実行）。

#### uncomplete エンドポイント

```js
function uncomplete(id) {
    if (!confirm('この校正を未完了に戻しますか？')) return;
    router.put(route('proof_coordinator.assignments.uncomplete', { proofRequest: id }), {}, {
        preserveScroll: true,
    });
}
```

#### LAYOUT_GUIDELINES.md 準拠チェック

- `AppLayout` のスロット: `#header`（h2のみ）, `#tabs`（ProofCoordinatorNavigationTabs）, デフォルト（コンテンツ）
- `#headerExtras` は今回不要（ページアクションボタンなし）
- カード: `<div class="rounded bg-white p-6 shadow">`
- ProofCoordinator テーマ色: ピンク系（`bg-pink-600`, `text-pink-600`）

### ナビゲーションタブ変更の注意

`ProofCoordinatorNavigationTabs.vue` の `active` prop を受け取るページ側も確認すること。

| ページ | 旧 active 値 | 新 active 値 |
|--------|------------|-------------|
| Assignments/Index.vue（書き換え後） | `"assignments"` | `"jobs"` |
| History/Index.vue（旧ページ・残存） | `"history"` | 旧ページはそのまま（ナビからリンクが消えるだけ） |

### 完了タブの「直近3か月」デフォルト

コントローラー側で `$period` が空かつ `$tab === 'completed'` のとき:
```php
$completedQuery->where('completed_at', '>=', now()->subMonths(3)->startOfMonth());
```

フロント側で「完了タブ・年月未選択時」は「直近3か月を表示中」という注記を表示する:
```html
<p v-if="currentTab === 'completed' && !periodInput" class="text-xs text-gray-400 mt-1">
  ※ 直近3か月を表示しています。すべて表示するには年月を選択してください。
</p>
```

---

## 変更対象ファイル（再掲）

```
app/Http/Controllers/ProofCoordinator/ProofRequestController.php
resources/js/Pages/ProofCoordinator/Assignments/Index.vue        ← 完全書き換え
resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue
routes/web.php
```

## 変更しないファイル（確認済み）

```
resources/js/Pages/ProofCoordinator/Assignments/Show.vue   （詳細ページ・変更なし）
resources/js/Pages/ProofCoordinator/Assignments/Edit.vue   （編集ページ・変更なし）
resources/js/Pages/ProofCoordinator/History/Index.vue      （旧ページ・残存）
resources/js/Pages/ProofCoordinator/Inbox/                 （受信ボックス・変更なし）
```

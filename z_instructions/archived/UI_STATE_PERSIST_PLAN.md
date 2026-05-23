# SunBWork UI状態永続化 設計計画書
作成日: 2026-05-04

> **管理書:** `z_instructions/UI_STATE_PERSIST_MANAGER.md`
> **プロンプト:** `z_instructions/UI_STATE_PERSIST_PROMPT.md`

---

## 概要・目的

ページをリロード・再訪問しても、ユーザーが最後に操作した状態（チェックボックス・タブ・グループモード・ソート順）を自動的に復元する。

**対象状態の種別:**
- **クライアント専用状態:** `ref(ハードコードデフォルト値)` で初期化されるもの → **localStorage 対象**
- **URL連動状態:** `ref(props.xxx)` で初期化されるもの → Inertia ルーターが管理するため **対象外**（ブックマーク可能、ブラウザの戻るで復元される）

---

## 技術方針

### 共通コンポーザブルの作成

**新規ファイル:** `resources/js/Composables/useUIState.js`

```js
import { ref, watch } from 'vue';

export function useUIState(key, defaultValue) {
    const raw = localStorage.getItem(key);
    let initial = defaultValue;
    if (raw !== null) {
        try { initial = JSON.parse(raw); } catch { initial = raw; }
    }
    const state = ref(initial);
    watch(state, (v) => localStorage.setItem(key, JSON.stringify(v)));
    return state;
}
```

**使用方法（各 Vue ファイル側）:**

```js
// 変更前
const hideCompleted = ref(true);

// 変更後
import { useUIState } from '@/Composables/useUIState';
const hideCompleted = useUIState('sbw_jobbox_hide_completed', true);
```

> **注意:** `useUIState` が返す値は `ref` オブジェクトなので、既存の `.value` アクセス・`v-model` はそのまま動作する。

### キー命名規則

`sbw_{ページ識別子}_{フィールド}` 形式

- `sbw_` プレフィックスで他ライブラリとの衝突を防ぐ
- 既存の `pj_index_view_mode` は後方互換性のため変更しない

---

## フェーズ1：高優先度（最も使われるページ）

### P-01 JobBox/Index.vue

**対象ファイル:** `resources/js/Pages/JobBox/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 |
|--------|------------|----------------|-----|
| `hideCompleted` | `ref(true)` | `sbw_jobbox_hide_completed` | boolean |
| `viewMode` | `ref('date')` | `sbw_jobbox_view_mode` | string |

**変更内容:**
1. `import { useUIState } from '@/Composables/useUIState'` を追加
2. 以下を置き換え:
   ```js
   // 変更前
   const hideCompleted = ref(true);
   const viewMode = ref('date');

   // 変更後
   const hideCompleted = useUIState('sbw_jobbox_hide_completed', true);
   const viewMode = useUIState('sbw_jobbox_view_mode', 'date');
   ```

---

### P-02 MyJobBox/Index.vue

**対象ファイル:** `resources/js/Pages/MyJobBox/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 |
|--------|------------|----------------|-----|
| `hideCompleted` | `ref(true)` | `sbw_myjobbox_hide_completed` | boolean |
| `viewMode` | `ref('date')` | `sbw_myjobbox_view_mode` | string |

**変更内容:** P-01 と同様のパターン

---

### P-03 Coordinator/ProjectJobs/Index.vue

**対象ファイル:** `resources/js/Pages/Coordinator/ProjectJobs/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 | 備考 |
|--------|------------|----------------|-----|------|
| `hideCompleted` | `ref(true)` | `sbw_coord_pj_hide_completed` | boolean | 新規追加 |
| `viewMode` | `localStorage.getItem('pj_index_view_mode')` | `pj_index_view_mode` | string | **既存実装を useUIState に移行** |
| `sortKey` | `ref('created_at')` | `sbw_coord_pj_sort_key` | string | 新規追加 |
| `sortDir` | `ref('desc')` | `sbw_coord_pj_sort_dir` | string | 新規追加 |

**変更内容:**
1. `import { useUIState } from '@/Composables/useUIState'` を追加（`ref` は継続使用）
2. 以下を置き換え:
   ```js
   // 変更前
   const hideCompleted = ref(true);
   const viewMode = ref(localStorage.getItem('pj_index_view_mode') || 'date');
   watch(viewMode, (v) => localStorage.setItem('pj_index_view_mode', v));
   const sortKey = ref('created_at');
   const sortDir = ref('desc');

   // 変更後
   const hideCompleted = useUIState('sbw_coord_pj_hide_completed', true);
   const viewMode = useUIState('pj_index_view_mode', 'date');  // 既存キーを維持
   const sortKey = useUIState('sbw_coord_pj_sort_key', 'created_at');
   const sortDir = useUIState('sbw_coord_pj_sort_dir', 'desc');
   ```

---

### P-04 Admin/ProjectJobs/Index.vue

**対象ファイル:** `resources/js/Pages/Admin/ProjectJobs/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 |
|--------|------------|----------------|-----|
| `hideCompleted` | `ref(true)` | `sbw_admin_pj_hide_completed` | boolean |
| `viewMode` | `ref('date')` | `sbw_admin_pj_view_mode` | string |
| `sortKey` | `ref('created_at')` | `sbw_admin_pj_sort_key` | string |
| `sortDir` | `ref('desc')` | `sbw_admin_pj_sort_dir` | string |

---

### P-05 Leader/ProjectJobs/Index.vue

**対象ファイル:** `resources/js/Pages/Leader/ProjectJobs/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 |
|--------|------------|----------------|-----|
| `hideCompleted` | `ref(true)` | `sbw_leader_pj_hide_completed` | boolean |
| `viewMode` | `ref('date')` | `sbw_leader_pj_view_mode` | string |
| `sortKey` | `ref('created_at')` | `sbw_leader_pj_sort_key` | string |
| `sortDir` | `ref('desc')` | `sbw_leader_pj_sort_dir` | string |

---

### P-06 ProofCoordinator/Jobs/Index.vue

**対象ファイル:** `resources/js/Pages/ProofCoordinator/Jobs/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 | 備考 |
|--------|------------|----------------|-----|------|
| `groupMode` | `ref('deadline')` | `sbw_proof_jobs_group_mode` | string | currentTab/search/period/dateField は props 連動のため対象外 |

---

## フェーズ2：中優先度

### P-07 ProofCoordinator/Inbox/Index.vue

**対象ファイル:** `resources/js/Pages/ProofCoordinator/Inbox/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 |
|--------|------------|----------------|-----|
| `groupMode` | `ref('deadline')` | `sbw_proof_inbox_group_mode` | string |

> `searchInput` は空文字初期化だが、毎回入力するものなので対象外。

---

### P-08 WorkloadAnalyzer/Index.vue

**対象ファイル:** `resources/js/Pages/WorkloadAnalyzer/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 | 備考 |
|--------|------------|----------------|-----|------|
| `sortKey` | `ref('deviation')` | `sbw_workload_sort_key` | string | |
| `sortDir` | `ref('desc')` | `sbw_workload_sort_dir` | string | |
| `viewMode` | `ref('total')` | `sbw_workload_view_mode` | string | |
| `employmentFilter` | `ref('all')` | `sbw_workload_employment_filter` | string | |

> `selectedYm` は props 連動（URL パラメータ）のため対象外

---

### P-09 WorkloadAnalyzer/CategoryRank.vue

**対象ファイル:** `resources/js/Pages/WorkloadAnalyzer/CategoryRank.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 | 備考 |
|--------|------------|----------------|-----|------|
| `selectedCategory` | `ref('total_pages')` | `sbw_category_rank_category` | string | |
| `employmentFilter` | `ref('all')` | `sbw_category_rank_employment_filter` | string | |

> `selectedYm` は props 連動のため対象外

---

### P-10 JobNotifications/Index.vue

**対象ファイル:** `resources/js/Pages/JobNotifications/Index.vue`

| 変数名 | 現在の初期値 | localStorageキー | 型 | 備考 |
|--------|------------|----------------|-----|------|
| `unreadOnly` | `ref(false)` | `sbw_notifications_unread_only` | boolean | |

> `viewMode` / `selectedDays` は props.filters 連動のため対象外

---

## フェーズ3：低優先度（追加候補）

以下はフェーズ2完了後に判断する。

| タスク | 対象ファイル | 変数 | キー候補 |
|--------|------------|------|---------|
| P-11 | `User/ProofStatus.vue` | `viewMode` | `sbw_user_proof_view_mode` |
| P-12 | `WorkRecord/Index.vue` | `sortOvertime` | `sbw_workrecord_sort_overtime` |
| P-13 | `SuperAdmin/Teams/Index.vue` | `showType` | `sbw_superadmin_teams_show_type` |

---

## 対象外（URL連動のため localStorage 不要）

以下のページ・変数はサーバーサイドまたは Inertia ルーターが状態を管理しているため対象外。

| ページ | 変数 | 理由 |
|--------|------|------|
| ProofCoordinator/Jobs/Index.vue | `currentTab`, `searchInput`, `periodInput`, `dateFieldInput` | props（URL パラメータ）連動 |
| Coordinator/ProgressSheetList/Index.vue | `searchQuery`, `selectedMonth`, `showComplete`, `viewMode` | 全て props.filters 連動 |
| JobNotifications/Index.vue | `viewMode`, `selectedDays` | props.filters 連動 |
| WorkloadAnalyzer/Index.vue | `selectedYm` | props 連動 |
| WorkloadAnalyzer/CategoryRank.vue | `selectedYm` | props 連動 |
| Diaries/Index.vue | `selectedDays` | props.filters 連動 |

---

## 実装チェックリスト（各タスク共通）

- [ ] 対象ファイルを Read ツールで確認（推測で作業しない）
- [ ] `import { useUIState } from '@/Composables/useUIState'` を追加
- [ ] 対象 `ref()` を `useUIState()` に置き換え
- [ ] 既存の `watch(xxx, v => localStorage...)` パターンがあれば削除
- [ ] `npm run build` を実行してエラーがないことを確認
- [ ] ブラウザで動作確認（値を変えてリロードして復元されるか）

---

## localStorage キー一覧（全量）

| キー | ページ | 変数 | デフォルト |
|------|--------|------|----------|
| `sbw_jobbox_hide_completed` | JobBox/Index | hideCompleted | true |
| `sbw_jobbox_view_mode` | JobBox/Index | viewMode | 'date' |
| `sbw_myjobbox_hide_completed` | MyJobBox/Index | hideCompleted | true |
| `sbw_myjobbox_view_mode` | MyJobBox/Index | viewMode | 'date' |
| `sbw_coord_pj_hide_completed` | Coordinator/ProjectJobs/Index | hideCompleted | true |
| `pj_index_view_mode` | Coordinator/ProjectJobs/Index | viewMode | 'date' |
| `sbw_coord_pj_sort_key` | Coordinator/ProjectJobs/Index | sortKey | 'created_at' |
| `sbw_coord_pj_sort_dir` | Coordinator/ProjectJobs/Index | sortDir | 'desc' |
| `sbw_admin_pj_hide_completed` | Admin/ProjectJobs/Index | hideCompleted | true |
| `sbw_admin_pj_view_mode` | Admin/ProjectJobs/Index | viewMode | 'date' |
| `sbw_admin_pj_sort_key` | Admin/ProjectJobs/Index | sortKey | 'created_at' |
| `sbw_admin_pj_sort_dir` | Admin/ProjectJobs/Index | sortDir | 'desc' |
| `sbw_leader_pj_hide_completed` | Leader/ProjectJobs/Index | hideCompleted | true |
| `sbw_leader_pj_view_mode` | Leader/ProjectJobs/Index | viewMode | 'date' |
| `sbw_leader_pj_sort_key` | Leader/ProjectJobs/Index | sortKey | 'created_at' |
| `sbw_leader_pj_sort_dir` | Leader/ProjectJobs/Index | sortDir | 'desc' |
| `sbw_proof_jobs_group_mode` | ProofCoordinator/Jobs/Index | groupMode | 'deadline' |
| `sbw_proof_inbox_group_mode` | ProofCoordinator/Inbox/Index | groupMode | 'deadline' |
| `sbw_workload_sort_key` | WorkloadAnalyzer/Index | sortKey | 'deviation' |
| `sbw_workload_sort_dir` | WorkloadAnalyzer/Index | sortDir | 'desc' |
| `sbw_workload_view_mode` | WorkloadAnalyzer/Index | viewMode | 'total' |
| `sbw_workload_employment_filter` | WorkloadAnalyzer/Index | employmentFilter | 'all' |
| `sbw_category_rank_category` | WorkloadAnalyzer/CategoryRank | selectedCategory | 'total_pages' |
| `sbw_category_rank_employment_filter` | WorkloadAnalyzer/CategoryRank | employmentFilter | 'all' |
| `sbw_notifications_unread_only` | JobNotifications/Index | unreadOnly | false |

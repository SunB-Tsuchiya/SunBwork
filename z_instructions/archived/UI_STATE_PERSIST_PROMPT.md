# SunBWork UI状態永続化 Claude向けプロンプトファイル
作成日: 2026-05-04

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「UI_STATE_PERSIST_PROMPT.md を読んで永続化作業を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれから SunBWork プロジェクトの「UI状態永続化」作業を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/UI_STATE_PERSIST_MANAGER.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/UI_STATE_PERSIST_PLAN.md`（各作業の詳細仕様・コード例）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は UI_STATE_PERSIST_MANAGER.md に記載された「作業フロー（5ステップ）」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各 P-xx 作業の完了・進捗状況は必ず UI_STATE_PERSIST_MANAGER.md に記録してください：
- 作業完了時: 進捗一覧のステータスを「✅ 完了」に更新し、作業ログに記録
- ビルド成功・ユーザー確認待ちの場合: ステータスを「🔨 実装中」に更新

【作業ペース厳守】
- STEP 4（動作確認依頼）の後は必ず止まる。ユーザーの「OK」を待ってから STEP 5 に進む。
- STEP 5 完了後は「次は P-xx（内容）が推奨です。進めますか？」と聞いて止まる。
- ユーザーが「yes」「OK」「進めて」などと言うまで、次の作業のファイル読み込みも設計提示も行わない。
```

---

## 設計サマリー（Claude向け補足）

### 目的

ページリロード・再訪問後も、ユーザーの最後の操作状態を自動復元する。
対象は「URL に反映されないクライアント専用の UI 状態」のみ（`ref(ハードコードデフォルト)` で初期化されるもの）。

### アーキテクチャ

1. **共通コンポーザブル** `resources/js/Composables/useUIState.js` を先に作成する
2. 各 Vue ページで `useUIState(key, default)` を使う（返り値は `ref` と同一の Ref オブジェクト）
3. キー命名: `sbw_{ページ識別子}_{フィールド}` （`pj_index_view_mode` のみ既存キーを維持）

### 実装テンプレート

**useUIState.js:**
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

**各ページ（変更例）:**
```js
// 変更前
import { ref } from 'vue';
const hideCompleted = ref(true);
const viewMode = ref('date');

// 変更後
import { ref } from 'vue';
import { useUIState } from '@/Composables/useUIState';
const hideCompleted = useUIState('sbw_jobbox_hide_completed', true);
const viewMode = useUIState('sbw_jobbox_view_mode', 'date');
```

テンプレート側（`v-model`・条件分岐など）の変更は**一切不要**。

### 対象外（URL連動のため localStorage 不要）

以下は Inertia ルーターまたはサーバーが状態管理するため触らない：
- `ref(props.xxx)` パターンで初期化されているもの
- ProgressSheetList の全フィルター、WorkloadAnalyzer の `selectedYm`、JobNotifications の `viewMode`/`selectedDays`

### localStorage キー全一覧（素早い確認用）

| キー | ページ | 変数 | デフォルト |
|------|--------|------|----------|
| `sbw_jobbox_hide_completed` | JobBox | hideCompleted | true |
| `sbw_jobbox_view_mode` | JobBox | viewMode | 'date' |
| `sbw_myjobbox_hide_completed` | MyJobBox | hideCompleted | true |
| `sbw_myjobbox_view_mode` | MyJobBox | viewMode | 'date' |
| `sbw_coord_pj_hide_completed` | Coordinator/ProjectJobs | hideCompleted | true |
| `pj_index_view_mode` | Coordinator/ProjectJobs | viewMode | 'date' |
| `sbw_coord_pj_sort_key` | Coordinator/ProjectJobs | sortKey | 'created_at' |
| `sbw_coord_pj_sort_dir` | Coordinator/ProjectJobs | sortDir | 'desc' |
| `sbw_admin_pj_hide_completed` | Admin/ProjectJobs | hideCompleted | true |
| `sbw_admin_pj_view_mode` | Admin/ProjectJobs | viewMode | 'date' |
| `sbw_admin_pj_sort_key` | Admin/ProjectJobs | sortKey | 'created_at' |
| `sbw_admin_pj_sort_dir` | Admin/ProjectJobs | sortDir | 'desc' |
| `sbw_leader_pj_hide_completed` | Leader/ProjectJobs | hideCompleted | true |
| `sbw_leader_pj_view_mode` | Leader/ProjectJobs | viewMode | 'date' |
| `sbw_leader_pj_sort_key` | Leader/ProjectJobs | sortKey | 'created_at' |
| `sbw_leader_pj_sort_dir` | Leader/ProjectJobs | sortDir | 'desc' |
| `sbw_proof_jobs_group_mode` | ProofCoordinator/Jobs | groupMode | 'deadline' |
| `sbw_proof_inbox_group_mode` | ProofCoordinator/Inbox | groupMode | 'deadline' |
| `sbw_workload_sort_key` | WorkloadAnalyzer | sortKey | 'deviation' |
| `sbw_workload_sort_dir` | WorkloadAnalyzer | sortDir | 'desc' |
| `sbw_workload_view_mode` | WorkloadAnalyzer | viewMode | 'total' |
| `sbw_workload_employment_filter` | WorkloadAnalyzer | employmentFilter | 'all' |
| `sbw_category_rank_category` | WorkloadAnalyzer/CategoryRank | selectedCategory | 'total_pages' |
| `sbw_category_rank_employment_filter` | WorkloadAnalyzer/CategoryRank | employmentFilter | 'all' |
| `sbw_notifications_unread_only` | JobNotifications | unreadOnly | false |

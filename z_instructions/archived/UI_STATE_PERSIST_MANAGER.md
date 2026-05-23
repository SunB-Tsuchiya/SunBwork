# SunBWork UI状態永続化 作業管理書
作成日: 2026-05-04

---

## ■ この管理書の使い方

**ユーザーへ:**
- 「P-01 を始めましょう」などと Claude に伝えれば、対応するページの実装が始まります
- 「フェーズ1を全部やって」でもOK（1タスクずつ確認しながら進めます）
- 作業完了ごとに進捗一覧を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（UI_STATE_PERSIST_MANAGER.md）を読む
2. `z_instructions/UI_STATE_PERSIST_PLAN.md` を読む（詳細仕様・コード例が記載されている）
3. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
4. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/UI_STATE_PERSIST_PLAN.md` | 設計詳細・対象ファイル・変数・キー一覧・コード例 |
| `CLAUDE.md` | プロジェクト全体ルール（必読） |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

各作業項目（P-xx）は以下のステップで進める。

```
STEP 1: 計画書を読む
  → UI_STATE_PERSIST_PLAN.md の該当 P-xx セクションを読む
  → 対象ファイルを Read ツールで確認する（推測で作業しない）

STEP 2: 設計・方針の提示
  → 変更対象のファイル・変数・キー名を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → useUIState.js がまだ存在しない場合は先に作成する
  → 計画書のコード例に従って実装する
  → Vue/JSファイルを変更したら npm run build を実行
  → エラーが出たら修正してから進む

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する（変更した変数・キー名）
  → ユーザーに「ブラウザで値を変えてからリロードし、復元されるか確認してください」と依頼

STEP 5: 完了記録
  → ユーザーから「OK」が出たらこの管理書の進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 「次は P-xx（内容）が推奨です。進めますか？」と聞いて必ずここで止まる
  → ユーザーが「yes」「OK」などと言うまで次の作業（ファイル読み込みを含む）を一切始めない
```

---

## ■ 安全ルール

- `useUIState.js` を作成してから各ページの変更を始める
- 既存の `localStorage.getItem / setItem` + `watch` パターンを `useUIState` に統一する際は必ず既存コードを削除する（重複しない）
- `pj_index_view_mode` キーは後方互換性のため変更しない
- `npm run build` 成功を必ず確認してから次タスクへ進む

---

## ■ 進捗一覧

### フェーズ0：共通コンポーザブル作成（最初に実施）

| ID | タスク | ステータス | 備考 |
|----|--------|----------|------|
| P-00 | `resources/js/Composables/useUIState.js` 作成 | ✅ 完了 | |

### フェーズ1：高優先度

| ID | 対象ページ | 永続化変数 | ステータス | 備考 |
|----|----------|----------|----------|------|
| P-01 | JobBox/Index.vue | hideCompleted, viewMode | ✅ 完了 | |
| P-02 | MyJobBox/Index.vue | hideCompleted, viewMode | ✅ 完了 | |
| P-03 | Coordinator/ProjectJobs/Index.vue | hideCompleted, viewMode（移行）, sortKey, sortDir | ✅ 完了 | viewMode は既存キー維持 |
| P-04 | Admin/ProjectJobs/Index.vue | hideCompleted, viewMode, sortKey, sortDir | ✅ 完了 | |
| P-05 | Leader/ProjectJobs/Index.vue | hideCompleted, viewMode, sortKey, sortDir | ✅ 完了 | |
| P-06 | ProofCoordinator/Jobs/Index.vue | groupMode | ✅ 完了 | |

### フェーズ2：中優先度

| ID | 対象ページ | 永続化変数 | ステータス | 備考 |
|----|----------|----------|----------|------|
| P-07 | ProofCoordinator/Inbox/Index.vue | groupMode | ✅ 完了 | |
| P-08 | WorkloadAnalyzer/Index.vue | sortKey, sortDir, viewMode, employmentFilter | ✅ 完了 | |
| P-09 | WorkloadAnalyzer/CategoryRank.vue | selectedCategory, employmentFilter | ✅ 完了 | |
| P-10 | JobNotifications/Index.vue | unreadOnly | ✅ 完了 | |

### フェーズ3：低優先度（フェーズ2完了後に判断）

| ID | 対象ページ | 永続化変数 | ステータス | 備考 |
|----|----------|----------|----------|------|
| P-11 | User/ProofStatus.vue | viewMode | ✅ 完了 | |
| P-12 | WorkRecord/Index.vue | sortOvertime | ✅ 完了 | |
| P-13 | SuperAdmin/Teams/Index.vue | showType | ✅ 完了 | |

---

## ■ 作業ログ

| 日付 | タスク | 変更ファイル | 結果 |
|------|--------|------------|------|
| 2026-05-04 | P-00 | resources/js/Composables/useUIState.js | 新規作成 |
| 2026-05-04 | P-01 | resources/js/Pages/JobBox/Index.vue | hideCompleted, viewMode を useUIState に移行 |
| 2026-05-04 | P-02 | resources/js/Pages/MyJobBox/Index.vue | hideCompleted, viewMode を useUIState に移行 |
| 2026-05-04 | P-03 | resources/js/Pages/Coordinator/ProjectJobs/Index.vue | hideCompleted, viewMode（既存watch削除）, sortKey, sortDir を useUIState に移行 |
| 2026-05-04 | P-04 | resources/js/Pages/Admin/ProjectJobs/Index.vue | hideCompleted, viewMode, sortKey, sortDir を useUIState に移行 |
| 2026-05-04 | P-05 | resources/js/Pages/Leader/ProjectJobs/Index.vue | hideCompleted, viewMode, sortKey, sortDir を useUIState に移行 |
| 2026-05-04 | P-06 | resources/js/Pages/ProofCoordinator/Jobs/Index.vue | groupMode を useUIState に移行 |
| 2026-05-04 | P-07 | resources/js/Pages/ProofCoordinator/Inbox/Index.vue | groupMode を useUIState に移行 |
| 2026-05-04 | P-08 | resources/js/Pages/WorkloadAnalyzer/Index.vue | sortKey, sortDir, viewMode, employmentFilter を useUIState に移行 |
| 2026-05-04 | P-09 | resources/js/Pages/WorkloadAnalyzer/CategoryRank.vue | selectedCategory, employmentFilter を useUIState に移行 |
| 2026-05-04 | P-10 | resources/js/Pages/JobNotifications/Index.vue | unreadOnly を useUIState に移行 |
| 2026-05-04 | P-11 | resources/js/Pages/User/ProofStatus.vue | viewMode を useUIState に移行 |
| 2026-05-04 | P-12 | resources/js/Pages/WorkRecord/Index.vue | sortOvertime を useUIState に移行 |
| 2026-05-04 | P-13 | resources/js/Pages/SuperAdmin/Teams/Index.vue | showType を useUIState に移行 |

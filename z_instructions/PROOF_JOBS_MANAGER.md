# ジョブ管理ページ 作業管理書
作成日: 2026-05-04

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「P-01から始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（PROOF_JOBS_MANAGER.md）を読む
2. `z_instructions/PROOF_JOBS_PLAN.md` を読む（詳細仕様が記載されている）
3. `CLAUDE.md` を確認する（最重要ルール）
4. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
5. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/PROOF_JOBS_PLAN.md` | ジョブ管理ページの詳細設計 |
| `z_instructions/LAYOUT_GUIDELINES.md` | レイアウトガイドライン |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

```
STEP 1: 計画書を読む
  → PROOF_JOBS_PLAN.md の該当項目を読み、仕様を把握する
  → 関連ファイルをコードで確認する（推測で作業しない）

STEP 2: 実装
  → 承認された設計に従って実装する
  → Vue/JSファイルを変更したら npm run build を実行
  → Artisan が必要な場合は docker compose exec 経由で実行

STEP 3: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする

STEP 4: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示する
```

### ⚠️ 安全ルール
- DBマイグレーションは不要（今回の変更はすべてコード変更のみ）
- 1つの作業が完了するまで次の作業に移らない
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する

---

## ■ 進捗一覧

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| P-01 | routes/web.php：新ルート2件追加 | ✅ 完了 | proof_coordinator.jobs・uncomplete |
| P-02a | ProofRequestController：assignStore() ステータス変更 | ✅ 完了 | assigned → in_progress |
| P-02b | ProofRequestController：jobManagement() メソッド追加 | ✅ 完了 | 進行中・完了 統合取得ロジック |
| P-02c | ProofRequestController：uncomplete() メソッド追加 | ✅ 完了 | 完了→進行中に戻す・pja100・pja_op も戻す |
| P-03 | ProofCoordinatorNavigationTabs.vue：タブ修正 | ✅ 完了 | 割り振り管理→ジョブ管理、案件校正履歴タブ削除 |
| P-04 | Assignments/Index.vue：完全書き換え | ✅ 完了 | Jobs/Index.vue として新規作成 |
| P-05 | npm run build・動作確認 | ✅ 完了 | ビルド成功（エラーなし） |

---

## ■ ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔨 実装中 | コード変更・ビルド中 |
| ✅ 完了 | ユーザー確認済み |
| ❌ スキップ | 不要と判断、またはユーザー判断でスキップ |

---

## ■ 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-04 | — | 設計書（PROOF_JOBS_PLAN.md）・管理書（PROOF_JOBS_MANAGER.md）・プロンプト（PROOF_JOBS_PROMPT.md）作成 | Claude |
| 2026-05-04 | P-01〜P-05 | routes/web.php・ProofRequestController.php・ProofCoordinatorNavigationTabs.vue・Jobs/Index.vue 実装・ビルド成功 | Claude |

---

## ■ 主要ファイルパス

```
app/Http/Controllers/ProofCoordinator/ProofRequestController.php   ← P-02a/b/c
resources/js/Pages/ProofCoordinator/Assignments/Index.vue           ← P-04（完全書き換え）
resources/js/Components/Tabs/ProofCoordinatorNavigationTabs.vue     ← P-03
routes/web.php                                                       ← P-01
```

---

## ■ よくある落とし穴

- `route()` の第2引数はオブジェクト形式: `route('proof_coordinator.jobs', { tab: 'active' })`
- さくら本番では `route()` 必須・ハードコードパス禁止
- 完了タブで年月未指定時は「直近3か月」がデフォルト（サーバー側でフィルター適用済み）
- `groupedRows` はクライアントサイド computed で計算（サーバーはフィルターのみ担当）
- `uncomplete()` では pja100 の `completed` フラグも必ず戻すこと

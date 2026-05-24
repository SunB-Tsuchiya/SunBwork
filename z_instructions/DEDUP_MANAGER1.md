# DEDUP_MANAGER1 — クライアント重複チェック 進捗管理

作成日: 2026-05-24

---

## 進捗一覧

| # | フェーズ | タスク | 担当ファイル | 状態 |
|---|---|---|---|---|
| 1 | Phase 1 | `normalizeClientName()` に 'hc' フラグ追加 | ClientController.php | ⬜ 未着 |
| 2 | Phase 2 | `duplicateCheckPage()` 実装 | ClientController.php | ⬜ 未着 |
| 3 | Phase 3 | `batchMerge()` 実装 | ClientController.php | ⬜ 未着 |
| 4 | Phase 4 | ルート追加（admin/leader/coordinator） | routes/web.php | ⬜ 未着 |
| 5 | Phase 5 | Index.vue に「重複チェック」ボタン追加 | Clients/Index.vue | ⬜ 未着 |
| 6 | Phase 6 | DuplicateCheck.vue 新規作成 | Clients/DuplicateCheck.vue | ⬜ 未着 |
| 7 | Phase 7 | npm run build | — | ⬜ 未着 |
| 8 | Phase 8 | 動作確認・ChangelogSeeder 追記 | — | ⬜ 未着 |

状態: ⬜ 未着 / 🔄 進行中 / ✅ 完了 / ❌ エラー

---

## 作業ログ

### 2026-05-24
- 設計フェーズ: DEDUP_PLAN1.md / DEDUP_MANAGER1.md / DEDUP1_PROMPT.md 作成。ユーザー確認待ち。

---

## 判断メモ

- `batchMerge()` のエラー処理: **スキップ方式**（1 ペア失敗しても他ペアは続行、最後にエラー件数を flash）
- デフォルト「残す」選択: **案件数 > 作成日（古い方）** の優先順
- ロール表示: admin / leader / coordinator 全員にボタン表示（既存 merge ルートも 3 ロール全て対応済み）
- coordinator は merge ルートあり（confirmed: web.php line 611）
- ページロード時にスキャン実行（ボタン押してスキャンではなく、直接ページ表示）

---

## リスク管理

| リスク | 対策 |
|---|---|
| normalizeClientName 変更で checkDuplicate の挙動変化 | より厳密になるだけ（カタカナ→ひらがな統一）で既存動作の破壊なし |
| ルート順序ミス（{client} に飲まれる） | Resource より前に duplicate-check と batch-merge を定義 |
| 大量クライアントで scan が重い | superadmin 向けに会社 ID フィルター検討（現状はスコープ内全件） |

# PROCESS_MANAGER1.md — 工程シート・項目リスト 進捗管理

作成日: 2026-05-14  
関連: PROCESS_PLAN1.md / PROCESS1_PROMPT.md

---

## 作業フロー

```
Phase 0（DB） → Phase 1（項目リスト）→ Phase 2（オートコンプリート）
→ Phase 3（工程シートバックエンド）→ Phase 4（工程シートUI）
→ Phase 5（テンプレート）→ Phase 6（時間集計）→ Phase 7（複製対応）→ Phase 8（PDF）
```

**Phase 0→1→2 は独立して先に完成できる。**  
Phase 3 以降は Phase 0 完了が前提。

---

## 進捗一覧テーブル

### Phase 0: DB マイグレーション

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-00a | workflow_templates テーブル | ✅ 完了 | |
| W-00b | project_item_entries テーブル | ✅ 完了 | |
| W-00c | workflow_sheets テーブル | ✅ 完了 | |
| W-00d | workflow_rows テーブル | ✅ 完了 | |
| W-00e | workflow_cells テーブル | ✅ 完了 | |

### Phase 1: 項目リスト機能

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-01 | ProjectItemEntry モデル | ✅ 完了 | |
| W-02 | ItemEntryController（Coordinator） | ✅ 完了 | |
| W-03 | ルート追加（項目リスト3本） | ✅ 完了 | |
| W-04 | ProjectJobs/Show.vue「項目リスト」タブ追加 | ✅ 完了 | Show.vue内にタブ+セクション+Script込み |
| W-05 | ItemListTab.vue 作成 | ✅ 完了 | Show.vue内に直接実装 |

### Phase 2: オートコンプリート連携

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-06 | AssignmentForm.vue オートコンプリート | ✅ 完了 | datalist方式。coordinator/userモード両対応 |
| W-07 | 台割フォーム オートコンプリート | ⬜ 未着手 | ProjectJobItem名入力フォームのパス要確認 |
| W-08 | マイジョブ作成 オートコンプリート | ✅ 完了 | AssignmentFormと同一コンポーネントのため自動対応 |

### Phase 3: 工程シート バックエンド

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-09 | WorkflowSheet/Row/Cell モデル | ✅ 完了 | WorkflowTemplate含む5モデル |
| W-10 | Coordinator/WorkflowSheetController | ✅ 完了 | |
| W-11 | Coordinator/WorkflowRowController | ✅ 完了 | import機能含む |
| W-12 | Coordinator/WorkflowCellController | ✅ 完了 | |
| W-13 | User/WorkflowSheetController | ✅ 完了 | |
| W-14 | User/WorkflowCellController | ✅ 完了 | |
| W-15 | ルート追加（工程シート・User側） | ✅ 完了 | 計18ルート |

### Phase 4: 工程シート UI

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-16 | ProjectJobs/Show.vue「工程シート」タブ追加 | ✅ 完了 | |
| W-17 | Coordinator/WorkflowSheets/Show.vue | ✅ 完了 | WorkflowCellEditor.vue含む |
| W-18 | User/WorkflowSheets/Show.vue | ✅ 完了 | |

### Phase 5: テンプレート機能

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-19 | WorkflowTemplate モデル | ✅ 完了 | |
| W-20 | WorkflowTemplateController | ✅ 完了 | |
| W-21 | ルート追加（テンプレート4本） | ✅ 完了 | |
| W-22 | WorkflowTemplates/Index.vue | ✅ 完了 | |
| W-23 | シート作成ダイアログにテンプレート選択UI | ✅ 完了 | Show.vueのモーダル内 |

### Phase 6: 時間集計表示

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-24 | 小計・合計 計算ロジック（Vue computed） | ✅ 完了 | |
| W-25 | 集計行 UI | ✅ 完了 | |

### Phase 7: 案件複製対応

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-26 | clone() に WorkflowSheet 複製追加 | ✅ 完了 | |
| W-27 | clone() に ProjectItemEntry 複製追加 | ✅ 完了 | |

### Phase 8: PDF/印刷（後で詳細設計）

| # | タスク | 状態 | 備考 |
|---|--------|------|------|
| W-28 | 印刷ビュー / PDF出力 | ⬜ 未着手 | フォーマット設計待ち |

---

## 状態凡例

| 記号 | 意味 |
|------|------|
| ⬜ 未着手 | まだ作業していない |
| 🔵 作業中 | 現在進行中 |
| ✅ 完了 | 実装・テスト済み |
| ⚠️ 保留 | 仕様未確定・依存待ち |
| ❌ 差し戻し | 問題あり・修正必要 |

---

## 未決事項トラッカー

| # | 項目 | 状態 | 回答日 |
|---|------|------|--------|
| U-01 | PDF/印刷フォーマット詳細（用紙・ISO基準内容） | ⚠️ 保留 | — |
| U-02 | マイジョブ作成フォームのファイルパス | ⚠️ 保留 | — |
| U-03 | 台割項目名入力フォームのファイルパス | ⚠️ 保留 | — |
| U-04 | WorkflowSheet に share_token 機能が必要か | ⚠️ 保留 | — |
| U-05 | Leaderが自分以外のセルを更新できるか | ⚠️ 保留 | — |

---

## 作業ログ

| 日付 | 内容 |
|------|------|
| 2026-05-14 | 設計確認QA完了（Q1〜Q8）。PLAN/MANAGER/PROMPT 3ファイル作成 |
| 2026-05-14 | Phase 0〜7 実装完了。DB5テーブル・モデル5・Controller7・Vue6ページ・ルート22本。ビルド確認OK。W-07（台割オートコンプリート）のみ残 |

---

## セッション引き継ぎメモ

新セッションを開始する際は **PROCESS1_PROMPT.md** をそのまま貼り付ける。  
作業を始める前に必ずこのファイルで進捗を確認し、完了したタスクを ✅ に更新すること。

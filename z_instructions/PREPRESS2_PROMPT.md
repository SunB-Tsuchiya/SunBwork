# PREPRESS2_PROMPT.md — 新セッション開始用プロンプト

---

## このファイルの使い方

新しい会話セッションでこの修繕作業を続ける際に、以下のプロンプトをコピーしてClaudeに送ること。

---

## セッション開始プロンプト

```
製版ボード 修繕計画２（Phase 1〜7 全完了）のバグ修正・追加改善を続けてください。

設計書: z_instructions/PREPRESS_PLAN2.md
進捗管理: z_instructions/PREPRESS_MANAGER2.md

PREPRESS_MANAGER2.md の進捗一覧を確認し、現在のステータスを把握した上で、
次に取り掛かるべき作業を実施してください。

実装前に必ず関連ファイルを読み込んでから着手してください。
```

**※ Phase 1〜7 はすべて完了済み（2026-05-23）。次セッションからは保守・追加要件を扱う。**

---

## 設計サマリー（引き継ぎ用）

### 対象ページ
- メイン: `resources/js/Pages/Prepress/Board.vue`
- フォーム: `resources/js/Pages/Prepress/Tickets/Create.vue`, `Edit.vue`, `Show.vue`, `Index.vue`
- コントローラー: `app/Http/Controllers/Prepress/BoardController.php`, `TicketController.php`

### 実装済み機能（Phase 1〜7 全完了）

**Ph1〜5 - 初期実装（2026-05-22）**
- DB: `prepress_tickets.sales_rep` + `outputting` ステータス追加
- フォーム: Create/Edit/Show/Index に担当営業フィールド追加
- Board.vue: 出稿中列・4列展開・グローバル検索・準備列カード/リスト切替
- CSV一括登録: `PrepressClientMatcher` + `analyzeCsv` + `importCsv` + 確認モーダル

**Ph6 - 営業担当管理（2026-05-22）**
- `prepress_sales_reps` テーブル + pivot テーブル新規作成
- `PrepresSalesRep` モデル + `SalesRepController`（CRUD + bulkStore）
- `SalesReps/Index.vue`（一覧・登録・編集・削除・一括登録）
- `prepress_tickets.sales_rep_id` FK追加
- CSV モーダルに担当営業選択 UI 追加（Board.vue / Tickets/Index.vue 両方）

**Ph7 - モーダル統合・インライン修正（2026-05-23）**
- 登録ボタン: ドロップダウン廃止 → `openCreateModal()` 直接呼出しに変更
- 登録モーダルに CSV 4択目追加（Board.vue / Tickets/Index.vue）
- `Tickets/Index.vue` に CSV機能全移植（Board.vue と同等）
- `apiClientCreate()` 重複チェック強化（`was_existing` フラグ）
- `saveInlineClient()`: `triggeredRawName` 方式で CSV内同名行に一括反映
- `was_existing` 時: 青 info ボックスで通知、モーダル OK クリックまで開放しない

### 重要な実装上の注意

- `route('prepress.tickets.analyzeCsv')` を `route('prepress.tickets.{ticket}')` より前に web.php で宣言する
- BoardController.index() の `get([...])` カラムリストに `sales_rep` を追加する
- `NormalizesCsvEncoding` トレイト（`app/Http/Controllers/Concerns/`）を CSV 処理で必ず使う
- Vue 変更後は必ず `npm run build`（プロジェクトルートで）

### サンプルCSVの場所
`z_instructions/prepress_sample.csv` （CP932エンコード確認済み）
```
No,受注No.,得意先,品名,営業担当
1,'4600152,さんぷる⑭　,イオン　B2ポスター　728号　,'深田智志
```
→ `'` 削除・丸数字削除・trim 後: `4600152`, `さんぷる`, `イオン B2ポスター 728号`, `深田智志`

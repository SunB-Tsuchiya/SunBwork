# PREPRESS_MANAGER2.md — 製版ボード 修繕計画２ 進捗管理

作成日: 2026-05-22

---

## 進捗一覧テーブル

| フェーズ | タスク | 担当ファイル | ステータス |
|--------|--------|------------|--------|
| Ph1-1 | migration: sales_rep カラム追加 | `migrations/...add_sales_rep...php` | ✅ 完了 |
| Ph1-2 | PrepressTicket: fillable, STATUS_LABELS, 定数追加 | `Models/PrepressTicket.php` | ✅ 完了 |
| Ph1-3 | TicketController: store/update に sales_rep 追加 | `Controllers/Prepress/TicketController.php` | ✅ 完了 |
| Ph1-4 | BoardController: outputting を許可ステータスに追加 | `Controllers/Prepress/BoardController.php` | ✅ 完了 |
| Ph1-5 | `php artisan migrate` 実行 | — | ✅ 完了 |
| Ph2-1 | Create.vue: 担当営業フィールド追加 | `Tickets/Create.vue` | ✅ 完了 |
| Ph2-2 | Edit.vue: 担当営業フィールド追加 | `Tickets/Edit.vue` | ✅ 完了 |
| Ph2-3 | Show.vue: 担当営業表示追加 | `Tickets/Show.vue` | ✅ 完了 |
| Ph2-4 | Index.vue: 担当営業列追加 | `Tickets/Index.vue` | ✅ 完了 |
| Ph3-1 | Board.vue: 出稿中列追加（COLUMNS定義） | `Board.vue` | ✅ 完了 |
| Ph3-2 | Board.vue: 列展開上限 2→4、デフォルト3列 | `Board.vue` | ✅ 完了 |
| Ph3-3 | Board.vue: グローバル検索ボックス追加 | `Board.vue` | ✅ 完了 |
| Ph3-4 | Board.vue: 伝票登録ボタン → モーダル統合（ドロップダウン廃止） | `Board.vue` | ✅ 完了 |
| Ph3-5 | BoardController: index() に sales_rep カラム追加 | `Controllers/Prepress/BoardController.php` | ✅ 完了 |
| Ph3-6 | `npm run build` | — | ✅ 完了 |
| Ph4-1 | Board.vue: 準備列 リスト/カード切替タブ | `Board.vue` | ✅ 完了 |
| Ph4-2 | `npm run build` | — | ✅ 完了 |
| Ph5-1 | PrepressClientMatcher.php: 正規化・マッチングサービス | `Services/PrepressClientMatcher.php` | ✅ 完了 |
| Ph5-2 | TicketController: analyzeCsv() 追加 | `TicketController.php` | ✅ 完了 |
| Ph5-3 | TicketController: importCsv() 追加 | `TicketController.php` | ✅ 完了 |
| Ph5-4 | web.php: 2ルート追加 | `routes/web.php` | ✅ 完了 |
| Ph5-5 | Board.vue: CSV確認モーダル実装 | `Board.vue` | ✅ 完了 |
| Ph5-6 | `npm run build` + 動作確認 | — | ✅ 完了 |
| Ph6-1 | migration: prepress_sales_reps + pivot テーブル | `migrations/...` | ✅ 完了 |
| Ph6-2 | PrepresSalesRep モデル作成 | `Models/PrepresSalesRep.php` | ✅ 完了 |
| Ph6-3 | SalesRepController 新規作成（CRUD + bulkStore） | `Controllers/Prepress/SalesRepController.php` | ✅ 完了 |
| Ph6-4 | SalesReps/Index.vue 新規作成（一覧・登録・編集・削除・一括登録） | `SalesReps/Index.vue` | ✅ 完了 |
| Ph6-5 | PrepressTicket: sales_rep_id fillable + salesRepEntry リレーション | `Models/PrepressTicket.php` | ✅ 完了 |
| Ph6-6 | TicketController: CSV importCsv() に sales_rep_id 保存 | `TicketController.php` | ✅ 完了 |
| Ph6-7 | BoardController: salesRepEntry eager load + apiClients() 追加 | `BoardController.php` | ✅ 完了 |
| Ph6-8 | Board.vue: CSV モーダルに担当営業選択 UI 追加 | `Board.vue` | ✅ 完了 |
| Ph6-9 | Tickets/Index.vue: CSV モーダルに担当営業選択 UI 追加 | `Tickets/Index.vue` | ✅ 完了 |
| Ph6-10 | web.php: sales-reps ルート群追加 | `routes/web.php` | ✅ 完了 |
| Ph6-11 | `php artisan migrate` 実行 | — | ✅ 完了 |
| Ph6-12 | `npm run build` | — | ✅ 完了 |
| Ph7-1 | Board.vue: showRegisterMenu 削除、＋ボタンを openCreateModal() に直接接続 | `Board.vue` | ✅ 完了 |
| Ph7-2 | Board.vue: 登録方法モーダルに CSV 4択目追加 | `Board.vue` | ✅ 完了 |
| Ph7-3 | Tickets/Index.vue: Board.vue と同等のCSVモーダル全実装（インライン登録含む） | `Tickets/Index.vue` | ✅ 完了 |
| Ph7-4 | BoardController: apiClientCreate() 重複チェック + was_existing 返却 | `BoardController.php` | ✅ 完了 |
| Ph7-5 | Board.vue: saveInlineClient() — triggeredRawName 方式・was_existing 対応 | `Board.vue` | ✅ 完了 |
| Ph7-6 | Tickets/Index.vue: saveInlineClient() — 同上 | `Tickets/Index.vue` | ✅ 完了 |
| Ph7-7 | `npm run build` | — | ✅ 完了 |

**ステータス凡例:** ⬜ 未着手 / 🔄 作業中 / ✅ 完了 / ❌ ブロック中

---

## 作業フロー

```
Ph1（DB基盤）
  ↓
Ph2（フォーム対応）  ←── Ph1完了後に開始
  ↓
Ph3（ボード基本改修）  ←── Ph1完了後に開始（Ph2と並行可）
  ↓
Ph4（準備列タブ）  ←── Ph3完了後
  ↓
Ph5（CSV一括登録）  ←── Ph3完了後（独立して実装可能）
```

---

## 作業ログ

| 日時 | 対応内容 | 備考 |
|------|---------|------|
| 2026-05-22 | 設計・進捗ファイル作成、サンプルCSV分析 | 設計合意完了 |
| 2026-05-22 | Phase 1〜5 全実装完了（修繕計画２ 初期機能） | ビルド成功 |
| 2026-05-22 | Phase 6 実装完了（営業担当管理・CSV改善） | ビルド成功 |
| 2026-05-23 | Phase 7 実装完了（モーダル統合・インラインクライアント修正・一括登録） | ビルド成功 |

---

## 重要な設計決定事項

1. **検索方式**: クライアントサイドフィルタリング（全データ取得済みのため、サーバーリクエスト不要）
2. **CSV確認フロー**: 一括解析→まとめて確認 (Option A)
3. **担当営業**: 手動フォームにも追加（テキスト文字列のまま保存）
4. **列展開上限**: 最大4列（5列中）
5. **出稿中カラー**: オレンジ系 (`border-orange-400 bg-orange-50`)
6. **準備列リスト**: 全項目表示（伝票番号・クライアント名+ID・案件名・担当営業・入稿日・下版日）、並べ替え可
7. **CSV クライアント未マッチ時**: インラインで検索選択 or 新規登録（ページ遷移なし、モーダル内処理）

### Phase 6 追加設計決定事項（2026-05-22）

8. **営業担当テーブル**: `prepress_sales_reps`（id, name, company, timestamps）+ `prepress_sales_rep_department`（pivot: sales_rep_id, department_id）
9. **既存 `departments` テーブル活用**: 情報出版=1, 製版=2, オンデマンド=3 をそのまま利用
10. **`prepress_tickets.sales_rep_id`**: nullable FK、既存 `sales_rep` 文字列カラムは残す（両立）
11. **会社選択 UI**: プリセット2社（サンエー印刷/サン・ブレーン）+ 自由入力モード切替
12. **アクセス権限**: Leader / Coordinator / Prepress（製版部署）が管理可能
13. **営業担当名正規化**: `normalizeName()` = 全角スペース→半角、連続スペース→1つ、trim（PHP: `preg_replace('/[\x{3000}\s]+/u', ' ', $name)` / JS: `s.replace(/[　\s]+/g, ' ').trim()`）
14. **CSV モーダル幅**: max-w-7xl に拡大
15. **インライン登録 API**: `POST prepress/api/clients`（BoardController.apiClientCreate）/ `POST prepress/api/sales-reps`（SalesRepController.apiCreate）

### Phase 7 追加設計決定事項（2026-05-23）

16. **登録ボタン方式**: ドロップダウンではなくモーダルを直接開く — showRegisterMenu を廃止し `openCreateModal()` を直接呼ぶ
17. **CSV 4択目**: 登録方法選択モーダルに「CSV一括登録」を4つ目の選択肢として追加
18. **Tickets/Index.vue に CSV機能移植**: Board.vue と同等の CSVモーダル・インライン登録機能を Tickets/Index.vue にも実装（コード重複だがページ分離のため許容）
19. **インラインクライアント propagation キー**: `raw_client_name`（CSV原文）を使用。モーダル内でクライアント名を編集しても正しく同名行に反映される
20. **was_existing フロー**:
    - バックエンド: `Client::where('name', $name)->first()` で重複チェック → 存在すれば `was_existing: true` + 既存レコードを返す
    - フロントエンド: 青 info ボックスでメッセージ表示、モーダルは「OK」クリックまで開いたまま
    - 製版部署への紐付けは重複時も `syncWithoutDetaching` で行う
21. **営業担当一括登録 (bulkStore)**:
    - ルート: `POST prepress/sales-reps/bulk`（`{salesRep}` ルートより前に定義すること）
    - 会社・部署はフォームから共有、名前のみテキストエリア（改行区切り）
    - チップカラー: 緑=新規OK / 赤=DB重複 / 黄=テキスト内重複
    - PHP: `whereIn` で一括事前チェック → ループで `create()` + `syncWithoutDetaching()`

---

## テスト確認項目

### Phase 1完了後
- [ ] DBマイグレーション成功確認
- [ ] outputting ステータスへのドラッグ&ドロップが動作すること

### Phase 2完了後
- [ ] 伝票新規作成で担当営業が保存されること
- [ ] 伝票編集で担当営業が表示・更新されること

### Phase 3完了後
- [ ] 出稿中列が作業中と完了の間に表示されること
- [ ] グローバル検索でクライアントID検索できること（部分一致）
- [ ] グローバル検索で担当営業名検索できること（部分一致）
- [ ] 4列同時展開できること
- [ ] 伝票登録ドロップダウンの3メニューが表示されること

### Phase 4完了後
- [ ] 準備列でリスト/カード切替ができること
- [ ] リスト表示で各列ヘッダーで並べ替えができること
- [ ] localStorage に表示モードが保存されること

### Phase 5完了後
- [ ] サンプルCSV（CP932/CP932 BOM付き）がエラーなく読み込めること
- [ ] 丸数字・シングルクォートが除去されること
- [ ] 完全一致クライアントが自動マッチされること
- [ ] 候補ありのケースで選択UIが表示されること
- [ ] 未マッチでインライン検索・新規登録ができること
- [ ] 一括保存後にボードに伝票が追加されること

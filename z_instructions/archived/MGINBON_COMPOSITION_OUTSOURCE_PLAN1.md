# MGinbon 組版外注・初校発注書 設計1

作成日: 2026-09-30
状態: 実装中

## 目的

FileMakerの「組版外注」と「組版外注_初校 御中」を、銀本制作進行の既存正規化データから再構成する。
利用頻度が低い場合も独立データを増やさず、LIST・MyJobと同じ担当・工程情報を表示する。

## 対象画面

### 組版外注

- 学校・試験回ごとの左側識別情報を表示する。
- 国語・算数・社会・理科の列に、入稿、初校組、再校組、三校組、四校組を並べる。
- 工程担当は既存工程 `initial_operation`、`reproof_operation`、`third_operation`、`fourth_operation` を正本とする。
- 発注日・納品日は専用列を新設せず、既存作業パッケージの `assigned_at`、`completed_at` を表示する。
- 入稿日は既存 `manuscript_received_on` を使用する。
- 表示用画面とし、この画面からの担当・日付編集は初期実装外とする。

### 組版外注_初校 御中

- 初校組工程だけを学校・媒体ごとの発注書形式で表示する。
- 左側に、みくにコード、日能研コード、分類、学校名・試験回、媒体を表示する。
- 教科ごとに初校組担当、登録日、完了日を表示する。
- 画面上の補助情報として、入稿日、文字入力担当／完了日、作図担当／完了日を既存値から表示する。
- 選択したレコードをA4縦で印刷・PDF保存できるようにする。
- 印刷時にも左側のコード・分類・学校名・媒体を必ず含める。
- FileMakerのWindows／Mac別帳票は作らず、共通印刷CSSで扱う。

## データ対応

| 表示 | 既存データ |
| --- | --- |
| 学校・コード・分類 | `mginbon_production_units` |
| 媒体・教科 | `mginbon_items` / `mginbon_item_subjects` |
| 入稿 | `manuscript_received_on` |
| 文字 | `text_input`、`text_input_completed_on` |
| 作図 | `drawing`、`drawing_completed_on` |
| 初校組 | `initial_operation` |
| 再校組 | `reproof_operation` |
| 三校組 | `third_operation` |
| 四校組 | `fourth_operation` |
| 発注相当日 | 作業パッケージ `assigned_at` |
| 納品相当日 | 作業パッケージ `completed_at` |

日時はJST表示にし、日付のみを `MM/DD` 形式で表示する。未登録値は空欄とし、推測値を補完しない。

## 変更予定

- `app/Http/Controllers/Coordinator/MGinbonLedgerController.php`
- `resources/js/Pages/Coordinator/MGinbon/LedgerIndex.vue`
- `resources/js/Components/MGinbon/CompositionOutsourceLayout.vue`（新規）
- `resources/js/Components/MGinbon/CompositionInitialOrderLayout.vue`（新規）
- `resources/js/Components/MGinbon/CompositionInitialOrderRecord.vue`（新規）
- MGinbon Feature test
- `z_instructions/CONSOLIDATED_09_domain_rules.md`
- `database/seeders/ChangelogSeeder.php`

DB migration・新規保存APIは追加しない。

## 検証

- 2レイアウトをクエリで選択できる。
- 4教科の担当、登録日、完了日が既存データから表示される。
- 対象工程がない教科は空欄になる。
- 初校発注書の画面と印刷に左側識別情報が表示される。
- 選択解除したレコードが印刷されない。
- 印刷がDBを更新しない。
- `php artisan test --filter=MGinbon`
- `npm run build`
- `git diff --check`

## 初回実装外

- FileMaker専用の発注日・納品日の新規保存。
- 組版外注画面からの担当・日付編集。
- Sakura本番配備。

# 案件一括作成機能 提案書

**作成日:** 2026-04-20
**対象ロール:** Coordinator
**関連ページ:** `resources/js/Pages/Coordinator/ProjectJobs/`

---

## 背景・課題

Coordinatorは短いスパンで数十件の細かい案件を登録しなければならない。現状は1件ずつ手入力が必要で非常に手間がかかる。チームメンバーは変わらずクライアントだけ変わるケースが多い。以下の3機能でこの課題を解決する。

---

## 実装する3機能

### 機能1：既存案件のワンクリック複製

#### 概要
案件詳細ページ（Show.vue）に「この案件を複製する」ボタンを追加。既存案件の全情報（リーダー・サブCo・チームメンバー・サイズ・詳細等）を引き継いだ新規案件を作成する。

#### UIフロー
1. 案件詳細ページ（`coordinator.project_jobs.show`）に「複製して新規作成」ボタンを追加
2. クリックすると確認ダイアログ「この案件をもとに新規案件を作成します。よいですか？」
3. OKで複製ページ（または通常の Create.vue をプリフィル状態で表示）に遷移
4. 伝票番号・タイトル・クライアントは空（または「コピー - 元タイトル」）で表示し、修正して保存
5. チームメンバーも複製される（project_team_members レコードを新案件にコピー）

#### 技術要件
- バックエンド: `ProjectJobController@clone` メソッド追加
- ルート: `POST coordinator.project_jobs.{projectJob}.clone`
- ProjectTeamMember・project_job_coordinators も複製対象に含める
- さくら本番ルール: `Arr::pull($data, 'schedule')` を忘れずに

---

### 機能2：CSVテンプレートによる案件一括登録（メイン機能）

#### 概要
「案件テンプレート」を作成・保存し、そのテンプレートに基づいたサンプルCSVをダウンロード。CSVに変動項目を入力してアップロードするだけで複数案件を一括登録できる。チームメンバーもテンプレートに含め、CSV登録と同時に自動セットされる。

#### UIフロー

**A. 案件一覧ページ（Index.vue）のボタン追加**
```
[新規作成]  [テンプレートから一括作成]  ← 追加
```

**B. 一括作成ハブページ（新規: BulkCreate.vue）**
- 上部に2つのタブ or セクション:
  - 「テンプレート管理」（テンプレートの作成・編集・保存・読込）
  - 「CSV取込」（CSVアップロード・プレビュー・登録実行）

**C. テンプレート管理セクション**
- 既存 ProgressTemplate の保存・読込パターンを流用（`is_shared` 対応で共有可）
- テンプレートに設定できる固定項目:
  | 項目 | 備考 |
  |------|------|
  | リーダー（user_id） | Coordinatorから選択 |
  | サブCoordinator（sub_coordinator_ids） | 複数選択可 |
  | サイズ（size_id） | 紙媒体/デジタルフィルタあり |
  | チームメンバー | ユーザー + 担当区分（assignment）のセット |
  | 詳細（detail） | 案件共通の説明文 |
- テンプレート名・共有フラグも設定
- 「テンプレートを保存」「テンプレートを読み込む」ボタン

**D. サンプルCSVダウンロード**
- テンプレートを選択/作成すると「サンプルCSVをダウンロード」ボタンが有効化
- サンプルCSVには「テンプレートで固定されていない項目」だけが列として入る
- テンプレートにリーダーを固定した場合 → CSVにリーダー列は不要
- 最低限 CSVに含まれる列（テンプレートで固定できない/しない場合）:
  | CSV列 | 内容 | 必須/任意 |
  |-------|------|----------|
  | jobcode | 伝票番号（数字とハイフン） | 任意 |
  | title | 案件タイトル | 必須 |
  | client_id | クライアントID | client_nameと片方必須 |
  | client_name | クライアント名（部分一致検索） | client_idと片方必須 |
  | page_count | 総ページ数 | 任意 |
- テンプレートで固定しなかった項目（リーダー等）はCSV列として追加される

**E. CSVアップロード・プレビュー**
- `admin.users.csv.upload` / `CsvPreview.vue` のパターンを流用
- アップロード後にプレビュー表示（エラー行をハイライト、修正可能なら赤字表示）
- client_name 指定時はDB検索して client_id を自動解決。候補が複数あれば警告
- 問題なければ「登録実行」ボタン → 一括 INSERT

**F. 登録後**
- 登録件数と案件リストを表示
- 「案件一覧へ戻る」ボタン

#### 技術要件（バックエンド）
- 新コントローラ: `App\Http\Controllers\Coordinator\BulkProjectJobController`
  - `index()` → BulkCreate.vue をレンダリング
  - `downloadSample(Request $request)` → テンプレートIDを受けてCSVレスポンス
  - `preview(Request $request)` → CSVパース・バリデーション結果をJSON返却
  - `store(Request $request)` → バリデーション済みデータを一括 INSERT
- 新モデル（またはProgressTemplateを流用）: `ProjectJobTemplate`
  - `name`, `description`, `is_shared`, `created_by`
  - `fixed_fields` (JSON): `{user_id, sub_coordinator_ids, size_id, detail}` 等
  - `team_members` (JSON): `[{user_id, assignment_id, ...}, ...]`
- ルート（`coordinator` prefix 内）:
  ```
  GET  project-jobs/bulk-create         → bulk_project_jobs.index
  GET  project-jobs/bulk-create/sample  → bulk_project_jobs.sample
  POST project-jobs/bulk-create/preview → bulk_project_jobs.preview
  POST project-jobs/bulk-create/store   → bulk_project_jobs.store

  GET  project-job-templates            → project_job_templates.index
  POST project-job-templates            → project_job_templates.store
  PUT  project-job-templates/{t}        → project_job_templates.update
  DELETE project-job-templates/{t}      → project_job_templates.destroy
  ```
- さくら本番: `Arr::pull($data, 'schedule')` を各案件ストア時に適用

---

### 機能3：クライアントプリセット（自動入力補助）

#### 概要
案件作成フォーム（Create.vue）または一括作成フォームで、クライアントを選択した瞬間に「このクライアントの直近案件の設定」を自動セットする。手入力をほぼゼロにするための補助機能。

#### UIフロー
1. Create.vue のクライアント選択後に、APIを叩いてそのクライアントの直近案件を取得
2. 「前回の設定を引き継ぎますか？（リーダー: 〇〇、メンバー: △△ 他3名、サイズ: B5）」という確認バナーを表示
3. 「引き継ぐ」を押すと各フィールドに自動セット
4. 「使わない」を押すと何もしない

#### 技術要件
- バックエンド: `GET coordinator/clients/{client}/last-job-config` → JSON
  - 返却: `{user_id, sub_coordinator_ids, size_id, page_count, detail, team_members[]}`
- フロントエンド: Create.vue の `selectClient` 関数の直後で呼び出す

---

## 派生案件グループ複製機能（拡張機能）

### 概要
「sample」という書籍案件があったとき、「sample-ebook」「sample-html」など、タイトルを変換してグループ派生案件をまとめて作る。

### 実装案（2案）

**A案：案件一覧でのチェック&一括複製**
1. 案件一覧（Index.vue）にチェックボックスを追加
2. 複数選択後「選択案件を一括複製」ボタン
3. 置換ルール入力モーダル:
   - 「〇〇」を「△△」に置換（タイトルの文字列置換）
   - または「末尾に -△△ を追加」
4. プレビュー表示（元タイトル → 新タイトル）確認後に一括作成

**B案：案件グループ化＋グループ一括複製**
1. 案件に「グループID」を持たせる（新カラム: `group_id`）
2. 案件詳細でグループを設定・管理
3. グループ単位で「グループを複製（タイトル変換ルール付き）」

**推奨:** A案（シンプルで実装コストが低く、グループ概念なしで実現できる）

---

## 実装優先順位（提案）

| 優先度 | 機能 | 理由 |
|--------|------|------|
| ① | ワンクリック複製 | 最小実装、即効性が高い |
| ② | クライアントプリセット | 手間削減の即効性 |
| ③ | CSVテンプレート一括登録 | 大量登録のメイン手段 |
| ④ | 派生案件グループ複製 | 拡張機能として後続フェーズ |

ただしCSVへのこだわりがある場合は①②③を同一フェーズで実装する。

---

## 参照すべき既存実装

| 参照先 | 利用する箇所 |
|--------|------------|
| `resources/js/Pages/Admin/Users/CsvUpload.vue` | CSVアップロードUI |
| `resources/js/Pages/Admin/Users/CsvPreview.vue` | CSVプレビューUI |
| `app/Http/Controllers/Coordinator/ProgressTemplateController.php` | テンプレート保存・読込ロジック |
| `app/Models/ProgressTemplate.php` | テンプレートモデル設計の参考 |
| `resources/js/Pages/Coordinator/ProjectJobs/Create.vue` | 案件登録フォーム（クライアント検索含む） |
| `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` | 複製ボタン追加先 |
| `resources/js/Pages/Coordinator/ProjectJobs/Index.vue` | 一括作成ボタン・チェックボックス追加先 |

---

## 設計確認済み事項

- テンプレートにチームメンバー（担当工程含む）まで含める → **確定（A案採用）**
- CSVはメイン機能として複製機能と同等の扱い → **確定**
- さくら本番に `project_jobs.schedule` カラムは存在しない → `Arr::pull($data, 'schedule')` 必須
- ナビゲーションは必ず `route()` を使う（ベースパス `/members` のため）
- CSRFトークンは `meta[name="csrf-token"]` から取得

---

## 次のステップ

1. この提案書を叩き台にClaudeと質疑応答し、詳細設計書（DB設計・API仕様・コンポーネント設計）を作成
2. 詳細設計書を `z_instructions/BULK_PROJECT_CREATE_DESIGN.md` に保存
3. 設計書を参照させたうえで実装Claudeが順に実装

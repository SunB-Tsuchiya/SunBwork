# 案件一括作成機能 設計依頼プロンプト

このファイルをClaudeに読み込ませ、設計作業を依頼するためのプロンプトです。

---

## Claudeへの指示

以下を必ず先に読んでください：

1. `/home/tchirosb/SunBWork/CLAUDE.md`
2. `/home/tchirosb/SunBWork/z_instructions/BULK_PROJECT_CREATE_PROPOSAL.md`（提案書・要件定義）
3. 以下の既存実装ファイル（仕組みを把握するため）:
   - `resources/js/Pages/Coordinator/ProjectJobs/Index.vue`
   - `resources/js/Pages/Coordinator/ProjectJobs/Create.vue`
   - `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`
   - `resources/js/Pages/Admin/Users/CsvUpload.vue`
   - `resources/js/Pages/Admin/Users/CsvPreview.vue`
   - `app/Http/Controllers/Coordinator/ProjectJobController.php`
   - `app/Http/Controllers/Coordinator/ProgressTemplateController.php`
   - `app/Models/ProgressTemplate.php`
   - `app/Models/ProjectJob.php`
   - `routes/web.php`（coordinator prefix 周辺）

---

## 依頼内容

提案書（`BULK_PROJECT_CREATE_PROPOSAL.md`）に基づき、以下3機能の**詳細設計書**を作成してください。

### 対象機能
1. **ワンクリック複製** — 案件詳細から既存案件を丸ごとコピーして新規案件を作成
2. **CSVテンプレート一括登録** — テンプレートで固定項目を設定し、変動項目だけCSVで入力して複数案件を一括登録（チームメンバーもテンプレートに含める）
3. **クライアントプリセット** — クライアント選択時に直近案件の設定を自動セットする補助機能

### 設計書に含める内容

#### 1. DB設計
- 新規テーブル（またはカラム追加）の一覧
- 各テーブルのカラム定義（型・NULL可否・デフォルト値）
- マイグレーションファイル名の案

#### 2. API / ルート設計
- 追加するルート一覧（HTTP メソッド・URL・ルート名・コントローラメソッド）
- 各エンドポイントのリクエスト/レスポンス仕様

#### 3. コンポーネント設計
- 新規作成・変更するVueファイルの一覧とその役割
- 各ページのprops定義
- コンポーネント間のデータフロー

#### 4. CSVフォーマット仕様
- テンプレートの固定項目に応じてCSV列が動的に変わる仕組みの詳細
- サンプルCSV生成ロジック
- バリデーションルール（client_name → client_id 解決含む）

#### 5. 実装順序
- フェーズ分けと各フェーズで実装するファイルの一覧

---

## 作業ルール（CLAUDE.md より）

- 不明点・仕様の曖昧さがあれば必ず**1つずつ**質問してから作業を開始する
- 設計書は `z_instructions/BULK_PROJECT_CREATE_DESIGN.md` に保存する
- さくら本番の制約を必ず考慮する（`Arr::pull($data, 'schedule')`、`route()` 必須、CSRFはmetaタグから取得）
- `project_jobs.schedule` カラムはさくら本番に存在しない

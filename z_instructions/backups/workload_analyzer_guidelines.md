# Workload Analyzer — ガイドラインと修正履歴

このドキュメントは `Workload Analyzer` 機能に関する実装概要、API 仕様、注意事項、修正履歴、開発・テスト手順をまとめたものです。

## 概要

Workload Analyzer は、組織内のメンバーごとの作業量を計測・可視化する機能です。ページ単位（assigned/self）のページ数、推定作業時間、各種係数（ステージ/サイズ/種別/難易度）を用いてポイントを算出し、偏差値やランキングを表示します。

実装は Laravel (Controller: `app/Http/Controllers/Leader/WorkloadAnalyzerController.php`) と Inertia + Vue 3 を使用しています。フロントは `resources/js/Pages/WorkloadAnalyzer` 配下にあります。

## 主な機能

- Index: 会社／部署／チーム単位でメンバーの集計を表示（`index()`）。
- Show: 個人の詳細分析ページ（`show()`）。
- Settings: 係数編集ページ（`settings()` / `saveSettings()`）。

## 重要なデータソースとモデル

- ProjectJobAssignment, ProjectJobAssignmentByMyself: 作業エントリ（ページ数、estimated_hours、stage_id, size_id, work_item_type_id, difficulty_id など）。
- Stage, Size, WorkItemType, Difficulty: 各カテゴリの定義（`coefficient` カラムを持つ）。

## Settings 機能（最近の追加）

- フロント: `resources/js/Pages/WorkloadAnalyzer/Settings.vue`
    - 各テーブル（stages, sizes, types, difficulties）をセクション表示。
    - 各レコードに対して coefficient のドロップダウン（0.00 〜 3.00、0.25 刻み）を提供。
    - テーブル毎に保存ボタンを設置し、変更をそのテーブルだけ保存できる。
    - 保存は axios による XHR で行い、ページのリロードは発生しない。保存完了時にトーストを表示する。

- サーバ: `WorkloadAnalyzerController::saveSettings(Request $request)`
    - 受け取るペイロード: { table: 'stages'|'sizes'|'types'|'difficulties', rows: [{id, coefficient}, ...] }
    - 各行を逐次 `Model::find(id)->coefficient = value` で保存（トランザクションでラップ）。
    - XHR（Inertia/axios）からの保存には JSON を返す。

## API / ルーティング

- leader/workload-analyzer (GET) -> index
- leader/workload-analyzer/settings (GET) -> settings
- leader/workload-analyzer/settings (POST) -> saveSettings
- leader/workload-analyzer/{user} (GET) -> show

注意: static route (`settings`) は parameterized route (`{user}`) より先に定義する必要があります。もし route cache を使っている場合は `php artisan route:clear` を忘れずに。

## 既知の注意点・対策

1. Schema 差分
    - 環境により `project_job_assignments` に `assigned_to` や `desired_start_date` カラムが存在しない場合があります。コントローラでは `Schema::hasColumn()` を使ってガードしています。

2. ルーティングの衝突
    - `workload-analyzer/{user}` が `settings` をキャッチしてしまう問題があったため、静的ルートを先に定義しました。より堅牢にするには `{user}` ルートに数字制約を付ける（->where('user','[0-9]+')）ことを推奨します。

3. 保存時のナビゲーション
    - 以前は `Inertia.post` からの保存でサーバが `redirect()` を返してページ遷移が発生していました。現在は XHR を検出して JSON を返す実装に変更、フロントは `axios.post` を使いリロードを防止しています。

4. UX
    - ドロップダウンの幅が狭い問題を修正（Tailwind の幅クラス `w-28` を追加）。必要に応じてレスポンシブ対応を追加してください。

## 修正履歴（要約）

- [2025-09-23] settings ページ作成（`Settings.vue`）、AppLayout に header slot の表示追加。
- [2025-09-23] `WorkloadAnalyzerController::settings()` と `saveSettings()` を追加。`saveSettings()` をテーブル単位保存に変更。
- [2025-09-23] `saveSettings()` を XHR に対して JSON を返すよう修正し、ページ遷移を行わないようにした。
- [2025-09-23] `Settings.vue` を axios ベースに変更し、保存成功時に `useToasts` を用いてトーストを表示するように。
- [2025-09-23] `assigned_to` を参照するクエリに `Schema::hasColumn` ガードを追加（旧スキーマ互換対応）。
- [2025-09-23] ドロップダウン幅を `w-28` に変更してボタンと被らないようにした。

## テスト手順

1. リーダー権限でログイン
2. `/leader/workload-analyzer/settings` を開く
3. ステージ／サイズ／種別／難易度のいずれかで値を変更して保存ボタンを押す
    - トーストが表示され、ページ遷移しないことを確認
    - DB の該当テーブルで `coefficient` 値が更新されていることを確認
4. 必要なら `storage/logs/laravel.log` を監視してサーバ側のエラーを確認

## 今後の提案

- 保存後、サーバから再フェッチして UI を厳密に同期（現在は内部 state を信頼している）。
- `{user}` ルートに数字制約を入れてルーティングの安全性を高める。
- 個別行の編集／追加／削除 UI の提供（現在は既存行の coefficient 更新のみ対応）。
- E2E テスト（Pest + Playwright など）を追加して認証付き保存フローを自動化。

---

作業報告用メモはここまでです。追加で載せたい情報やフォーマットの指定があれば教えてください。

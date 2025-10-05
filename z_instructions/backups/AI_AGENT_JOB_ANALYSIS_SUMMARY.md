# ジョブ分析 (Analysis) — 変更と実装まとめ

日時: 2025-10-04
ブランチ: `totaljob03`

## 概要

このドキュメントは、プロジェクトの「ジョブ分析（Analysis）」機能追加と関連変更を次の AI エージェントが継続・保守するための説明書です。実施したのは主に以下:

- サーバー側: `ProjectJobController::analysis` アクションの追加（`assignmentEvents` にステージ情報を含める）
- ルーティング: `coordinator.project_jobs.analysis` ルート追加
- フロントエンド: `resources/js/Pages/Coordinator/ProjectJobs/Analysis.vue` 新規作成／編集
- フロントエンド: `resources/js/Pages/Coordinator/ProjectJobs/Show.vue` の不要テーブル移動と「ジョブ分析」ボタン追加

ページはプロジェクトのレイアウト規約（`z_instructions/layout_guideline_for_ai_agent.md`）に従っています。

---

## 変更ファイル一覧と目的

- `app/Http/Controllers/Coordinator/ProjectJobController.php`
    - 追加: `analysis(ProjectJob $projectJob)` アクション
    - 役割: `assignmentEvents` を構築して Inertia に渡す。各イベントに `assignment_id`, `assignment_name`（users.assignment_id → assignments.name を優先して解決）、`user_id`, `user_name`, `status_name`, `start`, `end`, `stage_id`, `stage_name` を含める。

- `routes/web.php`
    - 追加: `GET project_jobs/{projectJob}/analysis` を `ProjectJobController@analysis` に割り当て。ルート名: `coordinator.project_jobs.analysis`。

- `resources/js/Pages/Coordinator/ProjectJobs/Analysis.vue`
    - 新規作成／編集: ジョブ分析ページ。
    - 主要機能:
        - サーバー渡しの `assignmentEvents` を受け取る
        - `assignmentGroups`：割り当て名（assignments.name をキー）ごとにイベントをグルーピングし、割り当て内の合計分数を計算
        - `stageSummaries`：ステージ別合計
        - `workerSummaries`：作業者別合計
        - `stageGroups`：ステージ別の詳細イベントリスト
    - UI: 各割り当てごとに詳細イベントの一覧テーブルを表示。ページヘッダー右側に「詳細に戻る」ボタン（`coordinator.project_jobs.show` へ遷移）を設置。

- `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`
    - 既存: 画面内で表示していた割り当てイベントテーブルを `Analysis.vue` に移動。
    - 追加: 「ジョブ分析」ボタンを追加して Analysis ページへ遷移。

---

## データ契約（contract）

フロントエンドに渡される `assignmentEvents` の各要素は次のキーを含みます（存在しない場合は null/未設定）：

- `assignment_id` (number|null)
- `assignment_name` (string) — 優先: users.assignment_id → assignments.name、なければ project_job_assignment.title
- `project_job_id` (number)
- `user_id` (number|null)
- `user_name` (string|null)
- `status_name` (string|null)
- `start` (ISO 日時文字列) — 可能なら DB の `starts_at` 等
- `end` (ISO 日時文字列)
- `stage_id` (number|string 'none')
- `stage_name` (string|null)

フロントエンドは `start` と `end` を Date にパースし、分単位の差分を計算して合計しています（負の差は 0 にクランプ）。

---

## フロント実装の重要ポイント

- `assignmentGroups` computed
    - 割り当て名をキーにして行を集め、`totalMinutes` を合計。
    - 各割り当て内の `items` を日付でソート。
    - 以前は `usersMap` を作りユーザー別集計も行っていましたが、UI 要件で削除し、現在は割り当てごとの詳細イベントテーブルのみ表示（必要なら復帰可能）。

- `stageSummaries`, `workerSummaries`, `stageGroups`
    - フロント側で分単位合計と件数を計算。

- レイアウト
    - `AppLayout.vue` の `py-12` → `max-w-7xl mx-auto sm:px-6 lg:px-8` → `rounded bg-white p-6 shadow` 構造に従う。

- ナビゲーション
    - ヘッダー右に目立つ「詳細に戻る」ボタンを追加。Inertia の `router.get(route('coordinator.project_jobs.show', job.id))` で遷移。

---

## ビルドと検証

1. 依存インストール（初回）:
    - `npm install`（または yarn）
2. ビルド:
    - 開発: `npm run dev`
    - 本番ビルド: `npm run build`
3. 検証ポイント:
    - Analysis ページを開き、割り当てごとのテーブルがページ上部に表示されること
    - 各行の「開始/終了/作業合計」が正しく表示されること
    - ヘッダー右の「詳細に戻る」ボタンで `Show` ページに戻れること

---

## 注意点 / 既知の問題

- `tsconfig.json` / `jsconfig.json` に関する警告: ワークスペースに大文字小文字だけが違うファイル名（例: `resources/js/components/ComposeModal.vue` と `resources/js/Components/ComposeModal.vue`）が存在すると TypeScript のコンパイル警告が出ます。必要であればファイル名を統一してください。

- データの整合性: `assignmentEvents` の各要素が必ず `start` と `end` を持つとは限らないため、フロントはパース不可の日時や null を安全に扱うように実装されています。

- 大量データの場合: クライアント側で全行を集計するとブラウザ負荷が高くなる可能性があります。必要ならコントローラ側で集計して Inertia に渡す設計に切り替えてください。

---

## 継続作業案（優先順）

1. 割り当ての優先表示順をサポート
    - 固定の優先配列（例: `['進行管理','オペレーター','校正']`）で `assignmentGroups` をソートして上位に表示する
2. 折りたたみ（accordion）を入れて割り当てごとの表示を開閉可能にする
3. CSV エクスポート：フィルタ付きで割り当て単位/ステージ単位の集計 CSV を吐く
4. サーバー側集計：大量データ時のパフォーマンス対策として controller 側で `SUM`/`COUNT` を計算して渡す
5. バッジやカラーリング：`z_instructions/layout_guideline_for_ai_agent.md` のスタイルに合わせる

---

## 開発者向けメモ（次の AI エージェントへ）

- まず `z_instructions/first_prompt.md` と `z_instructions/layout_guideline_for_ai_agent.md` を読んでレイアウト・命名規則を確認してください。
- 変更を加える前に `npm run build` を実行して現在のビルドが通ることを確認してください。
- 大きな集計やフィルタ機能はサーバー側（Eloquent クエリ）に移す設計を検討してください。

---

もしこのドキュメントに追加してほしい点（例: 実際の controller のコード抜粋、具体的な SQL クエリ例、期待される `assignmentEvents` のサンプル JSON 等）があれば指示ください。必要ならそのサンプルもこのファイルに追記します。

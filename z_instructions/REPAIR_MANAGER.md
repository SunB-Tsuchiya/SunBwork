# SunBWork 修繕 作業管理書
作成日: 2026-04-21
更新日: 2026-04-21

---

## ■ この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「B-01を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（REPAIR_MANAGER.md）を読む
2. `z_instructions/REPAIR_PLAN.md` を読む（詳細仕様が記載されている）
3. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
4. 以下の「作業フロー」に従って進める

---

## ■ 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/REPAIR_PLAN.md` | 修繕計画の詳細仕様・対象ファイル・対応内容 |
| `z_instructions/LAYOUT_GUIDELINES.md` | レイアウトガイドライン（L-01で作成予定） |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | ドメインルール（権限・JobBox・通知等） |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## ■ 作業フロー（Claude はこの手順を厳守すること）

各作業項目（B-xx / L-xx / F-xx / G-xx）は以下のステップで進める。

```
STEP 1: 計画書を読む
  → REPAIR_PLAN.md の該当項目を読み、仕様を把握する
  → 関連ファイルをコードで確認する（推測で作業しない）

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → 承認された設計に従って実装する
  → Vue/JSファイルを変更したら npm run build を実行
  → Artisan が必要な場合は docker compose exec 経由で実行

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする（「〜を確認してください」）

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示する
```

### ⚠️ 安全ルール（必ず守ること）
- STEP 2 でユーザーの確認なしに実装を始めない
- DB マイグレーションを伴う変更は必ず別途確認を取る
- 1つの作業が完了するまで次の作業に移らない
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する

---

## ■ 進捗一覧

### フェーズ1：バグ修正

| ID | 内容 | ステータス | 担当フェーズ | 備考 |
|----|------|-----------|------------|------|
| B-01 | カレンダーの予定削除が失敗する | ✅ 完了 | Phase 1 | ProjectSchedulePolicy に delete() 追加 |
| B-02 | カレンダーの日付が1日ずれて表示される | ✅ 完了 | Phase 1 | ProjectSchedule モデルのキャストを date:Y-m-d に変更 |
| B-03 | スケジュール編集後に予定が二重表示される | ✅ 完了 | Phase 1 | submitScheduleUpdate の router.reload() を削除 |
| B-04 | 「ジョブ詳細を開く」ボタンが反応しない | ✅ 完了 | Phase 1 | ProgressSheets/Show.vue のルート名を coordinator. プレフィックス付きに修正 |
| B-05 | 「未完了にする」後もジョブ一覧では完了扱い | ✅ 完了 | Phase 1 | uncompleteAssignment に status_id=null 追加・leader ロールの権限不足も修正 |
| B-06 | ジョブ一覧の完了フィルターが機能しない | ✅ 完了 | Phase 1 | hideCompleted を localStorage で永続化（検索・ページ遷移後もチェック状態を維持） |
| B-07 | 案件内割り当て一覧→ジョブ一覧が空になる | ✅ 完了 | Phase 1 | CoordinatorNavigationTabs の「ジョブ一覧」タブを常に coordinator.jobbox (global) へ遷移するよう変更。案件固有 jobbox は JAM なし割り当てを返さないため空になっていた |

### フェーズ2：レイアウトガイドライン

| ID | 内容 | ステータス | 担当フェーズ | 備考 |
|----|------|-----------|------------|------|
| L-01 | レイアウトガイドライン文書の作成 | ✅ 完了 | Phase 2 | z_instructions/LAYOUT_GUIDELINES.md を作成（2026-04-22） |
| L-02 | ガイドラインの全ページ適用 | ✅ 完了 | Phase 2 | 優先度高6ファイル＋追加2件（BulkCreate・ProjectCalendar）＋Leader/User 6ファイル適用・npm run build 成功（2026-04-22） |

### フェーズ3：機能改善（中規模）

| ID | 内容 | ステータス | 担当フェーズ | 備考 |
|----|------|-----------|------------|------|
| F-01 | ジョブステータスフローの刷新（送信→確認済み→セット→完了） | ✅ 完了 | Phase 3 | 送信/確認済み/セット/完了の4段階を既存カラムで実装。EventController の誤全件完了バグも同時修正（2026-04-24） |
| F-02 | 進行管理表テンプレートに「戻る」ボタン追加 | ✅ 完了 | Phase 3 | L-02（Coordinator/ProgressTemplates/Edit.vue）で対応済み |
| F-03 | 台割行の見出しグループ後の追加が不可な問題 | ✅ 完了 | Phase 3 | after_id挿入・行追加UI刷新（ColumnTreeEditor準拠）・子行↑↓並び替え追加 |
| F-04 | テンプレート見出し・行の編集機能 | ✅ 完了 | Phase 3 | Enter キー保存対応・保存時の未確定行ラベル警告トースト追加 |
| F-05 | 行管理で追加時に末尾文字が省略される | ✅ 完了 | Phase 3 | @keydown.enter → @keyup.enter に変更（IME確定後に発火）|
| F-06 | 台割行の「複製」機能追加 | ✅ 完了 | Phase 3 | ProgressRowController::duplicate() 追加・複製ボタン UI・blur 自動確定も同時対応 |
| F-07 | 「案件詳細に戻る」で進行管理表タブを開く | ✅ 完了 | Phase 3 | 戻るリンクに ?tab=progress を付与・Show.vue の activeTab 初期値を URL パラメータから読むよう変更 |
| F-08 | スケジュールの直接入力（カレンダー以外） | ✅ 完了 | Phase 3 | 案件詳細の概要タブにインライン編集モード追加（追加・編集・削除・ソート）・CSV出力（進捗列なし・日付YYYY-MM-DD）・CSV取込（YYYY/MM/DD・時刻付き・空行対応） |
| F-09 | 「進行表に紐づける」を紐づけ済みなら操作不可 | ✅ 完了 | Phase 3 | linkOptions/linkCell の権限チェックに leader ロールを追加（403修正）。紐づけ後の詳細連携は別途検討予定（後述） |
| F-10 | カレンダー週間プランナービューの追加 | ✅ 完了 | Phase 3 | project_schedule_week_postsテーブル追加・週間プランナーUI・多段スレッド掲示板・ロールカラー表示・User側カレンダー参照対応（2026-04-24） |

### フェーズ4：大規模機能開発

| ID | 内容 | ステータス | 担当フェーズ | 備考 |
|----|------|-----------|------------|------|
| G-01 | スケジュールと進行管理表の連動 | ✅ 完了 | Phase 4 | project_job_items テーブル追加・項目タブ・カレンダー自動表示・進行表読み込み・双方向同期（2026-04-24） |
| G-02 | 案件複製機能の拡張（日付シフト・進行表構造含む） | ✅ 完了 | Phase 4 | スケジュール（日付null・progress=0）・進行表構造（担当者null）を複製。DBマイグレーション不要（2026-04-24） |

### 別プロジェクト

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| P-01 | 子案件機能（別伝票番号）計画書の作成 | 🔲 未着手 | 別の Claude セッションで進行 |

---

## ■ 作業ログ

### 2026-04-24（続き）
- **F-10** 完了
- **変更ファイル:**
  - `database/migrations/2026_04_24_000001_create_project_schedule_week_posts_table.php`（新規）
  - `database/migrations/2026_04_24_000002_add_parent_id_to_project_schedule_week_posts_table.php`（新規）
  - `app/Models/ProjectScheduleWeekPost.php`（新規）
  - `app/Http/Controllers/Coordinator/ProjectScheduleWeekPostController.php`（新規）
  - `app/Http/Controllers/User/ProjectScheduleWeekPostController.php`（新規）
  - `routes/web.php`（Coordinator・User の week-posts ルート追加）
  - `resources/js/Components/ProjectWeekPlanner.vue`（新規：週間プランナーUI・多段スレッド掲示板・ロールカラー）
  - `resources/js/Components/ProjectCalendar.vue`（ビュー切替ボタン・readonly/weekPostsUrl prop追加）
  - `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue`（weekPostsUrl を渡すよう更新）
  - `resources/js/Pages/User/ProjectJobs/Show.vue`（スケジュール欄にProjectCalendar追加）

### 2026-04-24
- **G-02** 完了
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProjectJobController.php`（clone() にスケジュール・進行管理表・台割行・セルの複製を追加。progress=0、日付・担当者はnull）
  - `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`（複製確認ダイアログのメッセージ更新）
- **F-01** 完了
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php`（store() の send_immediately で accepted=true を削除）
  - `app/Http/Controllers/ProjectJobs/JobBoxController.php`（markRead/JAM作成/返信フローから accepted=true を削除、予定セット時に accepted=true 追加、show() と apiMarkRead() に受信者チェック追加）
  - `app/Http/Controllers/EventController.php`（自動完了ロジックを supersedes_assignment_id 指定の1件のみに修正）
  - `resources/js/Pages/JobBox/Index.vue`（getAssignmentStatus 新ロジック）
  - `resources/js/Pages/Coordinator/JobBox/Index.vue`（getUnifiedStatus 新ロジック）
  - `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`（historyGetStatus 新ロジック）
  - `resources/js/Pages/JobBox/Show.vue`（onMounted の apiMarkRead を受信者のみ呼ぶよう制限）
- **F-09** 完了
- **原因:** `linkOptions` / `linkCell` の権限チェックに `leader` ロールが含まれておらず 403 が返っていた
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（linkOptions・linkCell の権限配列に `'leader'` を追加）
  - `resources/js/Components/LinkProgressCellModal.vue`（エラーハンドリング改善：fetchError 表示・コンソールログ追加）

### 2026-04-23
- **F-09** 実装（一部）
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php`
  - `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/Show.vue`

### 2026-04-22（続き）
- **F-06** 完了
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressRowController.php`（duplicate() 追加）
  - `routes/web.php`（duplicate ルート追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（複製ボタン・blur 自動確定）
- **F-04・F-05** 完了
- **変更ファイル:**
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（行ラベル入力に Enter 保存追加・@keyup.enter でIME末尾文字欠落修正・保存時の未確定行ラベル警告トースト・saveColumnConfig に syncRowsFromPage 追加）

### 2026-04-22
- **F-03** 実装（ビルド成功・ユーザー確認待ち）
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressRowController.php`（store() に `after_id` パラメータ追加・order シフト処理）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（各行の下に「＋ ここに行を挿入」ボタン追加）
- **L-01** 完了（LAYOUT_GUIDELINES.md 作成）
- **L-02** 完了（全ページへのガイドライン適用）
- **変更ファイル:**
  - `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`（`#header` に戻るボタン追加、スティッキー内「一覧に戻る」削除）
  - `resources/js/Pages/Coordinator/ProjectJobs/BulkCreate.vue`（`#header` に戻るボタン追加、コンテンツ内見出し行削除）
  - `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue`（`#header` に戻るボタン追加、「キャンセル」→「テンプレート一覧に戻る」）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（`#header` に戻るボタン追加、ツールバー内戻るボタン削除）
  - `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue`（タイトル変更、`#header` に戻るボタン追加）
  - `resources/js/Components/ProjectCalendar.vue`（コンテンツ内「プロジェクト詳細に戻る」ボタン削除）
  - `resources/js/Pages/Coordinator/JobBox/Index.vue`（タイトル変更、新規作成ボタンを `#headerExtras` に移動）
  - `resources/js/Pages/JobBox/Index.vue`（同上）
  - `resources/js/Pages/Leader/Teams/Show.vue`（`#header` に戻るボタン追加、`max-w-4xl` 削除、コンテンツ下部の「一覧へ戻る」削除）
  - `resources/js/Pages/Leader/Teams/Create.vue`（`#header` に戻るボタン追加、`max-w-3xl` 削除）
  - `resources/js/Pages/Leader/Teams/Edit.vue`（`#header` に戻るボタン追加）
  - `resources/js/Pages/Leader/ProjectJobs/Show.vue`（戻るリンクのスタイルをガイドライン統一ボタンに変更）
  - `resources/js/Pages/User/ProjectJobs/Show.vue`（`#header` に戻るボタン追加、コンテンツ内「一覧に戻る」削除）
  - `resources/js/Pages/User/ProgressSheets/Show.vue`（`#header` に戻るボタン追加、ツールバー内戻るボタン削除）
  - `resources/js/Pages/User/ProofJobs/Show.vue`（`#header` に戻るボタン追加、コンテンツ内「戻る」削除）
  - `resources/js/Pages/User/ProofJobs/Set.vue`（戻るリンクを左端・ガイドラインスタイルに修正）

### 2026-04-21
- **B-01〜B-03** 完了（カレンダーバグ修正一括対応）
- **追加実装（B-01セッション内）:**
  - eventDrop（ドラッグ移動をDBに保存）
  - eventResize（リサイズをDBに保存）
  - CSV出力・CSV取込（`coordinator.project_schedules.csv_export/import`）
  - 削除・ドラッグ・リサイズの確認ダイアログ廃止
  - ドラッグ範囲選択で予定作成モーダルに日付を自動セット
  - 週ビュー（timeGridWeek）を廃止し月ビューのみに統一
  - ホバーポップアップ（タイトル・日付・メモを表示）
- **変更ファイル:**
  - `app/Policies/ProjectSchedulePolicy.php`
  - `app/Models/ProjectSchedule.php`
  - `app/Http/Controllers/Coordinator/ProjectSchedulesController.php`
  - `routes/web.php`
  - `resources/js/Pages/Coordinator/ProjectSchedules/Calendar.vue`
  - `resources/js/Components/ProjectCalendar.vue`

---

## ■ ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔍 調査中 | コード調査・仕様確認中 |
| 📝 設計中 | 設計・方針をユーザーと確認中 |
| 🔨 実装中 | コード変更・ビルド中 |
| ✅ 完了 | ユーザー確認済み |
| ⏸ 保留 | 依存関係・仕様未定のため一時停止 |
| ❌ スキップ | 不要と判断、またはユーザー判断でスキップ |

---

## ■ 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-04-21 | — | 計画書（REPAIR_PLAN.md）・管理書（REPAIR_MANAGER.md）作成 | Claude |

---

## ■ 次の推奨作業

**現時点の推奨:** G-01（スケジュールと進行管理表の連動）または P-01（子案件機能計画書）

---

## ■ 検討事項（修繕計画完了後）

### 進行表紐づけ機能の拡張
- **背景:** F-09 で「進行表に紐づける」機能を実装・修正済み
- **課題:** 紐づけた後の活用方法が未定
- **検討内容:**
  - 紐づけ後に進行表セルのデータ（案件タイトル・ページ数など）でジョブ詳細を自動更新する仕組み
  - 双方向の連動（ジョブ → セル、セル → ジョブ）の設計
- **優先度:** 修繕計画（B/L/F/G フェーズ）完了後に別途計画を策定する


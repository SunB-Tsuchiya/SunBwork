# 進行表 V2 刷新 作業管理書
作成日: 2026-04-25
更新日: 2026-04-27（V-14確認待ち・カレンダーから進行表作成機能追加）

---

## 進捗一覧

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| V-01 | DBマイグレーション（6カラム追加） | ✅ 完了 | progress_cells×4 / progress_sheets×1 / project_schedules×1 |
| V-02 | `worker`型セル Backend API対応 | ✅ 完了 | モデル/Controller/JobBox完了連携/ルート追加 |
| V-03 | `worker`型セル Frontend実装 | ✅ 完了 | ProgressCell/ProgressTable/Show.vue/Controller更新 |
| V-04 | `schedlink`型セル Backend API対応 | ✅ 完了 | complete()にschedlink対応・bulkUpdateにschedlinkケース追加・show()にschedule_name/schedule_completed_at追加 |
| V-05 | `schedlink`型セル Frontend実装 | ✅ 完了 | ProgressCell/ProgressTable/Show.vue更新・ルート名二重プレフィックスバグ修正 |
| V-06 | 締め切りアラート色 + 完了率バッジ | ✅ 完了 | アラート色はV-03/V-05で実装済み・完了率バッジ追加 |
| V-07 | 既存シート変換機能 | ✅ 完了 | プレビューAPI(GET)+変換API(PUT)の2段構成。全データ引き継ぎ確認済み（2026-04-25） |
| V-08 | テンプレートへの新セル型対応 | ✅ 完了 | PREVIEW_TYPE_LABELSにworker/schedlink追加（2026-04-25） |
| V-09 | セルメモ・コメント機能 | ✅ 完了 | メモUI（1行表示+ホバーポップアップ+著者バッジ）実装完了（2026-04-25） |
| V-10 | User向け「自分の担当セル一覧」 | ✅ 完了 | JobBoxタブ追加・検索/期間/グループUI・cell_type未設定バグ修正・進行表からの発信後リダイレクト修正（2026-04-25） |
| V-11 | 進行表の読み取り専用共有URL | ✅ 完了 | share_token発行・公開ページ・トークン無効化対応（2026-04-26） |
| V-12 | Coordinator横断レポート | ✅ 完了 | スコープ=project_team_members・フィルター（担当者/案件/完了状況/締め切り/完了日）・色分け（2026-04-26） |
| V-13 | 校正列のworker型対応 | ✅ 完了 | proof_userを2カラムUI（担当＋ジョブ登録＋完了管理）に刷新・ユーザー確認済み（2026-04-26）。その後 proof_v2 型として独立分離 |
| V-14 | カレンダー予定の完了表示 | 🔨 実装中 | 完了済み予定をグレー＋✓プレフィックスで表示・予定詳細モーダルに「未完了に戻す」ボタン追加・ビルド成功・ユーザー確認待ち |
| V-15 | セット方式削除・列追加UI刷新 | ✅ 完了 | 「セット方式で初期化」ボタン削除・buildV2ColumnConfig を worker+proof_v2 構成に変更・ColumnTreeEditor に「＋組版＋校正セット」プリセットボタン追加（2026-04-26） |
| V-16 | 進行表の印刷機能 | ✅ 完了 | Coordinator/User/共有URL の3か所で印刷ボタン追加・自動印刷なし・手動「印刷を実行」方式（2026-04-26） |

---

## 作業ログ

### 2026-04-27（カレンダーから進行表作成機能追加）
- **新機能** 進行表作成モーダルに「カレンダー（スケジュール）から作成」モードを追加
- **作成される進行表の構造:** 列=開始日（date型）・終了日（date型）、行=選択した各スケジュール項目、終了日→行の締め切り（deadline）
- **UI:** チェックで選択、項目名・開始日・終了日をインライン編集可、カレンダーの日付を自動読込
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（`store()` に `initial_rows` パラメータ追加・行＋日付セルを一括作成）
  - `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`（`newSheetUseV2` → `newSheetMode` に置換・3択ラジオ追加・カレンダー行テーブルUI追加・`createSheet()` にカレンダーモード追加）

### 2026-04-26（タイムゾーン修正: 校正管理者タイムテーブルからのジョブ依頼 9時間ズレ）
- **Bug fix** 校正管理者が校正者のタイムテーブルからジョブを依頼する際に時刻が9時間ズレる問題を修正
- **原因:** `Event::create/update` に `start`（JST文字列）のみ渡し `starts_at` を省略していたため、`setStartAttribute` ミューテータが JST 文字列を UTC TIMESTAMP に直接書き込み9時間ズレが発生
- **変更ファイル:**
  - `app/Http/Controllers/ProofCoordinator/CalendarController.php`
    - `update()` の `$event->update(...)` に `'starts_at' => $rawStart, 'ends_at' => $rawEnd` を追加（UTC文字列でミューテータを上書き）
    - `syncEventFromSchedule()` の `Event::create(...)` に `'starts_at' => $rawStart, 'ends_at' => $rawEnd` を追加（同上）
- **備考:** `$rawStart`/`$rawEnd` は両メソッドで `getRawOriginal('starts_at')` により既に計算済みだったため変数追加不要

### 2026-04-26（V-15完了・proof_v2型追加・各種バグ修正）
- **V-15** 完了（ユーザー確認済み）
- **proof_v2型** を新設：旧 proof_user（V2統合機能付き）の後継型。旧 proof_user は「担当者選択のみ（登録なし）」の単純型として再定義
- **バグ修正:** 変換ボタン押下後 UI が更新されない → `router.reload()` の `onSuccess` コールバックで `localColumnConfig`/`localCells` を同期
- **バグ修正:** 変換失敗時のエラーメッセージがデフォルト文言になる → `e?.response?.data?.message` を優先表示
- **バグ修正:** worker完了処理が失敗してもトーストが出ず、完了状態が反映されない → `onWorkerComplete` の catch ブロックでエラートーストを表示・ローカル状態更新を try 外に移動
- **バグ修正:** 校正者が校正ジョブの時間変更時に 405 エラー → `user.proof_jobs.set` ルートを `Route::match(['post', 'put'], ...)` に変更
- **DB移行:** 既存シートの `proof_user` 型ノードのうち次兄弟が `joblink` でないもの（6シート）を tinker スクリプトで `proof_v2` に変換
- **変更ファイル:**
  - `resources/js/Pages/Coordinator/ProjectJobs/Show.vue`（`buildV2ColumnConfig` を worker+proof_v2 構成に変更・モーダル説明文更新）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（`executeConvert` 修正・`onWorkerComplete` 修正・`findWorkerSiblingKey` パラメータ名変更）
  - `resources/js/Components/ProgressCell.vue`（`proof_user` を担当のみ単純型に再実装・`proof_v2` ブロックをV2統合型として追加・`onProofUserSimpleChange` 追加）
  - `resources/js/Components/ColumnTreeEditor.vue`（型セレクターに `proof_v2` 追加・`TYPE_DEFAULT_LABELS` に追加・`addKumihanKoseiPreset` を worker+proof_v2 に変更）
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（`transformColumnConfig` で `proof_user` → `proof_v2` に変換）
  - `app/Http/Controllers/ProofCoordinator/ProofRequestController.php`（`cell_type` を `proof_v2` に変更・`findProofUserSiblingKey` で `proof_user`/`proof_v2` 両対応）
  - `routes/web.php`（`user.proof_jobs.set` を POST+PUT 両対応に変更）

### 2026-04-26（V-13バグ修正: 外部者選択 / 校正ジョブ一覧に未表示）
- **Bug fix** V-13実装後に発覚した2バグを修正
- **変更ファイル:**
  - `resources/js/Components/ProgressCell.vue`
    - proof_user セレクターに外注先（subcontractors）を追加
    - `onProofUserChange` に `s_*` プレフィックス対応（`value_type: 'subcontractor'` を emit）
    - `proofAssigneeName` に `value_subcontractor_id` 参照を追加
    - 「担当者設定済み・未登録」状態・「＋登録」ボタンを `value_subcontractor_id` にも対応
  - `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php`
    - `store()` 進行表セルリンク時に column_config を参照して `cell_type` を `proof_user` に正しく設定
    - `proof_user` セルへの割り当て時に ProofRequest を自動作成（校正ジョブ一覧に表示されるよう対応）
    - `findColTypeInConfig()` private ヘルパー追加

### 2026-04-26（V-13 完了）
- **V-13** ユーザー確認済み
- **変更ファイル:**
  - `resources/js/Components/ProgressCell.vue`（proof_user型をworker型に近い2カラムUIに刷新・proof_user型用computed追加）
  - `resources/js/Components/ProgressTable.vue`（rowCompletionMapにproof_user追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（onProofDirectComplete修正・sheetCompletionにproof_user追加）
  - `app/Http/Controllers/Coordinator/ProgressCellController.php`（bulkUpdateにproof_userケース追加）
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（detectUserJobPairs/transformColumnConfigにproof_user+joblinkペア対応追加）

### 2026-04-26（V-12 完了）
- **V-12** 完了（ユーザー確認済み）
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressReportController.php`（新規: スコープ=project_team_members・フィルター・締め切り色分け）
  - `resources/js/Pages/Coordinator/ProgressReport/Index.vue`（新規: 検索UI・テーブル・行クリックで進行表遷移）
  - `routes/web.php`（`GET /coordinator/progress-report` → `coordinator.progress_report.index` 追加）
  - `resources/js/Components/Tabs/CoordinatorNavigationTabs.vue`（「進行レポート」タブ追加）

### 2026-04-26（V-16 完了）
- **V-16** 完了（ユーザー確認済み）
- 自動印刷を廃止し「印刷を実行」ボタン押下時のみ `window.print()` を起動する方式に変更
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（`printView()` + `buildPrintCells()` 追加）
  - `app/Http/Controllers/User/ProgressSheetController.php`（`printView()` 追加）
  - `app/Http/Controllers/Shared/ProgressSheetController.php`（`printView()` 追加・`show()` に `token` prop追加）
  - `routes/web.php`（`coordinator.progress_sheets.print` / `user.progress_sheets.print` / `shared.progress_sheets.print` 追加）
  - `resources/js/Pages/Shared/ProgressSheets/Print.vue`（新規: 印刷専用ページ・自動window.print()・印刷時CSS）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（「印刷」ボタン + `openPrint()` 追加）
  - `resources/js/Pages/User/ProgressSheets/Show.vue`（「印刷」ボタン + `openPrint()` 追加）
  - `resources/js/Pages/Shared/ProgressSheets/Show.vue`（「印刷」ボタン + `token` prop + `openPrint()` 追加）

### 2026-04-26（V-11 ビルド成功）
- **V-11** 完了（ユーザー確認済み）
- **変更ファイル:**
  - `app/Models/ProgressSheet.php`（`share_token` を `$fillable` に追加）
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（`share()` / `unshare()` 追加・`show()` に `share_token` 追加）
  - `app/Http/Controllers/Shared/ProgressSheetController.php`（新規: 認証不要の公開ページController）
  - `routes/web.php`（`POST/DELETE progress-sheets/{sheet}/share` + `GET /shared/progress-sheets/{token}` 追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（ツールバーに「共有リンクを発行」/「URLをコピー」/「リンクを無効化」ボタン追加・`issueShare()` / `revokeShare()` / `copyShareUrl()` 関数追加）
  - `resources/js/Pages/Shared/ProgressSheets/Show.vue`（新規: 読み取り専用公開ページ）

### 2026-04-25（V-10完了）
- **V-10** 完了（ユーザー確認済み）
- **変更ファイル:**
  - `app/Http/Controllers/User/ProgressCellController.php`（新規: `myAssignments()` — worker/user型セルを横断取得・締め切り優先順計算・Inertia描画）
  - `routes/web.php`（`GET /user/progress-cells/my-assignments` → `user.progress_cells.my_assignments` 追加）
  - `resources/js/Pages/User/ProgressCells/Index.vue`（新規: 検索窓・年月セレクター・クライアント/案件ごとグループ切替・アラート色・完了非表示）
  - `resources/js/Pages/JobBox/Index.vue`（Userコンテキスト時の `#tabs` に「進行表担当」タブ追加）
  - `app/Http/Controllers/Coordinator/ProgressCellController.php`（`bulkUpdate` worker case に `cell_type = 'worker'` 追加）
  - `app/Http/Controllers/Coordinator/ProjectJobAssignmentsController.php`（`store()` 進行表セルリンク時に `value_user_id`/`cell_type` も保存・`_progress_sheet_id` があれば進行表にリダイレクト）
- **バグ修正（同時対応）:**
  - `bulkUpdate` が `cell_type` をセットしていなかったため担当セルが一覧に表示されなかった
  - 進行表からジョブ発信後にジョブ一覧ではなく進行表に戻るよう修正

### 2026-04-25（V-09完了：メモ著者バッジ対応 + 保存問題修正）
- **V-09** 完了
- **変更ファイル:**
  - `database/migrations/2026_04_25_100002_add_note_user_id_to_progress_cells_table.php`（新規: `cell_note_user_id` FKカラム追加）
  - `app/Models/ProgressCell.php`（`$fillable` に `cell_note_user_id` 追加・`noteUser()` relation追加）
  - `app/Http/Controllers/Coordinator/ProgressCellController.php`（`note()` で `cell_note_user_id` 保存・`noteByPosition()` を追加: `row_id`+`col_key` で upsert して `cell_id` を返す）
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（`with` に `noteUser:id,name,user_role` 追加・セルmapに `cell_note_user_name`/`cell_note_user_role` 追加）
  - `routes/web.php`（`POST progress-sheets/{sheet}/cell-note` → `coordinator.progress_sheets.cell_note` を追加）
  - `resources/js/Components/ProgressCell.vue`（メモ行UI全面刷新: 1行表示+ホバーポップアップ+ロールカラー著者バッジ。`saveNote()` emit に `rowId`/`colKey` を追加）
  - `resources/js/Components/ProgressTable.vue`（`@note-save` passthrough・defineEmitsに`note-save`追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（`onNoteSave` を `cellId` なし対応: DB未作成セルは新エンドポイント経由でupsert、返ってきた `cell_id` で `localCells` を更新）
- **備考:** `cell_note_user_id` マイグレーション実行済み。ロールカラー: SuperAdmin=黄/Admin=赤/Leader=オレンジ/Coordinator=緑/Clerk=紫/User=青

### 2026-04-25（V-09実装）
- **V-09** ビルド成功・ユーザー確認待ち
- **変更ファイル:**
  - `resources/js/Components/ProgressCell.vue`（メモアイコン＋インライン編集テキストエリアをworker/schedlink/joblink型に追加・`note-save` emit追加・`showNoteEdit`/`editingNote`/メモ関数追加）
  - `resources/js/Components/ProgressTable.vue`（`@note-save` passthrough・defineEmitsに`note-save`追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（`@note-save="onNoteSave"`バインド・`onNoteSave()` PATCH API追加）
- **備考:** バックエンド（ProgressCellController::note() + routes）はV-02で実装済みのため追加不要

### 2026-04-25（V-08完了）
- **V-08** 完了（ユーザー確認待ち）
- **変更ファイル:**
  - `resources/js/Pages/Coordinator/ProgressTemplates/Edit.vue`（PREVIEW_TYPE_LABELS に `worker: '担当＋ジョブ'` / `schedlink: '予定連携'` を追加）
- **備考:** ColumnTreeEditor.vue の型選択ドロップダウンはV-03時点で実装済みのため追加不要

### 2026-04-25（バグ修正）
- **バグ修正:** workerセル「完了にする」後リロードで戻る問題
- **原因:** `ProgressSheetController::completeAssignment()` が `progress_cells.completed_at` を更新していなかった
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（completeAssignment に `ProgressCell.completed_at` 更新処理を追加）

### 2026-04-25（V-07完了）
- **V-07** 完了（ユーザー確認済み・データ引き継ぎ成功）
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（`convertPreview()`・`convertToV2()`・`detectUserJobPairs()`・`transformColumnConfig()`を追加）
  - `routes/web.php`（`GET progress-sheets/{sheet}/convert-preview` / `PUT progress-sheets/{sheet}/convert-to-v2` 追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（「新形式に変揔」ボタン・`hasOldPairs`computed・プレビューモーダル追加）
- **実装内容:**
  - user+joblinkペアがあるときのみ「新形式に変揔」ボタンを表示
  - ボタンクリック→プレビューモーダル表示（検出ペア一覧・引き継ぎ可能セル数・不可逆警告）
  - 全データ引き継ぎ（value_user_id/value_subcontractor_id/assignment_idをworkerセルに移送）
  - joblinkセルは移送完了後に削除

### 2026-04-25（V-06完了）
- **V-06** 完了（ユーザー確認済み）
- **変更ファイル:**
  - `resources/js/Components/ProgressTable.vue`（行ごとの完了率バッジ追加・rowCompletionMap computed追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（ツールバー右端にシート全体完了率バッジ追加・sheetCompletion computed追加）
- **備考:** 締め切りアラート色（bg-red-50/bg-yellow-50/bg-green-50 + 左border）はV-03/V-05で実装済みのため追加実装なし

### 2026-04-25（続き）
- **V-05** 完了（ユーザー確認済み）
- **変更ファイル:**
  - `resources/js/Components/ProgressCell.vue`（schedlink型ブロック追加・schedlink系computed/関数追加・defineEmitsにschedlink-complete追加）
  - `resources/js/Components/ProgressTable.vue`（@schedlink-complete passthrough・defineEmits追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（@schedlink-complete バインド・onSchedlinkComplete()追加）
- **V-04** 完了
- **変更ファイル:**
  - `app/Models/ProjectSchedule.php`（completed_atをfillable/castsに追加）
  - `app/Http/Controllers/Coordinator/ProgressCellController.php`（complete()にschedlink→project_schedules.completed_at更新追加・bulkUpdateにschedlinkケース追加）
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（show()のcellsマップにschedule_name/schedule_completed_at追加・scheduleロードにcompleted_atカラム追加）

### 2026-04-25
- **セット方式・組版列をworker型に変更**
- **変更ファイル:**
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（`generateV2ColumnConfig`: 組版を user+joblink ペア → worker 1列に変更）
  - `resources/js/Components/ColumnTreeEditor.vue`（型選択に「担当＋ジョブ（V2）」追加）
- **V-03** 完了
- **変更ファイル:**
  - `app/Http/Controllers/Coordinator/ProgressSheetController.php`（cellsマップにV2フィールド追加・projectSchedules追加）
  - `app/Http/Controllers/Coordinator/ProgressCellController.php`（worker型でsubcontractor_id対応）
  - `resources/js/Components/ProgressCell.vue`（worker型セルテンプレート + props/emits/computed追加）
  - `resources/js/Components/ProgressTable.vue`（projectSchedules prop + worker系emitsバブリング追加）
  - `resources/js/Pages/Coordinator/ProgressSheets/Show.vue`（projectSchedules prop + worker系ハンドラ追加）
- **V-02** 完了
- **変更ファイル:**
  - `app/Models/ProgressCell.php`（$fillable/$casts/schedule()リレーション追加）
  - `app/Http/Controllers/Coordinator/ProgressCellController.php`（worker型対応 + complete/deadline/note追加）
  - `app/Http/Controllers/ProjectJobs/JobBoxController.php`（completeAssignment でcompleted_at連携）
  - `routes/web.php`（progress-cells 3ルート追加）
- **V-01** 完了
- **作成ファイル:**
  - `database/migrations/2026_04_25_100001_add_v2_columns_to_progress_cells_table.php`（schedule_id / cell_deadline / cell_note / completed_at）
  - `database/migrations/2026_04_25_100002_add_share_token_to_progress_sheets_table.php`（share_token）
  - `database/migrations/2026_04_25_100003_add_completed_at_to_project_schedules_table.php`（completed_at）

---

## ステータス凡例

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

## ⚠️ プロジェクト完了時チェックリスト：新旧形式の差分確認

> **背景:** V2刷新では `progress_cells.completed_at` を新規追加したが、
> 機能実装後も既存の完了済みデータには `completed_at = NULL` のまま残るレコードが存在した（13件）。
> 表示上の workaround（`cell.completed_at || cell.assignment_completed` フォールバック）で対処したが、
> 本来はDBデータの整合性も保証すべきであった。
>
> 同様の問題は **V-13（校正列のworker型対応）** 等、今後の機能追加でも発生する可能性が高い。
> 次回以降の作業でも以下のチェックを必ず実施すること。

### 毎回確認すべき事項

| # | 確認項目 | 対処方法 |
|---|---------|---------|
| 1 | **新規カラム追加時** — 既存レコードにデフォルト値が入るか、または NULL のまま残るか | マイグレーション時に `DEFAULT` を設定するか、バックフィルSQLを用意する |
| 2 | **フラグ・タイムスタンプ系カラム** — 新機能リリース前に完了済みのレコードが NULL になっていないか | `WHERE old_flag = true AND new_column IS NULL` でカウントし、0件でなければバックフィル実行 |
| 3 | **セル型変更**（例: `user` → `worker`、`proof_user` → 新型）— 旧型セルのデータが新型の参照先カラムに引き継がれているか | 変換スクリプト（V-07方式）または手動SQLで確認 |
| 4 | **表示ロジックの workaround** — フォールバック（`A || B`）で凌いでいる箇所が残っていないか | 本番デプロイ後にバックフィルを実施し、workaround を削除できるか検討する |
| 5 | **さくら本番のデータ** — ローカルで確認した件数と本番の件数が一致するか | `SELECT COUNT(*) ...` でローカル・本番それぞれ確認してから本番バックフィルを実行 |

### バックフィルSQL作成のテンプレート

```sql
-- 例: completed_at が NULL のまま残った完了済みセルを一括更新
UPDATE progress_cells pc
JOIN project_job_assignments pja ON pc.assignment_id = pja.id
SET pc.completed_at = pja.updated_at   -- updated_at を代替値として使う場合
WHERE pja.completed = 1
  AND pc.completed_at IS NULL;

-- 実行前に必ず件数確認
SELECT COUNT(*) FROM progress_cells pc
JOIN project_job_assignments pja ON pc.assignment_id = pja.id
WHERE pja.completed = 1
  AND pc.completed_at IS NULL;
```

### 今回（V2刷新）の実績

| 対象 | バックフィルSQL | ローカル件数 | 本番件数 |
|------|--------------|------------|---------|
| `progress_cells.completed_at` | `UPDATE progress_cells pc JOIN project_job_assignments pja ON pc.assignment_id = pja.id SET pc.completed_at = NOW() WHERE pja.completed = 1 AND pc.completed_at IS NULL` | 13件 | 本番デプロイ後に要確認 |

> **次回（V-13: 校正列のworker型対応）での想定課題:**
> - `proof_user` 型セルに `value_user_id` がセットされているが、worker型相当の `completed_at` が存在しない可能性
> - 校正ジョブ（joblink）の `assignment_id` を新セルに引き継ぐ変換スクリプトが必要
> - 変換後、旧セルのデータが新セルに正しく移行されているか件数ベースで確認する

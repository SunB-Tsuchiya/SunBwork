# SunBWork 修繕 作業管理書 第5版 — 案件・ジョブ・日報 不具合修正
作成日: 2026-05-19
更新日: 2026-05-19

---

## この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「R5-01を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（REPAIR_MANAGER5.md）を読む
2. `z_instructions/REPAIR_PLAN5.md` を読む（詳細仕様・変更ファイル一覧）
3. `CLAUDE.md` を参照する（プロジェクト全体ルール）
4. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
5. 以下の「作業フロー」に従って進める

---

## 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/REPAIR_PLAN5.md` | 修繕計画5の詳細仕様・変更ファイル一覧 |
| `z_instructions/REPAIR_MANAGER4.md` | 修繕計画4の管理書（完了済み）|
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## 作業フロー（Claude はこの手順を厳守すること）

```
STEP 1: 計画書を読む
  → REPAIR_PLAN5.md の該当項目を読み、仕様を把握する
  → 「要調査」の項目は関連ファイルを確認してから設計を確定する

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → 承認された設計に従って実装する
  → Vue/JS ファイルを変更したら必ず npm run build を実行
  → PHP のみの変更なら npm run build は不要
  → Artisan が必要な場合は docker compose exec 経由で実行

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「✅ 完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示して止まる（自動で次の作業に進まない）
```

### 安全ルール（必ず守ること）

- STEP 2 でユーザーの確認なしに実装を始めない
- 1つの作業が完了するまで次の作業に移らない
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する
- 完了後は次の推奨作業を提示して止まる（自動進行しない）

---

## 進捗一覧

### フェーズ1：単純修正（最優先）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| R5-01 | 通知時間表記「15時：40」→「15:40」修正 | ✅ 完了 | JobNotifications/Index.vue:194 |
| R5-02 | 案件一覧お気に入り星 機能修正 | ✅ 完了 | web.php, Coordinator/ProjectJobController.php |
| R5-03 | スケジュールパネルCSVボタン重複削除 | ✅ 完了 | ProjectCalendar.vue |
| R5-04 | ジョブ名全角ハイフン→アンダーバー統一 | ✅ 完了 | User/ProgressSheets/Show.vue |
| R5-05 | ジョブ編集時の開始時間が現在時間にセット | ✅ 完了 | JobAssign/AssignmentForm.vue |

### フェーズ2：中規模修正

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| R5-06 | 案件一覧に伝票番号カラム + 表示設定 | ✅ 完了 | Coordinator/ProjectJobs/Index.vue |
| R5-07 | スケジュール編集タブ移動統一・開始日コピー | ✅ 完了 | 調査の結果すでに実装済み（type="date" + @change コピー）|
| R5-08 | 進行表にユーザー名表示（調査・修正） | ✅ 完了 | Coordinator/ProgressSheetController.php, User/ProgressSheetController.php |
| R5-09 | 完了/未完了フロー修正 | ✅ 完了 | ProgressSheetController.php, User/MyProjectJobController.php, ProgressSheets/Show.vue |
| R5-10 | 画像登録/削除後モーダルリロード統一 | ✅ 完了 | Coordinator/ProjectJobs/Show.vue |
| R5-11 | CSV文字コード自動変換（Shift-JIS対応） | ✅ 完了 | ProjectSchedulesController.php（BulkProjectJobController は実装済みだった）|

### フェーズ3：大規模修正

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| R5-12 | テンプレートから案件作成タブ追加 | ✅ 完了 | BulkCreate.vue + ProjectJobController + web.php |
| R5-13 | ジョブ重複防止・登録済み可視化 | ✅ 完了 | JobBoxController, ProjectJobAssignmentUserController, ProjectJobAssignmentController, JobBox/Index.vue, ProjectJobAssignment.php |
| R5-14 | 日報タイムライン表示 | ✅ 完了 | Diaries/Create.vue, Diaries/Edit.vue |
| R5-15 | Quillエディター箇条書き修正 | ✅ 完了 | Diaries/Create.vue (CSS import 修正), resources/css/app.css |
| R5-16 | 日報タイムライン編集・カレンダー連動 | 🔨 実装済・確認待 | Diaries/Create.vue, Diaries/Edit.vue |

### 保留

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| — | OCR精度調整 | ⏸ 保留 | データ収集後に別途対応 |

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

## 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-19 | — | 計画書（REPAIR_PLAN5.md）・管理書（REPAIR_MANAGER5.md）・プロンプト（REPAIR5_PROMPT.md）作成 | Claude |
| 2026-05-19 | R5-01 | 通知時間表記修正（getHours/getMinutes に変更） | Claude |
| 2026-05-19 | R5-03 | スケジュールパネル内の CSV 出力・取込ボタン削除 | Claude |
| 2026-05-19 | R5-13 | マイジョブ登録時に元の Coordinator 割当の is_registered = true を更新 | Claude |
| 2026-05-19 | R5-02 | coordinator.project_jobs.favorite ルート追加・toggleFavorite メソッド追加・is_favorite/favoriteJobs を index で付与 | Claude |
| 2026-05-19 | R5-04 | User/ProgressSheets/Show.vue の buildJobTitle で全角ハイフン「ー」→「_」に変更 | Claude |
| 2026-05-19 | R5-05 | AssignmentForm.vue で編集時（assignment.id あり）は既存の start_time_hour/min を保持 | Claude |
| 2026-05-20 | R5-05 | normalizeAssignment の sender_id 漏れ修正（_isSelfEdit が常に false になり終了時刻が現在+30分になるバグ） | Claude |
| 2026-05-20 | R5-13 | JobBoxController::show の is_registered 上書きバグ修正、create() ブラウザバックガード追加 | Claude |
| 2026-05-20 | R5-13 | whereNotExists 削除 → is_registered 動的計算（DB列 + supersedes フォールバック） | Claude |
| 2026-05-20 | R5-13 | JobBox/Index.vue「登録済みを表示しない」チェックボックス追加（デフォルトON）、「登録済」バッジ追加 | Claude |
| 2026-05-20 | R5-13 | myjob:cleanup-duplicates コマンド作成（重複削除 + is_registered 補完） | Claude |
| 2026-05-20 | — | z_instructions/JOB_FLOW_GUIDE.md 作成（ジョブフロー全体ガイド） | Claude |
| 2026-05-20 | R5-06 | 案件一覧に伝票番号カラム追加・表示設定ボタン（localStorage 永続化） | Claude |
| 2026-05-20 | R5-07 | 調査の結果すでに実装済み（type="date" + @change で開始日コピー済み） | Claude |
| 2026-05-20 | R5-11 | ProjectSchedulesController::csvImport に Shift-JIS 変換・CRLF 正規化追加 | Claude |
| 2026-05-20 | R5-11 | NormalizesCsvEncoding トレイト作成・全5コントローラーに適用 | Claude |
| 2026-05-20 | R5-08 | linkJob で value_user_id をセットしていなかったバグ修正（Coordinator/User 両方）| Claude |
| 2026-05-20 | R5-09 | uncompleteAssignment で completed_at クリア・destroyAssignment も同様・フロントも修正 | Claude |
| 2026-05-20 | R5-10 | 画像 store/delete の onSuccess に router.reload を追加 | Claude |
| 2026-05-23 | R5-10 | Show.vue ライトボックスの job.image_url → jobImageUrl / jobOriginalFilename に統一 | Claude |
| 2026-05-23 | R5-09 | adminCompleteAssignment / adminUncompleteAssignment 後に jobLinkDetailModal.open = false 追加 | Claude |
| 2026-05-23 | R5-06 | 案件一覧の表示設定を全カラム（登録日・伝票番号・クライアント名・ステータス）対象に拡張 | Claude |
| 2026-05-23 | R5-15 | Create.vue の Quill CSS import を正しいバージョン（vue-quill.snow.css）に修正・app.css にリスト bullet CSS 追加 | Claude |
| 2026-05-23 | R5-14 | Diaries/Create.vue・Edit.vue に TimelineDiary 追加（当日予定をフォーム下部に表示） | Claude |
| 2026-05-23 | R5-12 | BulkCreate.vue にテンプレートから作成タブ追加・ProjectJobController::storeFromTemplate メソッド追加・web.php にルート追加 | Claude |
| 2026-05-23 | R5-16 | Diaries/Create.vue・Edit.vue のタイムラインを editable=true に変更・update:events/open-create/open-edit ハンドラ追加・Edit.vue に form.date watch 追加 | Claude |

---

## 推奨作業順

**最初の推奨:** R5-01 → R5-03 → R5-13 → R5-02 → R5-05 → R5-04 の順で進める。

- R5-01・R5-03: 各1ファイルのみ、リスクなし
- R5-13: バックエンド1ファイルのみ、リスク低
- R5-02: ルート + コントローラー、テーブル確認が必要
- R5-05・R5-04: 要調査

フェーズ2以降は各作業前にコード調査を必ず行う。

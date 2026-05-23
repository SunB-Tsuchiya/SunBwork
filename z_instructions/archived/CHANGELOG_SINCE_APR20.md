# SunBWork 改定履歴（2026年4月20日以降）
作成日: 2026-05-06

---

## 概要

2026年4月20日から5月6日にかけて、バグ修正・機能改善・大規模機能開発を体系的に実施した。大きく「修繕第1版」「進行表V2刷新」「修繕第2版」「Prepress部署エリア」「イベント刷新」「レイアウト修繕」「校正Coジョブ管理」「UI状態永続化」の8フェーズに分かれており、合計100件超のタスクが完了している。「案件一括作成」「OCR伝票読み取り」はいずれも設計書作成後に全機能が実装済みであることが2026-05-06の調査で確認された。

---

## 改定一覧

---

### フェーズ0：案件一括作成機能（設計書作成）— 2026年4月20日

**ユーザーの要望:** Coordinatorが短いスパンで数十件の細かい案件を登録しなければならず、1件ずつ手入力が非常に手間がかかる。チームメンバーは変わらずクライアントだけ変わるケースが多いため、3機能で効率化したい。

**設計内容（BULK_PROJECT_CREATE_PROPOSAL.md / BULK_PROJECT_CREATE_DESIGN.md）:**
- 機能1：既存案件のワンクリック複製（`ProjectJobController::clone()` 追加）
- 機能2：CSVテンプレートによる案件一括登録（`project_job_templates` テーブル新設・`BulkProjectJobController` 新設・`BulkCreate.vue` 新設）
- 機能3：クライアントプリセット（クライアント選択時に直近案件設定を自動セット）
- CSVフォーマット仕様・バリデーションルール・さくら本番注意事項（`Arr::pull($data, 'schedule')` 必須等）を詳細設計

**完了状況:** 全3機能実装済み（2026-05-06確認）。機能1（ワンクリック複製）は G-02 で実装済み。機能2（CSV一括登録）・機能3（クライアントプリセット）も実装完了。実装ファイル：`BulkProjectJobController.php`（609行）・`ProjectJobTemplateController.php`・`BulkCreate.vue`（1430行）・DBマイグレーション（`project_job_templates` テーブル）・ルート8本（`routes/web.php`）。

---

### フェーズ1：修繕第1版（REPAIR_PLAN.md）— 2026年4月21〜24日

**ユーザーの要望:** カレンダー・進行管理表・ジョブ一覧など主要画面に散在するバグを修正し、レイアウトを統一した上で機能改善・大規模機能開発を行いたい。

**実装内容:**

#### バグ修正（B-01〜B-07）全7件完了

- **B-01** カレンダー予定削除失敗 → `ProjectSchedulePolicy` に `delete()` 追加。同セッション内でドラッグ移動・リサイズ保存・CSV出力取込・ホバーポップアップも追加実装
- **B-02** カレンダーの日付が1日ずれる → `ProjectSchedule` モデルのキャストを `date:Y-m-d` に変更
- **B-03** スケジュール編集後に予定が二重表示 → `submitScheduleUpdate` の `router.reload()` を削除
- **B-04** 「ジョブ詳細を開く」ボタンが反応しない → `ProgressSheets/Show.vue` のルート名を `coordinator.` プレフィックス付きに修正
- **B-05** 「未完了にする」後もジョブ一覧では完了扱い → `uncompleteAssignment` に `status_id=null` 追加・Leaderロールの権限不足も修正
- **B-06** ジョブ一覧「完了を表示しない」フィルターが機能しない → `hideCompleted` を localStorage で永続化
- **B-07** 案件内割り当て一覧→ジョブ一覧が空になる → `CoordinatorNavigationTabs` の「ジョブ一覧」タブを常にグローバル jobbox へ遷移するよう変更

#### レイアウトガイドライン（L-01〜L-02）全2件完了

- **L-01** レイアウトガイドライン文書作成（`z_instructions/LAYOUT_GUIDELINES.md`）— ボタン種別・配置ルール・表記統一・カレンダーマス目サイズ等を定義
- **L-02** ガイドラインの全ページ適用 — 優先度高6ファイル＋追加8ファイル（Coordinator/Leader/User 計14ファイル）に `#header` 戻るボタン追加・`#headerExtras` へボタン移動等を適用

#### 機能改善（F-01〜F-10）全10件完了

- **F-01** ジョブステータスフロー刷新（送信→確認済み→セット→完了の4段階を既存カラムで実装）。`EventController` の誤全件完了バグも同時修正
- **F-02** 進行管理表テンプレートに「戻る」ボタン追加（L-02 で対応済み）
- **F-03** 台割行の見出しグループ後の追加が不可な問題 → `after_id` 挿入・行追加UI刷新・子行↑↓並び替え追加
- **F-04** テンプレート見出し・行の編集機能 → Enter キー保存対応・保存時の未確定行ラベル警告トースト追加
- **F-05** 行管理で追加時に末尾文字が省略される → `@keydown.enter` を `@keyup.enter` に変更（IME確定後に発火）
- **F-06** 台割行の「複製」機能追加 → `ProgressRowController::duplicate()` 追加・複製ボタンUI・blur 自動確定
- **F-07** 「案件詳細に戻る」で進行管理表タブを開く → 戻るリンクに `?tab=progress` を付与・`activeTab` 初期値を URL パラメータから読むよう変更
- **F-08** スケジュールの直接入力（カレンダー以外） → 案件詳細の概要タブにインライン編集モード追加（追加・編集・削除・ソート・CSV出力取込）
- **F-09** 「進行表に紐づける」を紐づけ済みなら操作不可 → `linkOptions`/`linkCell` の権限チェックに `leader` ロールを追加（403修正）
- **F-10** カレンダー週間プランナービューの追加 → `project_schedule_week_posts` テーブル追加・週間プランナーUI・多段スレッド掲示板・ロールカラー表示・User側カレンダー参照対応

#### 大規模機能開発（G-01〜G-02）全2件完了

- **G-01** スケジュールと進行管理表の連動 → `project_job_items` テーブル追加・連携設定タブ（ProjectJobItemsTab.vue）・カレンダー自動表示・進行表読み込み・双方向同期。詳細設計書は `z_instructions/G01_ITEM_DESIGN.md`
- **G-02** 案件複製機能の拡張 → スケジュール（日付null・progress=0）・進行表構造（担当者null）を複製。`ProjectJobController::clone()` 拡張。DBマイグレーション不要

#### 別プロジェクト

- **P-01** 子案件機能 → G-02（案件複製機能）で目的達成のためスキップ

**完了状況:** B-01〜B-07（7件）・L-01〜L-02（2件）・F-01〜F-10（10件）・G-01〜G-02（2件）全21件完了。

---

### フェーズ2：進行表V2刷新（PROGRESS_SHEET_V2_DESIGN.md）— 2026年4月25〜27日

**ユーザーの要望:** 進行表を「担当者管理 + ジョブ連携 + スケジュール連携」が一体となったデータ管理基盤として刷新したい。現在の「担当セル（user型）+ 登録ボタン（joblink型）をセットで作る」制約を撤廃し、1つのセルで担当者管理・締め切り管理・ジョブ完了を完結させたい。

**実装内容（V-01〜V-16）全15件完了（V-14のみ確認待ち）:**

- **V-01** DBマイグレーション6カラム追加（`progress_cells` に schedule_id/cell_deadline/cell_note/completed_at、`progress_sheets` に share_token、`project_schedules` に completed_at）
- **V-02** `worker` 型セル Backend API対応（モデル/Controller/JobBox完了連携/ルート追加）
- **V-03** `worker` 型セル Frontend実装（ProgressCell.vue に担当者セレクター・締め切り・ジョブ登録・完了管理の2カラムUIを追加）
- **V-04** `schedlink` 型セル Backend API対応（スケジュール完了操作・bulkUpdate対応）
- **V-05** `schedlink` 型セル Frontend実装（スケジュール選択・完了操作UI）
- **V-06** 締め切りアラート色（完了=緑/期日超過=赤/3日以内=黄）+ 完了率バッジ（行単位・シート単位）
- **V-07** 既存シート変換機能（user+joblinkペア → worker型への不可逆変換。プレビューAPI+変換API の2段構成）
- **V-08** テンプレートへの新セル型対応（`PREVIEW_TYPE_LABELS` に worker/schedlink 追加）
- **V-09** セルメモ・コメント機能（1行表示+ホバーポップアップ+著者バッジ付きUI。`cell_note_user_id` カラムも追加）
- **V-10** User向け「自分の担当セル一覧」（JobBoxに「進行表担当」タブ追加。締め切り優先順・グループ切替・アラート色）
- **V-11** 進行表の読み取り専用共有URL（`share_token` 発行・公開ページ `Shared/ProgressSheets/Show.vue` 新規作成・トークン無効化）
- **V-12** Coordinator横断レポート（`ProgressReportController` 新規作成・担当者/案件/完了状況/締め切り/完了日フィルター・色分け）
- **V-13** 校正列のworker型対応（`proof_user` 型を2カラムUI刷新・`proof_v2` 型として独立分離。外注先選択対応・校正ジョブ一覧連携修正）
- **V-14** カレンダー予定の完了表示（完了済み予定をグレー+✓プレフィックス表示・「未完了に戻す」ボタン追加）→ ビルド成功・ユーザー確認待ち
- **V-15** セット方式削除・列追加UI刷新（「セット方式で初期化」ボタン削除・ColumnTreeEditor に「組版+校正セット」プリセットボタン追加）
- **V-16** 進行表の印刷機能（Coordinator/User/共有URL の3か所・手動「印刷を実行」方式・`Print.vue` 新規作成）

**追加実装（2026-04-27）:** 進行表作成モーダルに「カレンダー（スケジュール）から作成」モードを追加（スケジュール項目をチェックで選択→行と日付セルを自動生成）

**完了状況:** V-14（確認待ち）を除く15件完了。

---

### フェーズ3：修繕第2版（REPAIR_PLAN2.md）— 2026年4月26〜29日

**ユーザーの要望:** 修繕第1版・進行表V2の完了後に発見・蓄積したバグ・UI改善・機能改善（N-01〜N-12）を実施したい。

**実装内容:**

#### バグ修正（N-06・N-07・N-09・N-10）全4件完了

- **N-06** ユーザーカレンダー（events）削除時の500エラー＋Coordinator非同期 → `EventController::destroy()` 修正・PJA-B削除・PJA-A復元・`CalendarController` 未定義変数修正・MyJobBoxステータス列追加
- **N-07** ジョブ履歴削除後のリダイレクト先を案件詳細・ジョブ履歴タブに変更 → `coordinator.project_jobs.show?tab=history` に変更
- **N-09** ジョブステータス表示の全ページ統一（F-01の4段階基準で5ファイルを統一・列幅100px化）
- **N-10** 「戻る」ボタンが機能しないページの調査・修正 → `Events/Show.vue` の `window.history.back()` を `goBack()` 関数化。他ページ問題なし確認済み

#### UI改善（N-01・N-02・N-05・N-11・N-12）全5件完了

- **N-01** ジョブ履歴の初期表示を「展開済み」に変更 → `historyOpen` の初期値を `false` → `true` に変更
- **N-02** ジョブ割り振り時の開始時刻初期値を現在時刻（5分刻み切り上げ）に・終了時刻は17:30に設定
- **N-05** 案件詳細タブ構成変更 → スケジュールタブを独立（新タブ順：概要・メンバー→進行管理表→スケジュール→連携設定→ジョブ履歴）
- **N-11** 案件カレンダーCSV出力のファイル名に案件名を含める（`{title}_スケジュール.csv` 形式・rawurlencode対応）
- **N-12** 進行管理表の行をクリックで開けるようにする（`tr` に `@click`・`cursor-pointer` 追加）

#### 機能改善（N-03・N-04・N-08）全3件完了

- **N-03** ジョブタイトル命名規則の統一（`normalizeTitle()` 追加：ー等を _ に統一・連続 _ 圧縮）
- **N-04** 「詳細を見る（進行表へ）」の遷移先改善（紐づき進行管理表が1枚→直接遷移・複数枚→シート選択モーダル）
- **N-08** ジョブ一覧グループ表示記憶＋Coordinator 設定タブ追加 → `coordinator_settings` テーブル・`CoordinatorSetting.php`・`CoordinatorSettingController.php` 新規作成・`Coordinator/Settings/Index.vue` 新規作成・設定タブをナビに追加

**将来計画:**
- **GUIDE-01** ガイド全面書き換え（Admin/Coordinator/Leader/User の全4ガイドページ） → 全修繕計画完了後に別計画書で実施（保留）

**完了状況:** N-01〜N-12 全12件完了。GUIDE-01 は保留。

---

### フェーズ4：Prepress（製版）部署エリア（PREPRESS_PLAN.md）— 2026年4月29日

**ユーザーの要望:** 印刷・組版会社内の製版（Prepress）部署に独立した作業エリアを設け、ホワイトボード運用（予定／作業中／完了）をWeb上で行える伝票管理機能を実装したい。

**実装内容（フェーズ1・2 全9件完了）:**

#### フェーズ1：ベース実装

- **P-01** AppLayout への Prepress タブリンク追加（`roleNavClass`・`currentRouteContext`・全ロール template に Prepress リンク追加・レスポンシブナビ対応）
- **P-02** `PrepressNavigationTabs.vue` 新規作成（ダッシュボード / 伝票ボード / 伝票一覧 タブ）
- **P-03** Prepress ダッシュボード（`Dashboard.vue` 新規作成・green-700 テーマ・プロフィールカード）
- **P-04** `routes/web.php` 全ルート追加 + `php artisan migrate` 実行
- **P-05** `HandleInertiaRequests.php` に `isPrepressDepartment` フラグ追加（`department.name === '製版'` で判定。department.name='製版' は ID:2 として確認済み）

#### フェーズ2：伝票管理機能

- **P2-01** 伝票ボード（`Board.vue` + `BoardController` 新規作成・HTML5 ネイティブ D&D・axios.patch によるオプティミスティック更新。Inertia JSON エラー修正含む）
- **P2-02** 伝票一覧（`Tickets/Index.vue` 新規作成・フィルター・ページネーション）
- **P2-03** 伝票登録（`Tickets/Create.vue` 新規作成・画像アップロード・ライトボックス）
- **P2-04** DBインフラ（`prepress_tickets` テーブル・`PrepressTicket.php`・`PrepressImageService`（PDF/HEIC→JPG変換・max 1600px・quality 85）・`PrepressDashboardController`・`TicketController` 新規作成）

**PREPRESS_BOARD_V2（2026-05-06確認済み・実装済み）:**
- 3列カンバンを4列アコーディオンボード（準備/作業中/入稿予定/完了）に全面改修済み
- `submitting`（入稿予定）ステータス・D&D遷移バリデーション・アコーディオン表示を `Board.vue` および `PrepressTicket.php` に実装済み

**完了状況:** フェーズ1・2・ボードV2 全件完了。フェーズ3以降（伝票詳細・編集・担当者・期日管理等）はユーザー指示待ち。

---

### フェーズ5：OCR伝票読み取り機能（OCR_TICKET_MANAGER.md）— 2026年5月3日（設計）

**ユーザーの要望:** `/prepress/tickets/create` の伝票登録フォームに伝票画像OCR自動入力機能を追加し、PDF/画像をアップロードするだけで伝票番号・クライアント名・品目名を自動入力できるようにしたい。

**設計内容:**
- **OCR技術:** ocr.space API（月25,000回無料・PHP から HTTPS POST・さくらサーバーでPython/Tesseractが利用不可のためクラウドOCR採用）
- **新規作成:** `OcrSpaceService.php`・`TicketOcrController.php`・`OcrModal.vue`・DBマイグレーション（`prepress_tickets.client_id` FK追加）
- **フロー:** ファイル選択 → 自動一時アップロード → OCRモーダル（サムネイル+入力枠+クライアントDB照合結果表示） → 「反映」でメインフォームに値流し込み
- **クロップ領域定義:** 受注番号・クライアント名・品目名の固定座標を設計

**完了状況:** 実装完了（2026-05-06確認）。当初設計のPython/EasyOCRからクラウドAPI（ocr.space）に変更し、1回のAPIコールで3フィールド（受注番号・クライアント名・品目名）を同時取得する方式を採用。実装ファイル：`TicketOcrController.php`・`OcrSpaceService.php`（日本語OCR・クライアント3段階照合・Imagickグレースケール前処理）・`OcrModal.vue`（509行）・DBマイグレーション（`prepress_tickets.client_id` FK追加）・ルート2本。OCR_TICKET_MANAGER.md のタスクステータスは「未着手」のままだが実態は実装済み。

---

### フェーズ6：イベント予定機能リニューアル（SPEC_EVENT_RENEWAL.md）— 2026年5月2日

**ユーザーの要望:** 従来の「予定作成」ボタン1つを廃止し、「案件打合せ・外出」と「社内予定」の2種類に分割したい。また Leader/Admin が定例会議を登録し、社内予定フォームでワンクリック入力できるようにしたい。

**実装内容（E-01〜E-08 全8件完了）:**

- **E-01** DBマイグレーション4件（`events.project_job_id` FK追加・`events.destination` 追加・`meeting_definitions` テーブル新設・`meeting_definition_members` 中間テーブル新設・`event_item_types` に「来社応対」追加）
- **E-02** バックエンド（`MeetingDefinition.php`・`Event.php` fillable/リレーション追加・`ClientEventController`・`InternalEventController`・`Leader/MeetingDefinitionController`・`Admin/MeetingDefinitionController` 新規作成・`routes/web.php` 追加）
- **E-03** `CreateClientEvent.vue` 新規作成（案件打合せ・外出フォーム・種類/クライアント/プロジェクト連動・タイトル自動生成）
- **E-04** `CreateInternalEvent.vue` 新規作成（社内予定フォーム・会議種類セレクター・自動入力）
- **E-05** Leader/Admin 会議設定 CRUD（`MeetingDefinitions/{Index,Create,Edit}.vue` 各ロール用計6ファイル新規作成）
- **E-06** カレンダー・日報ボタンの遷移先変更（`Calendar.vue`・`Diaries/Show.vue` の `openEventModal` → `router.get(route(...))` に変更・2種類のボタンに分岐）
- **E-07** Leader/Admin ナビゲーションタブに「会議設定」タブ追加
- **E-08** 予定イベント同士の重複計算が機能しない問題の修正（`Events/Create.vue` の `submit()` で `jobLinked`/`otherOverlap` 分岐を統合・全イベント対象に拡張）

**完了状況:** E-01〜E-08 全8件完了。`npm run build` 成功済み。

---

### フェーズ7：レイアウト修繕（LAYOUT_REPAIR_PLAN.md）— 2026年5月2〜3日

**ユーザーの要望:** `LAYOUT_SPEC_V2.md` の基準（`#header` スロットに `<h2>` を入れる・戻るボタンは `#header` 内・プライマリボタンは `bg-indigo-600`・コンテンツカード内にボタンを置かない等）に沿って、全ロールの主要ページを修繕したい。

**実装内容（R-01〜R-25 全25件完了）:**

#### フェーズ1：Coordinator 残修正（R-01〜R-09 計9件、対象13ファイル）
- R-01: 案件一覧 Index — `#headerExtras` への新規作成ボタン移動・色統一
- R-02: 案件作成 Create — `#header` 修正・戻るボタン追加・`mx-auto max-w-2xl` 追加・色統一
- R-03: 案件編集 Edit — 同上・フォームボタン整理
- R-04: ジョブ割り当て一覧 JobAssign/Index — 戻るボタン追加・色統一・`h1` 削除
- R-05: ジョブ割り当て詳細 JobAssign/Show — `#header` 戻るボタン追加・`#headerExtras` 編集/削除/紐づけボタン追加
- R-06: 案件選択 JobAssign/SelectProject — `#header` 戻るボタン追加・`h1` 削除・`mx-auto max-w-2xl` 追加
- R-07: 外注先3ページ（Show/Create/Edit）— 戻るボタンスタイル統一
- R-08: チームメンバー管理（Create/Index）— `bg-blue-600` → `bg-indigo-600` 統一
- R-09: スケジュールコメント作成 — 戻るを `#header` に `Link` 化・色統一

#### フェーズ2：User / JobBox / Events（R-10〜R-17 計8件）
- R-10: Events/Show — `#header` 追加（戻るボタン）・`#headerExtras` 追加（編集/削除）・ボタン整理
- R-11: Events/Edit — `#header` 追加・色統一・フォームボタン整理
- R-12: Events/Create — `#header` 追加・`h1`+説明文削除・`max-w-3xl→max-w-2xl`・色統一
- R-13: Events/Create_Job — `#header` 追加・`#headerExtras`（流用/依頼ボタン）追加・色統一
- R-14: JobBox/Show — `#header` 戻るボタン追加・`#headerExtras` 編集/削除ボタン追加・色統一
- R-15: JobBox/Schedule — `#header` 戻るLink追加・フォーム内戻るボタン削除・色統一
- R-16: MyJobBox/Show — `#header` 戻るLink追加・アクション行削除・`max-w-3xl` 削除・色統一
- R-17: JobNotifications/Index — `#header` 追加・カード内 `h2` 削除・色統一

#### フェーズ3：Admin（R-18〜R-19 計2件）
- R-18: Admin/Users/Index — `#headerExtras` 分離・色統一。Admin/Users/{Show,Edit,Create}.vue も追加で `#header` 標準化・`max-w-2xl` 追加
- R-19: Admin/Teams/Show — `#header` 戻るLink追加・`#headerExtras` 編集Link追加・`max-w-4xl` 削除

#### フェーズ4：Leader / Diaries / Messages（R-20〜R-23 計5件）
- R-20: Leader/ProjectJobs/Index — `mx-auto max-w-6xl` 削除・`h1`+説明文削除
- R-21: Diaries/Index（色統一）・Diaries/Show（`#header` 標準化・`#headerExtras` 編集/削除ボタン追加）
- R-22: Diaries/Interactions/Index — 色統一
- R-23: Messages/Show — 戻るボタンをボタンスタイルに・`#headerExtras` 削除ボタン移動

#### フェーズ5：ProofCoordinator / Proof / Prepress / Clerk（R-24〜R-25 計2件）
- R-24: ProofCoordinator/Dispatchers/{Show,Edit,Create} — `#header` 戻るLink標準化・`max-w-2xl` 追加
- R-25: ProofCoordinator/Assignments/Show — `#header` 戻るLink追加・`#headerExtras` 編集/開始/完了ボタン移動・`mx-auto max-w-3xl` 削除

**完了状況:** R-01〜R-25 全25件完了（対象ファイル数約30ファイル）。

---

### フェーズ8：校正Coジョブ管理（PROOF_JOBS_PLAN.md）— 2026年5月4日

**ユーザーの要望:** proof-coordinator の「割り振り管理」と「案件校正履歴」を統合し、進行中・完了をタブで切り替えて1ページで管理できる「ジョブ管理」ページを作りたい。完了ジョブを「未完了に戻す」機能も必要。

**実装内容（P-01〜P-05 全5件完了）:**

- **P-01** `routes/web.php` に新ルート2件追加（`proof_coordinator.jobs`・`proof_coordinator.assignments.uncomplete`）
- **P-02a** `ProofRequestController::assignStore()` のステータスを `assigned` → `in_progress` に変更
- **P-02b** `ProofRequestController::jobManagement()` メソッド追加（進行中・完了統合取得ロジック・検索・年月フィルター・デフォルト3か月表示）
- **P-02c** `ProofRequestController::uncomplete()` メソッド追加（完了→進行中に戻す・pja100・pja_op も戻す）
- **P-03** `ProofCoordinatorNavigationTabs.vue` 修正（「割り振り管理」→「ジョブ管理」にリネーム・「案件校正履歴」タブ削除）
- **P-04** `Assignments/Index.vue` 完全書き換え（2タブ・検索・グループ表示（案件/校正員/締め切り）・完了にする/未完了に戻すボタン・行クリックで詳細へ）
- **P-05** ビルド成功・動作確認完了

**完了状況:** P-01〜P-05 全5件完了。

---

### フェーズ9：UI状態永続化（UI_STATE_PERSIST_PLAN.md）— 2026年5月4日

**ユーザーの要望:** ページをリロード・再訪問しても、ユーザーが最後に操作した状態（チェックボックス・タブ・グループモード・ソート順）を自動的に復元したい。

**実装内容（P-00〜P-13 全14件完了）:**

- **P-00** 共通コンポーザブル `resources/js/Composables/useUIState.js` 新規作成（`useUIState(key, defaultValue)` 関数 — localStorage 読み書きを `ref` と `watch` で一元化）

- **フェーズ1（高優先度 P-01〜P-06）:**
  - P-01: `JobBox/Index.vue` — `hideCompleted`・`viewMode` を `useUIState` に移行
  - P-02: `MyJobBox/Index.vue` — `hideCompleted`・`viewMode` を移行
  - P-03: `Coordinator/ProjectJobs/Index.vue` — `hideCompleted`・`viewMode`（既存 `pj_index_view_mode` キー維持）・`sortKey`・`sortDir` を移行
  - P-04: `Admin/ProjectJobs/Index.vue` — `hideCompleted`・`viewMode`・`sortKey`・`sortDir` を移行
  - P-05: `Leader/ProjectJobs/Index.vue` — 同上
  - P-06: `ProofCoordinator/Jobs/Index.vue` — `groupMode` を移行

- **フェーズ2（中優先度 P-07〜P-10）:**
  - P-07: `ProofCoordinator/Inbox/Index.vue` — `groupMode` を移行
  - P-08: `WorkloadAnalyzer/Index.vue` — `sortKey`・`sortDir`・`viewMode`・`employmentFilter` を移行
  - P-09: `WorkloadAnalyzer/CategoryRank.vue` — `selectedCategory`・`employmentFilter` を移行
  - P-10: `JobNotifications/Index.vue` — `unreadOnly` を移行

- **フェーズ3（低優先度 P-11〜P-13）:**
  - P-11: `User/ProofStatus.vue` — `viewMode` を移行
  - P-12: `WorkRecord/Index.vue` — `sortOvertime` を移行
  - P-13: `SuperAdmin/Teams/Index.vue` — `showType` を移行

**localStorage キー命名規則:** `sbw_{ページ識別子}_{フィールド}` 形式（既存の `pj_index_view_mode` は後方互換のため変更なし）

**完了状況:** P-00〜P-13 全14件完了（対象ファイル13ページ）。

---

---

### フェーズ10：期間セレクター刷新・マイジョブ改善（2026年5月）

#### UI改善（期間セレクター統一）

- **diaries/index** — 7日/30日/90日 の期間セレクターを年月セレクター（全期間・最新36ヶ月）に変更。選択と同時にページ遷移。`DiaryController` のパラメータを `days` → `year`/`month`/`period` に変更。
- **admin/diaryinteractions** — 同様に年月セレクターに変更。`DiaryInteractionController` も同様改修。
- **job-notifications** — 年月セレクター追加。検索ボックス（幅 `w-72`）を左端に配置。適用ボタンを検索ボックス専用に移動。「全て既読にする」ボタン追加。`JobNotificationController::markReadAll()` 追加、`POST /job-notifications/read-all` ルート追加。表示形式（日別/月別）セレクターを削除。

#### バグ修正

- **自己割当ジョブのステータスが常に「未読」になるバグ** — `ProjectJobAssignmentController::store()` 内の `Schema::hasColumn('project_job_assignment_by_myself', ...)` チェックが、テーブル統合（`project_job_assignments` に統合済み）により常に `false` を返していた。`Schema::hasColumn` チェックを削除し、`read_at`/`scheduled`/`scheduled_at`/`assigned`/`accepted` を無条件でセットするよう修正。既存レコード（ローカル27件、さくら側）もDBで直接修正。
- **CalendarAll.vue のtodayボタン** — FullCalendar `buttonText.today` を `'今日'` → `'today'` に統一。

#### 自動完了バッチ（マイジョブ）

- **機能概要** — マイジョブ（`sender_id = user_id`）で `scheduled_at` が翌日以降を過ぎたもの（`desired_end_date` を第2優先）を毎日深夜に自動完了にする。
- **実装ファイル** — `app/Console/Commands/AutoCompleteMyJobs.php`（Artisanコマンド）、`app/Console/Kernel.php`（スケジューラー登録・毎日0:05実行）。
- **完了条件** — `scheduled_at < 今日 00:00`（優先）または `desired_end_date < 今日`（次候補）、かつ `completed = false`、かつ `sender_id = user_id`。どちらの日付もない場合は対象外。

---

## タスク総数サマリー

| フェーズ | 計画・設計 | 完了 | 保留/未着手 |
|--------|----------|------|-----------|
| フェーズ0：案件一括作成 | 3機能 | 全3機能実装済み | — |
| フェーズ1：修繕第1版（B/L/F/G/P） | 21件 | 20件完了、1件スキップ | — |
| フェーズ2：進行表V2刷新（V） | 16件 | 15件完了 | V-14 確認待ち |
| フェーズ3：修繕第2版（N） | 13件 | 12件完了 | GUIDE-01 保留 |
| フェーズ4：Prepress部署エリア（P） | 9件 | 9件完了 | フェーズ3以降ユーザー待ち |
| フェーズ5：OCR伝票読み取り（OCR） | 9件 | 9件完了 | — |
| フェーズ6：イベントリニューアル（E） | 8件 | 8件完了 | — |
| フェーズ7：レイアウト修繕（R） | 25件 | 25件完了 | — |
| フェーズ8：校正Coジョブ管理（P） | 5件 | 5件完了 | — |
| フェーズ9：UI状態永続化（P） | 14件 | 14件完了 | — |
| フェーズ10：期間セレクター刷新・マイジョブ改善（P） | 5件 | 5件完了 | — |
| **合計** | **約125件** | **約125件完了** | **—** |

# MGinbon（銀本制作進行管理）再開プロンプト 2

## 再開時の依頼

SunBWorkのMGinbon（みくに出版・銀本シリーズ制作進行管理）の作業を再開してください。
前回までにデータ移行、専用DB、管理台帳、User自己登録、MyJob・カレンダー連動の初版まで実装済みです。
次は、利用者承認済みのFileMaker型一覧レイアウト再設計を最優先で進めてください。

さくら本番にはまだ反映しないでください。ローカルで実装・検証し、利用者レビューを受けます。

## 最初に読むファイル

1. ルート`AGENTS.md`
2. `z_instructions/MGINBON_LAYOUT_PLAN2.md`（次回作業の承認済み計画）
3. `z_instructions/MGINBON_MANAGER1.md`（末尾の2026-09-19節を優先）
4. `z_instructions/MGINBON_PLAN1.md`
5. `z_instructions/MGINBON_WIREFRAME1.md`
6. `z_instructions/MGINBON_SBWORK_INTEGRATION1.md`
7. `z_instructions/MGINBON_FIELD_MAPPING1.md`
8. `z_instructions/CONSOLIDATED_01_layout_and_ui.md`
9. `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md`
10. `z_instructions/CONSOLIDATED_09_domain_rules.md`

`z_NDBSystem/N_DBSystem_DDR/`にはFileMaker DDR、Merge、Excel等があるが、社員名・外注先名等を含む。
必要な場合だけローカルで読み、外部送信、回答への実名列挙、Gitへの追加を行わない。

## 現在の実装状態

### 専用DBと移行

- `config/database.php`に`mginbon`接続を追加済み。
- MGinbonの正式データはSBWork本体DBではなく専用MySQL DBにある。
- 専用マイグレーションは`database/migrations/mginbon/`。
- 2026年度FileMaker全535媒体を正規化済み。
- 制作単位184、媒体535、教科2,074、工程29,036を作成済み。
- 年度は`mginbon_projects.year`で分離する。
- 旧担当文字列は推測分割せず、`legacy_value`として保持する。

### 画面

- Coordinatorメニューに「銀本進行」がある。
- MGinbonと紐づいた通常のプロジェクトジョブでは、「進行管理表」タブに銀本専用表が出る。
- Coordinator管理台帳、取込確認、担当候補対応付け、媒体詳細編集を実装済み。
- Userは通常案件の進行管理表から銀本作業用表を開く。
- Userは学校・媒体・工程・教科を選び、MyJobへ自己登録できる。
- MyJob詳細には対象校、媒体、工程、教科が表示される。
- User銀本表の本人登録済み工程には、該当MyJobを開くリンクが出る。

### Assignment・Event連動

- 正本キーは`project_job_assignments.id`。
- 4教科一括でもAssignmentと作業パッケージは1件で、4工程タスクへリンクする。
- `MGinbonAssignmentSyncService`が登録、作業開始、完了、未完了戻し、削除を同期する。
- カレンダー初回登録は工程を作業中にし、対応する入り日が空欄の場合だけEvent作業日を記録する。
- Coordinator手入力済みの日付をUser操作で上書きしない。
- カレンダー完了は工程・パッケージを完了にし、対応するUP日をEventのJST作業日で記録する。
- 未完了戻し・削除では、自動入力した節目だけを元へ戻す。手動入力日は維持する。
- MyJob、JobBox、既存進行表、カレンダーの各完了経路へ同期処理を接続済み。

## 直前に修正した重要バグ

銀本進行から登録したMyJobで「予定をセット」すると、元Assignmentとは別の自己割当を
`Events/Create_Job`が新規作成していた。その結果、カレンダーでは完了でも元MyJobと
MGinbonパッケージは登録済みのままになっていた。

修正内容:

- `EventController::createJob()`で、`job`パラメータから既存MyJobを開いた場合は
  `assignments[0].id`へ元Assignment IDを渡す。
- AssignmentFormのUser編集経路を使い、既存AssignmentへEventを接続する。
- `source_job_assignment_id`を伴うCoordinator依頼ジョブ経路は従来どおり別Assignmentを作る。

ローカルで発生済みだった分裂データは修復済み:

- 正しいAssignment: ID 5
- 誤生成された重複Assignment: ID 6（参照を移して削除済み）
- Event ID 1はAssignment 5を参照
- Assignment 5は`completed=true`、`scheduled=true`
- 対応MGinbonパッケージは`completed`

このDB修復はローカルデータだけであり、Gitコミットには含まれない。

## 次に行う作業：レイアウト再設計

利用者は現行の1媒体1カード表示を不採用と判断した。約150校、535媒体、最大4教科を扱うため、
FileMakerのLISTと入稿チェックの一覧性をWeb上で再現する。

承認済み要件:

- 初期表示は`LIST`。
- `LIST`、`入稿チェック`、`作業登録`をタブで切り替える。
- 同じ学校ブロックと検索条件を使い、タブごとに列セットだけを変える。
- ページングを媒体レコード単位から学校・制作単位へ変更する。
- 学校ヘッダーにみくにコード、日能研コード、分類、学校名をまとめる。
- 学校内に問題、解答のみ、解説解答、解答用紙、傾向と対策等の実在媒体を並べる。
- 各媒体内は国語、算数、社会、理科の固定順。
- FileMaker同様、工程は左から右へ進む横長表にする。
- 学校、媒体、科目は横スクロール時もsticky表示する。
- 初期10校／ページ、25校・50校へ切替可能にする。
- 媒体フィルターをタブとは別に設ける。
- FileMakerの工程名・項目・意味は変更しない。
- 見た目だけでなく、同じ学校がページ境界で分断されないデータ取得へ変更する。

実装順:

1. `MGinbonLedgerController`を制作単位ページングへ変更する。
2. 学校→媒体→四教科→工程の表示用データを返す。
3. `resources/js/Components/MGinbon/`へ学校ブロックと工程セルを共通部品として作る。
4. CoordinatorのLISTタブを最初に完成させる。
5. 同じ部品へ入稿チェック列セットを適用する。
6. User作業登録画面を同じ学校構造へ置き換える。
7. 青山学院中等部等で学校内の全媒体と四教科がまとまることを画面確認する。

## 重要な業務ルール

- 問題と解答のみは通常4教科セットで動く。
- 解説解答、解答用紙、傾向と対策等は科目別で動く場合がある。
- 解答用紙はα版だけに付く。
- 傾向と対策は学校単位で、第二回等へ重複作成しない。
- `初校出稿前チェック`は初校出稿前の体裁・構成確認で、文字校正ではない。
- 初校校正は初校出稿後の文字突き合わせ。
- 校正①は再校出稿前のスキャン図照合。
- 校正②は再校出稿後の文字突き合わせ。
- 作図、チェック、オペレーション、校正はUserが選択できる。文字入力とスキャンは一般User対象外。
- Userの職種で選択工程を固定しない。
- DB上の正式名`作図・スキャン`は変更せず、User画面だけ`作図`と表示する。
- 社員／外注先等の過去値を推測で分割・自動対応しない。

## 検証済み事項

- PHP構文確認済み。
- `php artisan test tests/Unit/MGinbon`: 5 tests / 21 assertions passed。
- `npm run build`: 成功。既存のBrowserslist／glob非推奨警告のみ。
- User向けデータに文字入力が出ず、作図表示が出ることを確認済み。
- 4教科一括登録、完了、未完了戻し、削除、開始日優先規則をトランザクション検証済み。
- Coordinator詳細で現在担当者、登録／作業中／完了、登録日／完了日を取得できる。

## Git・環境上の注意

- この区切りのコミットはローカル実装のみ。さくら本番へは未デプロイ。
- 本番用`MGINBON_DB_*`は未設定で、専用DBマイグレーションも本番未実行。
- 本番作業を行う場合は`DEPLOY_SAKURA.md`に従い、SSHコマンドを事前提示して承認を得る。
- MGinbon専用マイグレーションの接続先DB名を必ず確認する。
- `migrate:fresh`、drop、truncateを行わない。
- 売上DBへ触れない。
- `z_NDBSystem/`はGit対象外の受領資料として扱う。
- 既存のUser／Claude変更を巻き戻さない。


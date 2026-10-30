# 部署の作業・一般向け記録表示：Codex引継ぎプロンプト

更新: 2026-10-30 JST。利用者がここで区切り、新しいCodexへの引継ぎを依頼した。まずこのファイルを最後まで読み、確認済みの画面を最初からやり直さない。

## 現在地・次の一手

**SuperAdmin限定の試用版は、実装・コード検証・さくら配備・依頼した画面確認まで完了。一般公開は未承認。**
次の一手は、管理資料を読み合わせたうえで、一般公開の対象と開始時期を利用者と決めること。公開指示がなければ権限変更・配備を始めない。本人の「引継ぎ」「yes」「成功」を一般公開の許可と解釈しない。

## 最初に読む資料

1. 作業先のAGENTS.md。共有ツリーのClaude変更を保護し、CLAUDE.mdは編集しない。
2. z_instructions/JOB_EVALUATION_CONTROL1.md（決定と証拠の入口）
3. z_instructions/JOB_EVALUATION_ROADMAP1.md（古い順序記載より最新の追記を優先）
4. z_instructions/DEPARTMENT_RECORDS_PLAN1.md / DEPARTMENT_RECORDS_MANAGER1.md
5. 評価側に触れる場合のみEVALUATION_SCORE_PLAN1.md / MANAGER1.md / EVALUATION_SCORE1_PROMPT.md。管理移設はarchived/WORKLOAD_MANAGEMENT_MOVE_*で完了済み。
6. UIはCONSOLIDATED_01、日付/予定はCONSOLIDATED_05、作業量はCONSOLIDATED_07。配備前はDEPLOY_SAKURA.md。

この会話の利用者発言と保存済み管理記録が根拠。過去チャットにアクセスできなければ、そのことを明示し、未読の会話を読んだとは言わない。

## コードとGitの正本

- 専用ブランチ: **feature/department-records-preview-20261030**
- 隔離ツリー: **/tmp/sunbwork-workload-management-deploy-20261030**
- 引継ぎ作成前の保存済みHEAD: **c18de6c9b**。今回の引継ぎ資料更新も同ブランチへcommit/pushするため、実際の最新はブランチ先端を確認する。
- 最後に配備を確認したさくらアプリ版: **71d5b646b**、main、追跡対象変更なし。これは確認時点の証拠で、現在も同じとは断定しない。
- 71d5b646b以降の今回の作業は画面確認結果などの資料更新だけ。引継ぎ作成時にapp/resources/routes/database/public/buildの差分なしを確認済み。
- 共有 **/home/w229/SunBwork** はClaudeの並行作業がある。checkout/reset/上書き/無条件pullをしない。origin/mainに今回の専用版が統合済みとは思わない。
- /tmpが消えていたら、共有ツリーを変更せずgit showで保存ブランチの資料を読み、新しい隔離worktreeを作る。ローカルrefがなければ必要なfetchを行う。存在する古い未追跡資料を最新と決めない。
- 本番へ無条件git pull origin mainは禁止。現在の版・変更・取得候補・差分を照合し、別作業を巻き込まない専用版だけを反映する。

## 利用者が決めた目的と画面

- 「部署の作業」は仕事の埋まり具合を確認し、本人が記録した仕事を管理側へ伝えるための入口。
- Userメニューのカレンダー直後に部署の作業。ただし今はINFO SuperAdminだけ。
- 内側は「部署の作業予定」「みんなの作業記録」の2表示。新規入口の既定は予定。
- 通常の「予定表」はSuperAdminも共有予定を直接表示。旧4タブには戻さない。
- 記録は同じ会社/部署の全員、日単位。**今日＋過去4営業日**。過去日は土日/会社登録祝日を除外し、休日の今日も表示する。週/月の分析は出さない。
- マイ/依頼ジョブはジョブとして紫、簡易作業ピンク、会議/外出緑、その他業務slateの時間バー。一般向けでは残業を独立した強調色/並替にせず、その作業区分内に含める。
- 初期順はadmin/usersの保存済み設定順（User.ordered）。役職/社歴/雇用形態を新たな独自規則で並べ直さない。総時間/種類別/名前順へ切替可能。同値は設定順。
- 評価点・順位番号・係数・案件登録順位・詳しい分析は表示しない。**専用JSONにも点数や非公開の件名/本文を入れない。**
- 本人にだけ記録時間/作業種類未指定/予定の空きを表示し、該当日の入力へ案内。他人の不足案内は出さない。勤務時間/休憩が確認できなければ空きは判定不可。未就労/欠勤とは断定しない。
- 会議/配分/応援や「その他業務」も仕事。通常予定だから不十分と扱わない。入力件数や種類を選んだだけのボーナスは作らない。
- 本人リンクはcalendar.indexにdateと**view=day**を指定。以前月/週表示だった場合も対象日の日表示で開く。
- 上部の白い説明/日付枠と青い本人枠は1024px以上で横並び、狭幅は縦。全員バーは下段全幅。

## 実装の要点

- app/Services/DepartmentWorkRecords.php: 既存PersonalWorkSchedule.availableでINFO SuperAdmin限定。会社部署を認証コンテキストに固定。日付窓もサーバ制限。
- app/Http/Controllers/Schedule/DepartmentWorkRecordsController.php: 読取専用GET、private/no-store。
- GET schedule.department_work.records（/schedule/department-work/records）。既存管理分析APIをそのまま返さず明示ホワイトリスト投影。
- 返すpeopleはid/name/minutes/breakdown(job,quick,meeting,other)。mineは認証本人だけのminutes/unclassified_minutes/gap_minutes。他人のuser_id指定で案内を取得できない。
- WorkloadTrialAnalysis.analyzeへ末尾separateOvertime引数（既定true）を追加。今回のAPIだけfalseにして残業を元の区分に保持。**既存の採点式・既定の管理表示は不変。**
- 本人の空きは完結した実勤怠と勤務枠/休憩が揃う日だけ、当日は現在時刻まで。夜勤/不明はnull。未分類は既存V3活動unspecified。日報なしを欠勤としない。
- ClerkCalendarHolidays.forCompanyのseed=falseで会社祝日を読む。DB書換えなし。
- DepartmentWorkRecords.vue、Schedule/Index.vue、GETルート/Ziggy、独立DepartmentRecordsPreviewChangelogSeeder。共通Seederは呼出1行のみ。migrationなし。
- UserNavigationTabs/既存部署ボード/管理分析/入力試用APIの権限は今回広げていない。将来公開する場合も管理API/入力APIのguardを一括解除しない。

## 検証と利用者の確認済み証拠

合成SQLite :memory:で**76テスト311断言**成功。権限、日付、他社/他部署除外、投影への点数/private情報混入防止、休憩/重複/残業分類、既存集計回帰を含む。対象PHP構文、ESLint、ルート/Ziggy、/membersビルド成功。manifest参照490欠落0。実Vue scriptで応答逆転対策/並替/エラー時の古い表示除去/URL同期を検証。

利用者が段階ごとに「成功」と確認済み:
- 別画面からの部署入口は予定が初期表示。
- 総時間の降順とユーザー一覧の設定順への復帰。初期設定順は同部署のadmin/usersと一致。
- 本人案内3項目、他人には案内なし、評価点/順位番号なし。
- 5日候補、別日切替にエラーなし、record_dateのURL一致と再読込保持。
- 月/週にしていたカレンダーでも本人リンクが対象日の日表示になる。
- 広幅の白青横並び、狭幅の縦並び、バー下段全幅。
- **最後の確認: 実際の一般Userで新規APIは403、SuperAdminではデータが見える。**

ビルド/配備を画面確認の代用にしない。未依頼の全role/全端末/全勤怠条件の網羅確認まで成功と広げない。数値の実勤怠との全件突合は行っていない。

## 残件と停止線

- 一般公開はまだ許可されていない。対象会社/部署/ロールと開始時期を利用者が決める。管理分析の公開とは分離し、入力側も別判断。
- 月別の上長コメントを利用者+Codex/Claudeで読む機能は構想のみ、後工程。
- 入力の使いづらさ、案件構造の残件、マイジョブ/簡易入力のすり合わせ等は横断台帳の既存後工程として保持。引継ぎを機に勝手に新機能や評価微調整を始めない。
- 閉じた過去月の網羅再調査や順位を特定人物の目標へ寄せる作業は再開しない。
- 本番sales DBの閲覧/複製禁止。破壊的DB操作は禁止事項と事前確認をAGENTSで確認する。
- SSHは正確なコマンドを先に提示し承認後だけ実行。過去に承認されたコマンドは別版の新操作の包括承認ではない。
- 利用者の「成功」は直前に列挙した確認の全成功。曖昧な確認依頼を避け、成功/違った項目の回答形式を示す。

## 新しいCodexの最初の返答

読んだ資料/使用したブランチと版、試用版の確認完了、一般公開は未承認、を短く伝える。その後は利用者が指定する次の作業に従う。既確認画面の再試験や本番SSHを勝手に始めない。

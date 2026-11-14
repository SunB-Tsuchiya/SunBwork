# 再開: 情報出版 部署の作業公開・マイジョブ入力統合

最初にAGENTS.md、`JOB_EVALUATION_CONTROL1.md`、`INFO_DEPARTMENT_MYJOB_PLAN1.md`、`INFO_DEPARTMENT_MYJOB_MANAGER1.md`、`DEPARTMENT_RECORDS_PLAN1.md` / MANAGER、`MYJOB_UNIFIED_BOX_PLAN1.md` / MANAGER、`USER_WORK_RELEASE_MANAGER1.md`、`JOB_EVALUATION_ROADMAP1.md`を読む。今回の基準は隔離ブランチ `feature/info-department-myjob-plan-20261114` 起点 `ae46644ea`。最後に確認した本番アプリは `0ab725a78` だが、本ターンで本番SSH再照合はしていない。共有mainにはClaude作業と未追跡ファイルがあるため触れない。

2026-11-14の利用者決定: 添付画像で見るのは松本さんの名前と下段に「部署の作業」がない点。公開対象はサン・ブレーン情報出版全員。Super Admin試用の部署予定/記録を通常運用へ移す。マイジョブの標準作成は現在の簡易入力へ統合し、旧形式の選択肢と過去記録を保持する。急いで実装せず調査・計画を先に作る。サンエー向け公開、製版/オンデマンドの仕事機能、評価点の一般公開は指示されていない。

静的調査: メニュー・Schedule画面・部署予定/記録GETはSuper Admin試用guard、本人BOX/簡易保存はINFO一般User可。カレンダーの簡易表示とドラッグ後入口はまだSuper Admin試用guard。統合BOXはPersonalWorkEntryと旧自己割当を投影しコピーしない。段階案は(1)部署閲覧だけINFO全員、(2)カレンダー標準マイジョブを簡易入力へ、(3)表示語/旧形式導線を統一、(4)限定配備と新規範囲の画面確認。コード・ビルド・追加配備・今回の画面確認は未実施。既成功のSuper Admin画面試験を繰り返さない。

2026-11-14: 利用者は段階順について「この順で実装してよい」と回答。直後に「ちょっと中断したいので区切りのいいところで止める」と指示。アプリコード変更前を区切りとして停止。2026-11-15に再開指示があり中断解除。

2026-11-15現況: 隔離ブランチで段階1〜3のアプリ修正済み。`PersonalWorkSchedule::canViewDepartmentWork()` を新設し、部署予定/記録GET・Userタブ・Schedule画面・Clerk入口だけに使う。管理分析の `available()` は保持。カレンダーのINFO本人入力を `personalWorkEnabled` に分け、「マイジョブ登録」からQuickWorkEntryModal(entryPoint=myjob)へ接続。旧形式入口と既存の編集/進行表入口は保持。BOXと部署表示の語を標準マイジョブへ統一。PHP関連35件177断言、Vue入力テスト、Viteビルドが成功。追加配備・今回の一般User画面確認は未実施。最後に確認した本番版 `0ab725a78` は前ターンの証拠で、この再開ターンではSSH照合していない。

次の一手: 本番版・他作業を読み取り専用SSHで照合する。正確なコマンドをAGENTS.mdどおり先に提示して利用者確認を得る。固定差分の配備準備後、新しい一般User範囲だけ画面確認する。設計承認と既成功のSuper Admin試用試験は繰り返さない。

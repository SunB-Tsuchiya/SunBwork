# 部署の作業・一般向け記録表示 再開プロンプト1

2026-10-30 JST。最初にAGENTS.md、JOB_EVALUATION_CONTROL1.md、DEPARTMENT_RECORDS_PLAN1.md、DEPARTMENT_RECORDS_MANAGER1.mdを読む。過去評価の再調整を勝手に再開しない。

現在: 利用者yesで設計承認、アプリ実装と合成検証76件311断言まで完了。次は承認付き読取SSHで現在版/branch/変更を確認し、別作業を含めない専用配備を準備する。配備と一般公開は未承認、画面は未確認。詳しい検証証拠はMANAGER末尾。

利用者決定: User「部署の作業」に予定/みんなの作業記録の2タブ。予定が既定。記録は同部署全員の日別直近5営業日、内容別色/名前時間等の並替、点数順位番号なし。本人の記録確認を促す案内は本人だけ。会議やその他業務も仕事なのでジョブでないだけで不十分扱いしない。管理画面/共有予定は維持。最初はINFO SuperAdmin限定プレビュー、一般公開は別許可。

安全境界: 一般用JSONに点数/係数/private内容/他人の不足案内を入れない。サーバで会社部署/日付窓/本人を制限。既存管理APIをそのままクライアントへ返さない。試用入力APIを緩めない。勤務時間不明を未記録と断定しない。JST/UTC混在は既存helperを使用し、重複/休憩は既存V3の認定時間を再利用。sales DB禁止。

基準HEAD72cb7a6ea、最後に確認した本番ce93d3171。隔離ツリー/tmp/sunbwork-workload-management-deploy-20261030。消えていれば保存branchを調べ再構築。共有/home/w229/SunBworkはClaudeの別作業があるので編集/resetしない。origin/mainや本番最新は都度読取で確認し、別作業を含めない専用配備を作る。

検証/配備/画面確認/一般公開は別状態。SSH前は正確なコマンド提示と承認、条件不一致で更新前停止。大量DB変更やmigrationは計画なし。利用者の成功は直前に列挙した条件だけ全成功としてMANAGERと台帳へ同時記録する。

実装: DepartmentWorkRecords専用サービス/ControllerとVue部品、Schedule/Indexの部署2表示、GETルート/Ziggy、独立DepartmentRecordsPreviewChangelogSeeder。WorkloadTrialAnalysisは最後のseparateOvertime引数（既定true）をこのAPIだけfalseにして残業を元の4区分内に維持。採点自体は不変。休日の今日は表示し、過去4日は土日/会社祝日除外。本人の空きは完結勤怠と勤務枠/休憩がある日だけ、夜勤/不明null。既存予定ボード/共有予定/入力guardは維持。

最終ビルド成功（20.24秒）。manifest参照490件欠落0。新規部品は独立manifestキーではなくSchedule/Indexチャンク内に同梱されていることを実成果物で確認。

2026-10-30 配備前読取確認: 利用者が正確なSSHコマンドを承認。さくらce93d3171/main/追跡対象変更なしを確認。準備済み680b70634は同版の子孫で、アプリ差分は今回の専用API/集計引数/2表示/GETルート/Ziggy/独立変更履歴だけ。まだ更新していない。次は現在版/branch/変更なし/取得版/指定9ソース差分一致を条件とする限定配備コマンドを提示し承認を得る。PHP6ファイル構文確認、独立DepartmentRecordsPreviewChangelogSeederだけ--force、キャッシュクリアを予定。migration/一般公開/他作業取得なし。

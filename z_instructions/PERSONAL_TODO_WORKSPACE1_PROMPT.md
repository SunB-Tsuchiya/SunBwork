# 個人TODOワークスペース 再開

AGENTS.md/JOB_EVALUATION_CONTROL1、PERSONAL_TODO_WORKSPACE_PLAN1/MANAGER1、PERSONAL_TODO_RESEARCH_20261117、PERSONAL_MONTH_MANAGER1を読む。branch feature/personal-todo-workspace-plan-20261117、worktree /tmp/sunbwork-personal-month-resume-20261117、基準70926c894。本番直近実行証拠ff17e8671。共有mainのClaude/利用者変更を保護。
利用者はリッチなTODOの案を承認。第1段階は専用3列/狭幅詳細、今日やる・重要・個人リスト・浅いチェック項目・検索/自動一覧/設定と左の直近欄。通知/繰返し/まとめ操作、MyJob連携は後段、本人専用・INFO SuperAdmin限定のまま。既存個人カレンダーは画面確認済みなので反復しない。
具体設計は既存itemsへメタ4列、新lists/settings、同一ID/旧hash再送/旧画面の未送信メタ保持、今日と締切の分離、全チェックで親を自動完了しない、リストアーカイブでタスクを消さない。現在は具体設計確認待ちで、アプリ/DB変更なし。
次は確認後にmigrationと所有scope/互換Serviceを実装し合成テスト。SSHは正確なコマンドの事前承認、一般公開は別承認。資料/実装/検証/配備/利用者画面の状態を分ける。

2026-11-17具体設計を利用者承認。itemsのメタ4列・本人リスト/表示設定の専用migration、所有scope、旧hashと未送信メタ保持、専用ページ/JSON APIの実装を開始。既存ID/期限/メモ/完了は保持、試用対象を変更しない。実装途中、今回検証/配備/画面確認は未実施。次は認可/後方互換と画面を接続する。

2026-11-17実装・コード検証完了（未配備）: 本人専用の3列TODO/狭幅詳細、今日やる・重要・検索/期限一覧、個人リストの追加/編集/全有効並べ替え/アーカイブ復元、チェック項目、版付き設定を実装。既存itemsの同一ID/旧hash再送/旧編集の未送信メタ保持を維持。専用migrationは2026_11_17_160001_add_personal_todo_workspace.phpだけ。通知/繰返し/MyJob連動/一般公開は後続。
検証: SQLiteメモリ合成でPersonalTodoWorkspaceTest + PersonalMonthCalendarTest（継承回帰含む）230件2426断言成功。実Vue personalTodoWorkspace/personalMonthCalendar/unifiedMyJobInputの3スクリプト成功（競合入力保持/同一キー再送/二重保存/未保存離脱/遅い詳細応答/チェック親独立/設定とリスト下書き）。本番用/membersビルド成功。利用者画面確認は未実施。
次の1手: 固定元ff17e8671から今回差分だけの本番配備をSSH正確コマンド+SHA256で確認する。INFO SuperAdmin限定を維持し、専用migrationのみ--path --forceで実行、ChangelogSeederは実行しない。既確認の旧配置/日報/MyJob保存を再依頼しない。

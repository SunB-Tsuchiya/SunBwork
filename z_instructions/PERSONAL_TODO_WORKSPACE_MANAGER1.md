# 個人TODOワークスペース 進捗1

2026-11-17。利用者は公式アプリ調査の案へ「OKです。その案で行きましょう」と承認。第1段階の専用ページ/今日やる/重要/個人リスト/チェック項目/検索・表示設定を詳細設計。繰返し/通知/まとめ操作・TODO→MyJobは後段、本人専用・INFO SuperAdmin限定を維持。
調査済み: 既存PersonalMonthCalendarの本人scope/version/再送、PersonalTodoList/usePersonalCalendarState、routesとCalendarController、レイアウト規則。既存IDの共用と旧モーダル編集で追加メタを消さない方針。
状態: 全体案は承認済み、具体DB/互換/完了の意味を含むPLAN/MANAGER/PROMPT作成済み。アプリ変更/検証/DB/配備/今回画面確認は未着手。
次の1手: 具体設計の確認後、専用migrationと認可・互換性の合成テストから実装。一般公開は別承認。
基準70926c894、branch feature/personal-todo-workspace-plan-20261117、worktree /tmp/sunbwork-personal-month-resume-20261117。本番直近ff17e8671。主worktreeのClaude変更は保護。

2026-11-17具体設計を利用者承認。itemsのメタ4列・本人リスト/表示設定の専用migration、所有scope、旧hashと未送信メタ保持、専用ページ/JSON APIの実装を開始。既存ID/期限/メモ/完了は保持、試用対象を変更しない。実装途中、今回検証/配備/画面確認は未実施。次は認可/後方互換と画面を接続する。

2026-11-17実装・コード検証完了（未配備）: 本人専用の3列TODO/狭幅詳細、今日やる・重要・検索/期限一覧、個人リストの追加/編集/全有効並べ替え/アーカイブ復元、チェック項目、版付き設定を実装。既存itemsの同一ID/旧hash再送/旧編集の未送信メタ保持を維持。専用migrationは2026_11_17_160001_add_personal_todo_workspace.phpだけ。通知/繰返し/MyJob連動/一般公開は後続。
検証: SQLiteメモリ合成でPersonalTodoWorkspaceTest + PersonalMonthCalendarTest（継承回帰含む）230件2426断言成功。実Vue personalTodoWorkspace/personalMonthCalendar/unifiedMyJobInputの3スクリプト成功（競合入力保持/同一キー再送/二重保存/未保存離脱/遅い詳細応答/チェック親独立/設定とリスト下書き）。本番用/membersビルド成功。利用者画面確認は未実施。
次の1手: 固定元ff17e8671から今回差分だけの本番配備をSSH正確コマンド+SHA256で確認する。INFO SuperAdmin限定を維持し、専用migrationのみ--path --forceで実行、ChangelogSeederは実行しない。既確認の旧配置/日報/MyJob保存を再依頼しない。

# 個人TODOワークスペース 再開

AGENTS.md/JOB_EVALUATION_CONTROL1、PERSONAL_TODO_WORKSPACE_PLAN1/MANAGER1、PERSONAL_TODO_RESEARCH_20261117、PERSONAL_MONTH_MANAGER1を読む。branch feature/personal-todo-workspace-plan-20261117、worktree /tmp/sunbwork-personal-month-resume-20261117、基準70926c894。本番直近実行証拠ff17e8671。共有mainのClaude/利用者変更を保護。
利用者はリッチなTODOの案を承認。第1段階は専用3列/狭幅詳細、今日やる・重要・個人リスト・浅いチェック項目・検索/自動一覧/設定と左の直近欄。通知/繰返し/まとめ操作、MyJob連携は後段、本人専用・INFO SuperAdmin限定のまま。既存個人カレンダーは画面確認済みなので反復しない。
具体設計は既存itemsへメタ4列、新lists/settings、同一ID/旧hash再送/旧画面の未送信メタ保持、今日と締切の分離、全チェックで親を自動完了しない、リストアーカイブでタスクを消さない。現在は具体設計確認待ちで、アプリ/DB変更なし。
次は確認後にmigrationと所有scope/互換Serviceを実装し合成テスト。SSHは正確なコマンドの事前承認、一般公開は別承認。資料/実装/検証/配備/利用者画面の状態を分ける。

2026-11-17具体設計を利用者承認。itemsのメタ4列・本人リスト/表示設定の専用migration、所有scope、旧hashと未送信メタ保持、専用ページ/JSON APIの実装を開始。既存ID/期限/メモ/完了は保持、試用対象を変更しない。実装途中、今回検証/配備/画面確認は未実施。次は認可/後方互換と画面を接続する。

2026-11-17実装・コード検証完了（未配備）: 本人専用の3列TODO/狭幅詳細、今日やる・重要・検索/期限一覧、個人リストの追加/編集/全有効並べ替え/アーカイブ復元、チェック項目、版付き設定を実装。既存itemsの同一ID/旧hash再送/旧編集の未送信メタ保持を維持。専用migrationは2026_11_17_160001_add_personal_todo_workspace.phpだけ。通知/繰返し/MyJob連動/一般公開は後続。
検証: SQLiteメモリ合成でPersonalTodoWorkspaceTest + PersonalMonthCalendarTest（継承回帰含む）230件2426断言成功。実Vue personalTodoWorkspace/personalMonthCalendar/unifiedMyJobInputの3スクリプト成功（競合入力保持/同一キー再送/二重保存/未保存離脱/遅い詳細応答/チェック親独立/設定とリスト下書き）。本番用/membersビルド成功。利用者画面確認は未実施。
次の1手: 固定元ff17e8671から今回差分だけの本番配備をSSH正確コマンド+SHA256で確認する。INFO SuperAdmin限定を維持し、専用migrationのみ--path --forceで実行、ChangelogSeederは実行しない。既確認の旧配置/日報/MyJob保存を再依頼しない。

2026-11-17本番試用反映完了: 利用者が正確コマンド/SHA256 edc4854c2079f38c94157b058f8dc60f4b7eeb7f8b6ad8444b216c88875df3edを承認。固定元ff17e8671→1d7977e08を配備、専用2026_11_17_160001 migration DONE、personal_todo 10 routes、cache clear、manifest497/refs528/missing0/base=/members、最終HEAD/追跡clean照合成功（SSH終了0）。既知未追跡6件を保護、Seeder/一般公開/通知なし。
状態: 実装・コード検証・本番SuperAdmin限定反映は完了。今回利用者画面は未確認。次の1手は、専用ページへの遷移とカレンダー戻り、今日やる/重要/チェックと親完了の独立、リスト・設定の保存を今回追加範囲だけ確認する。既存カレンダー配置/MyJob/日報の成功済みチェックは繰り返さない。

2026-11-17利用者画面確認: 直前に依頼した4条件（専用ページ/戻る、期限と今日やる・重要/チェックと親完了、リスト追加割当アーカイブで保持、表示設定とカレンダー左欄反映）を「成功」で確認。追加要望として検索と追加の混同を指摘。確認済み機能は再試験依頼しない。
確定修正: 中央を上の淡青「新しいTODOを追加」と下の白「一覧」の別カードへ分離。検索・並び順・完了表示は一覧内、入力ラベルとボタンの文言も用途を明示。表示のみ、API/DB/権限変更なし。次はビルドと差分固定後にSSH事前確認、配備後は配置だけ確認。

2026-11-17追加/一覧ブロック修正を本番反映: 利用者承認のSHA256 75a69ed7b7b2f24012c0cfb94efb5fb283b45ce0e6d190fa6e94eaa7bb239326で固定元1d7977e08→f3dae4b89。差分照合/未追跡保護/cache clear/manifest497 refs528 missing0 /members/追跡clean成功、SSH終了0。DB/保存処理/試用権限は変更なし。今回の画面確認は未実施。次は再読み込み後に上段青い追加ブロックと下段白い検索含む一覧ブロックの見分けやすさだけ確認する。既確認の保存/親子完了/リスト/設定は再依頼しない。

2026-11-17利用者が追加/検索一覧ブロックの分離を「成功」で確認。追加の確定要望は並び順の文字と選択矢印の重なり解消。保存版6a11e5bc5を新隔離worktree /tmp/sunbwork-todo-sort-width-20261117（branch feature/personal-todo-sort-width-20261117）へ復元し、一覧の並び順selectのみ最小幅7rem・左3/右9の余白へ変更する。旧/tmp作業場所は消失、共有主作業のziggy等は保護。本番直近確認f3dae4b89。次はビルド後、差分固定のSSH事前確認。保存/API/DB/権限は変更なし、成功済み機能の再確認不要。

2026-11-17並び順の幅修正: 実Vue操作テスト成功、本番用/membersビルド32.86秒成功、manifest参照欠損なし。実装/検証済み、今回未配備・画面未確認。

2026-11-17並び順select幅の本番反映完了: 利用者がSSH正確コマンドとSHA256 8c0e32f101124fd5c0c5394410c11acbf13f09e5441f6f44ce72bbd2ab884afbを承認、固定元f3dae4b89→b74dd5594。差分/未追跡保護/追跡clean/cache clear成功。manifest497 refs528 missing0 base=/members、SSH終了0。今回画面未確認。次の1手は再読み込みで並び順の文字と矢印が重ならないことだけ利用者確認。DB/保存処理/試用限定変更なし。

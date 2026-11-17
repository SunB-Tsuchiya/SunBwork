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

2026-11-17本番試用反映完了: 利用者が正確コマンド/SHA256 edc4854c2079f38c94157b058f8dc60f4b7eeb7f8b6ad8444b216c88875df3edを承認。固定元ff17e8671→1d7977e08を配備、専用2026_11_17_160001 migration DONE、personal_todo 10 routes、cache clear、manifest497/refs528/missing0/base=/members、最終HEAD/追跡clean照合成功（SSH終了0）。既知未追跡6件を保護、Seeder/一般公開/通知なし。
状態: 実装・コード検証・本番SuperAdmin限定反映は完了。今回利用者画面は未確認。次の1手は、専用ページへの遷移とカレンダー戻り、今日やる/重要/チェックと親完了の独立、リスト・設定の保存を今回追加範囲だけ確認する。既存カレンダー配置/MyJob/日報の成功済みチェックは繰り返さない。

2026-11-17利用者画面確認: 直前に依頼した4条件（専用ページ/戻る、期限と今日やる・重要/チェックと親完了、リスト追加割当アーカイブで保持、表示設定とカレンダー左欄反映）を「成功」で確認。追加要望として検索と追加の混同を指摘。確認済み機能は再試験依頼しない。
確定修正: 中央を上の淡青「新しいTODOを追加」と下の白「一覧」の別カードへ分離。検索・並び順・完了表示は一覧内、入力ラベルとボタンの文言も用途を明示。表示のみ、API/DB/権限変更なし。次はビルドと差分固定後にSSH事前確認、配備後は配置だけ確認。

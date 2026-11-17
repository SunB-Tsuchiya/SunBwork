# 個人TODO繰り返し 再開
AGENTS→JOB_EVALUATION_CONTROL1→PERSONAL_TODO_REPEAT_PLAN1/MANAGER1を読む。worktree /tmp/sunbwork-todo-sort-width-20261117、branch feature/personal-todo-sort-width-20261117、基準570642e9a、本番直近b74dd5594。過去/tmp消失があるので存在だけで版を決めない。共有main/Claude変更を保護。
利用者「繰り返しで行きましょう」。今回は日/平日/週/月/年を完了時次回1件として提案、具体設計確認待ちでアプリ未実装。TODO専用ページとカレンダーの同一ID/本人scope/旧hashと未送信メタ保持/INFO SuperAdmin限定を維持。通知/公開/MyJob連携は今回しない。完了取り消しは未編集後続だけ取り下げ、再完了でそのID復元、ユーザーが削除/編集した後続は消さない/復活させない。
次は具体設計確認後に実装。今回画面確認は未実施、既存の専用TODO4条件/追加検索分離/幅は成功済みで反復しない。SSHは毎回正確なコマンドと事前確認、配備は専用migrationのみ。実装/検証/配備/画面を別管理する。

2026-11-17具体設計を利用者承認。繰り返し専用migration/計算と後続発行・取り消しサービス/共通完了経路の実装を開始。一般公開は別承認のまま。現在実装中、今回テスト/配備/画面確認は未実施。

2026-11-17実装・コード検証完了（今回未配備）: 毎日/平日/週/月/年と終了日、完了時の未来次回1件、基準日保持、チェックUUID再発行と未チェック/今日解除、未編集次回だけの取り下げと同一ID復元を実装。内部anchor/後続IDはクライアントから設定不可。共通ownerロック→元回→後続回とself unique/versionで再送/競合を保護。旧hash/未送信ルール保持、旧modal完了の次回案内も接続。
コード検証: 合成SQLite memoryのPersonalTodoRecurrenceTest/WorkspaceTest/MonthCalendarTest（継承回帰含む）356件3731断言成功。初回回帰の4エラーはWorkspace合成DBが今回カラム未適用だったため、専用migration適用をfixtureへ揃え再実行成功。実Vue3スクリプト成功（ルール明示保存/409保持/次回案内/チェック独立/旧modal・月週日共有/旧MyJob入力）。変更PHP5件構文成功。/membersビルド29.45秒、manifest497 refs528 missing0成功。
状態: 実装/コード検証済み、本番未反映・今回利用者画面未確認。次の1手は固定元b74dd5594から今回差分だけの配備を正確SSHコマンド/SHA256で確認すること。今回専用2026_11_17_200001 migrationだけ--path --force、Seeder/一般公開/通知は行わない。

実装保存先: feature/personal-todo-repeat-20261117（設計時のsort-widthブランチから専用に分岐）、作業場所は /tmp/sunbwork-todo-sort-width-20261117 を継続。次の配備targetはこのブランチの固定commitとする。

2026-11-17本番試用反映完了: 利用者が正確SSHコマンドとSHA256 6ed3475f728f072bdaebbcda9b786f4233f85167e055cb34ce0c641f77db961bを承認。固定元b74dd5594→971f5359f、差分/本番版/既知未追跡保護/追跡clean照合成功。今回専用2026_11_17_200001 migration 66.16ms DONE、cache clear、manifest497 refs528 missing0 base=/members、SSH終了0。一般公開/通知/Seeder/既存TODO一括変更なし。
状態: 実装・コード検証・本番SuperAdmin限定反映は完了。今回利用者画面は未確認。次の1手は①毎日のルール保存、②完了で次回1件/チェックと今日解除、③未編集次回の取り消し・再完了でも1件、④次回編集後は元の完了取り消しを拒否し保護、の新規4条件だけ確認。既確認のレイアウト/旧保存/リスト・設定は再依頼しない。

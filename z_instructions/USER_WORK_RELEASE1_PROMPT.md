# 再開: 情報出版ユーザー向け仕事・案件（中断中）

`AGENTS.md` → `JOB_EVALUATION_CONTROL1.md` → `USER_WORK_RELEASE_PLAN1.md` → `USER_WORK_RELEASE_MANAGER1.md` の順に読む。隔離作業の起点は `8a4621b3b`。独立した公開作業ブランチはまだ作成していない。共有 `/home/w229/SunBwork` はClaude作業とビルド資産があるため編集しない。

利用者は2026-11-14に全社一斉案を撤回し、情報出版の一般UserとUser画面兼用者だけへ戻した。公開実装は中断中。製版・オンデマンドのユーザーメニューをサンエーと同様に簡潔にする作業を先行する。サンエー自体は別工程。Super Admin試用とCoordinator分類入口の画面確認は済み。一般Userの権限・画面確認は未実施。評価点の正式運用と分類統合/削除はこの公開で決めない。

2026-11-14: 製版・オンデマンドの6メニュー整理は隔離worktreeで実装、JS描画テスト/ESLint/Vite build/diff検証済み。配備と利用者画面確認は未実施。開発コンテナ停止のためPHP構文チェックは未実施。現在の変更ファイルは `HandleInertiaRequests.php`、`UserNavigationTabs.vue`、`tests/js/workHubNavigation.test.cjs` と資料。ビルド資産も隔離worktreeにある。次は差分保存・Sakura配備準備。情報出版の公開実装は利用者の再開指示後に権限表から始める。SSHは必ず正確なコマンドを先に提示し、利用者確認後に実行する。

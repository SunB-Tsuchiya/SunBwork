# 再開: 情報出版ユーザー向け仕事・案件

`AGENTS.md` → `JOB_EVALUATION_CONTROL1.md` → `USER_WORK_RELEASE_PLAN1.md` → `USER_WORK_RELEASE_AUDIT1.md` → `USER_WORK_RELEASE_MANAGER1.md` の順に読む。隔離ブランチ `feature/info-user-work-rollout-20261114` の起点は `f3405ea2c`。共有 `/home/w229/SunBwork` はClaude作業とビルド資産があるため編集しない。

利用者は2026-11-14に全社一斉案を撤回し、情報出版の一般UserとUser画面兼用者だけへ戻した。同日の「では情報出版に進みましょう」で調査から再開し、Super Adminで確認済みのジョブ関連を引き継ぐ。製版・オンデマンドの6メニュー整理は本番反映と画面確認済み。サンエーは別工程。Super Admin試用とCoordinator分類入口の画面確認は済み。一般Userの権限・画面確認は未実施。評価点の正式運用と分類統合/削除はこの公開で決めない。

2026-11-14: 製版・オンデマンドの6メニュー整理は本番 `dfb1362eb` へ反映し、利用者が両部署の6メニューとジョブ系ボタン非表示を画面確認済み。情報出版の調査は `USER_WORK_RELEASE_AUDIT1.md` にまとめた。削除されていた `userWorkHub` と `auth.canManageBroadProjects` は従来の試用条件で作業ブランチに復元し、差分チェック成功。PHPテスト・配備・一般User画面確認は未実施。次は本人入力/BOXの判定と部署作業分析の試用判定を分離し、INFOの一覧/保存/カレンダー/日報を揃える。SSHは必ず正確なコマンドを先に提示し、利用者確認後に実行する。

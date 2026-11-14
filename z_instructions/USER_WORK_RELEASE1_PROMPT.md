# 再開: 情報出版ユーザー向け仕事・案件（中断中）

`AGENTS.md` → `JOB_EVALUATION_CONTROL1.md` → `USER_WORK_RELEASE_PLAN1.md` → `USER_WORK_RELEASE_MANAGER1.md` の順に読む。隔離作業の起点は `8a4621b3b`。独立した公開作業ブランチはまだ作成していない。共有 `/home/w229/SunBwork` はClaude作業とビルド資産があるため編集しない。

利用者は2026-11-14に全社一斉案を撤回し、情報出版の一般UserとUser画面兼用者だけへ戻した。公開実装は中断中。製版・オンデマンドのユーザーメニューをサンエーと同様に簡潔にする作業を先行する。サンエー自体は別工程。Super Admin試用とCoordinator分類入口の画面確認は済み。一般Userの権限・画面確認は未実施。評価点の正式運用と分類統合/削除はこの公開で決めない。

2026-11-14: 製版・オンデマンドの6メニュー整理は専用ブランチ `feature/user-simple-nav-20261114` の `e39e784f5`（ソース/資料）、`7294e6e1b`（`/members` 用ビルド）へ保存済み。JS描画テスト/ESLint/Vite build/diff検証済み。利用者承認の固定SSH手順で本番 `78719c114`→`dfb1362eb` へ反映済み。PHP middleware構文、config/cache/view clear、manifest494件・参照資産525件・欠落0・`/members` baseを本番確認。利用者の「成功（両部署とも確認）」で製版/オンデの6メニューとジョブ系ボタン非表示を画面確認済み。今回工程は完了。次は情報出版の公開実装について利用者の再開指示待ち。SSHは必ず正確なコマンドを先に提示し、利用者確認後に実行する。

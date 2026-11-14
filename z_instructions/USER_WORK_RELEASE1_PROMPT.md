# 再開: 情報出版ユーザー向け仕事・案件

`AGENTS.md` → `JOB_EVALUATION_CONTROL1.md` → `USER_WORK_RELEASE_PLAN1.md` → `USER_WORK_RELEASE_AUDIT1.md` → `USER_WORK_RELEASE_MANAGER1.md` の順に読む。隔離ブランチ `feature/info-user-work-rollout-20261114` の起点は `f3405ea2c`。共有 `/home/w229/SunBwork` はClaude作業とビルド資産があるため編集しない。

利用者は2026-11-14に全社一斉案を撤回し、情報出版の一般UserとUser画面兼用者だけへ戻した。同日の「では情報出版に進みましょう」で調査から再開し、Super Adminで確認済みのジョブ関連を引き継ぐ。製版・オンデマンドの6メニュー整理は本番反映と画面確認済み。サンエーは別工程。Super Admin試用とCoordinator分類入口の画面確認は済み。一般Userの権限・画面確認は未実施。評価点の正式運用と分類統合/削除はこの公開で決めない。

2026-11-14: 製版・オンデマンドの6メニュー整理は本番 `dfb1362eb` へ反映し、利用者が両部署の6メニューとジョブ系ボタン非表示を画面確認済み。情報出版の調査は `USER_WORK_RELEASE_AUDIT1.md` にまとめた。共有値復元は `b7436fa02` で隔離ブランチへ保存。追加差分ではINFO本人の入力/BOX/旧式/依頼/校正を一般User・兼用者へ開く判定を試用分析から分け、分類の閲覧/検索/お気に入り/追加と管理操作を分離。関連PHP合成160件・1,507断言、JSテスト、ESLint、`/members` ビルド、PHP構文成功。追加ソースは同ブランチへ保存、本番未配備・一般User画面未確認。次は正確なコマンドの事前確認後に本番HEADと他作業を読み取り照合して限定配備手順を準備する。Super Adminの成功済み画面は再試験しない。

# 再開: 大まかな案件の試用

最初に `AGENTS.md`、`JOB_EVALUATION_CONTROL1.md`、`BROAD_PROJECT_PLAN1.md`、`BROAD_PROJECT_MANAGER1.md`、`JOB_INPUT1_PROMPT.md` を読む。ルート checkout の他者変更を守り、ブランチと差分を確認する。

利用者は「大まかな案件」という既存区分はないと訂正した。情報出版 Super Admin メニューへ登録を先に追加し、本人の簡易予定から選ぶ。名前必須、取引先・年度任意。既存 ProjectJob との紐づけ、ProjectJob 登録時選択、Coordinator メニューは皆で使う段階まで保留。勝手に先行実装・自動紐づけしない。

状態と次の一手は `BROAD_PROJECT_MANAGER1.md`。コードはさくら `db89a93bc` まで反映済みで、migration と単体テスト15件・51アサーションが成功。利用者の画面確認は未実施。次は管理画面の名前だけでの登録から順に確認する。さくら SSH は正確なコマンド提示と利用者確認が必須。本番 sales DB に触れない。

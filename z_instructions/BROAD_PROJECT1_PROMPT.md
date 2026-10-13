# 再開: 大まかな案件の試用

最初に `AGENTS.md`、`JOB_EVALUATION_CONTROL1.md`、`BROAD_PROJECT_PLAN1.md`、`BROAD_PROJECT_MANAGER1.md`、`JOB_INPUT1_PROMPT.md` を読む。ルート checkout の他者変更を守り、ブランチと差分を確認する。

利用者は「大まかな案件」という既存区分はないと訂正した。情報出版 Super Admin メニューへ登録を先に追加し、本人の簡易予定から選ぶ。名前必須、取引先・年度任意。既存 ProjectJob との紐づけ、ProjectJob 登録時選択、Coordinator メニューは皆で使う段階まで保留。勝手に先行実装・自動紐づけしない。

状態と次の一手は `BROAD_PROJECT_MANAGER1.md`。初期コードはさくら `db89a93bc` まで反映済みで、migration と単体テスト15件・51アサーションが成功。2026-10-14 に利用者が1件登録成功と取引先検索の動作を確認したが、空欄フォーカスで候補が開き、閉じられずボタンを覆う問題を指摘。候補を入力時だけ開き、閉じるボタン・外側クリック・Esc で閉じる修正 `f65bc911c` はさくらに反映済みで、ビルド・config/cache clear が成功。画面再確認は未実施。次は候補の閉じる操作、その後に任意項目の編集・簡易予定での選択を確認する。さくら SSH は正確なコマンド提示と利用者確認が必須。本番 sales DB に触れない。

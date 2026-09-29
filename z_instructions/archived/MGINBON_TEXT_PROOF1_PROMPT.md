# MGinbon 文字校正一覧 再開プロンプト1

`AGENTS.md`、`z_instructions/MGINBON_TEXT_PROOF_PLAN1.md`、`z_instructions/MGINBON_TEXT_PROOF_MANAGER1.md`、`z_instructions/MGINBON_PROJECT_ACTOR_HANDOFF2.md`を読んで再開する。

文字校正の初期実装とローカルmigration・自動検証まで完了し、利用者の画面確認待ち。文字校正は既存の校正工程を再構成する専用ビューであり、新工程ではない。ページ数は `mginbon_page_counts` に学校・試験回×ページ区分×教科単位で保存し、媒体の存在とは無関係に16セルを常時入力可能とする。問題・解答・傾対・解説のどこへ入れるかは利用者が判断し、自動初期値は設定しない。校正担当・日付はLISTと同じ既存データ・編集処理を使用する。三校・四校の発注日・納品日は不要。MyJobの数量との双方向連動は複数教科を逆配分できないため今回対象外。FileMaker取込対応は元データが揃ってから行う。

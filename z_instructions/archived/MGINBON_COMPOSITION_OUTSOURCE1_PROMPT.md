# MGinbon 組版外注・初校発注書 再開プロンプト1

`AGENTS.md`、`z_instructions/MGINBON_HANDOFF3.md`、本ファイル、`MGINBON_COMPOSITION_OUTSOURCE_PLAN1.md`、`MGINBON_COMPOSITION_OUTSOURCE_MANAGER1.md` を読み、組版外注2画面の作業を続ける。

利用者提供のFileMaker画像を基準に、「組版外注」は4教科の組版工程一覧、「組版外注_初校 御中」は初校組のA4縦発注書として実装する。独立DB項目は増やさず、工程担当と作業パッケージの `assigned_at` / `completed_at`、既存milestoneを組み合わせる。初校発注書は印刷時にもコード・分類・学校名・媒体など左側識別情報を含める。Sakura本番配備は対象外。

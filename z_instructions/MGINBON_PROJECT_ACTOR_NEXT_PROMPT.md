# 次のCodexへの再開プロンプト

`AGENTS.md`、`z_instructions/MGINBON_PROJECT_ACTOR_HANDOFF2.md`、同ファイル記載の計画・管理ファイルを最初に全文読む。

最優先で「年度準備」画面を実装する。年度対象校だけがあり媒体0件の状態をLISTと呼ばず、対象校取込・ProjectJob接続・媒体取込の準備状況と次操作を表示する。上部を「年度準備」と「制作進行」に分け、媒体未取込中はLIST・入稿チェック・出稿表などへ進めないようにする。媒体取込後は制作進行LISTを既定表示にする。新規ページ／コンポーネント前に `z_instructions/CONSOLIDATED_01_layout_and_ui.md` を読む。

その後、ProjectJobを唯一の所属元とするPhase C/Dへ進む。担当者系5値一覧を案件所属ユーザー／外注先へリンクし、LIST・詳細・出稿表・JobBox保存側の候補判定を統一する。既存割当・履歴・実績を削除せず、任意文字列を正式なJobBox担当へ昇格させない。複数DBの部分失敗を考慮し、各段階でFeature test、PHP構文、`npm run build`、`git diff --check`を行う。Sakura本番には利用者の明示確認なしで反映しない。

# 次のCodexへの再開プロンプト

`AGENTS.md` と `z_instructions/MGINBON_PROJECT_ACTOR_HANDOFF1.md` を最初に全文読む。続いて同ファイル記載の計画・管理ファイルを読む。

銀本とProjectJobの担当者連動をPhase Bから再開する。ProjectJobを唯一の所属元とし、年度取込時の案件接続必須化、未接続年度ガード、利用開始後の接続変更禁止を先に実装する。その後Phase C/Dへ進む。既存の割当・履歴・実績を削除せず、任意文字列を正式なJobBox担当へ昇格させない。複数DBをまたぐ処理は部分失敗を考慮する。各段階でFeature test、PHP構文、`npm run build`、`git diff --check`を行う。Sakura本番には利用者の明示確認なしで反映しない。

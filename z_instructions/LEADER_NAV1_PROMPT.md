# SunBrain Leaderタブ整理 再開メモ

2026-11-12。`AGENTS.md` と `JOB_EVALUATION_CONTROL1.md`、本件の `LEADER_NAV_PLAN1.md` / `LEADER_NAV_MANAGER1.md` を読む。保存基準は本番確認済み `aa5a28f41`、作業ブランチ `feature/leader-navigation-review-20261112`。共有 `/home/w229/SunBwork` の別作業を保護する。

SunBrain Leaderの16入口をAdminと同様に整理し、SunA/一般会社の旧ナビをそのまま保持。既存URL・権限・DBは変更しない。Adminタブは利用者の「成功」で3条件確認済みなので再確認しない。一般ユーザー向け新マイジョブの公開は未承認。

コード検証: 一般会社の旧部品は元SHA256と一致。16入口のZiggy名、対象Lintエラー0、`/members` build、manifest参照欠落0を確認。実ブラウザ画面確認ではない。本番read-only SSHで `aa5a28f41`/main/追跡変更なし・既知未追跡5件を確認。

本番状態: 利用者が固定SSH手順を再承認し、最初のawk失敗はmerge前で停止、修正版で本番 `aa5a28f41` から `a86dafa9f` へff-only。manifest494/参照525/欠落0、`/members` baseと追跡変更なしを確認。一般ユーザー公開は行っていない。

次の一手: `LEADER_NAV_MANAGER1.md` に今回依頼したLeader上段・4分類とお知らせ・日報/勤務時間見出しの利用者画面結果を記録する。成功済みAdmin画面は再確認しない。

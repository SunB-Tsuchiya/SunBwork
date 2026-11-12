# SunBrain Leaderタブ整理 再開メモ

2026-11-12。`AGENTS.md` と `JOB_EVALUATION_CONTROL1.md`、本件の `LEADER_NAV_PLAN1.md` / `LEADER_NAV_MANAGER1.md` を読む。保存基準は本番確認済み `aa5a28f41`、作業ブランチ `feature/leader-navigation-review-20261112`。共有 `/home/w229/SunBwork` の別作業を保護する。

SunBrain Leaderの16入口をAdminと同様に整理し、SunA/一般会社の旧ナビをそのまま保持。既存URL・権限・DBは変更しない。Adminタブは利用者の「成功」で3条件確認済みなので再確認しない。一般ユーザー向け新マイジョブの公開は未承認。

コード検証: 一般会社の旧部品は元SHA256と一致。16入口のZiggy名、対象Lintエラー0、`/members` build、manifest参照欠落0を確認。実ブラウザ画面確認ではない。本番read-only SSHで `aa5a28f41`/main/追跡変更なし・既知未追跡5件を確認。

次の一手: `LEADER_NAV_MANAGER1.md` の保存状況を確認し、固定差分を検査する。本番反映には `DEPLOY_SAKURA.md` を読み、正確なSSHコマンドを提示して確認を得る。

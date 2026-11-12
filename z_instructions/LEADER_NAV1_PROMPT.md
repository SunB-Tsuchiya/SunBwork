# SunBrain Leaderタブ整理 再開メモ

2026-11-12。`AGENTS.md` と `JOB_EVALUATION_CONTROL1.md`、本件の `LEADER_NAV_PLAN1.md` / `LEADER_NAV_MANAGER1.md` を読む。保存基準は本番確認済み `aa5a28f41`、作業ブランチ `feature/leader-navigation-review-20261112`。共有 `/home/w229/SunBwork` の別作業を保護する。

SunBrain Leaderの16入口をAdminと同様に整理し、SunA/一般会社の旧ナビをそのまま保持。既存URL・権限・DBは変更しない。Adminタブは利用者の「成功」で3条件確認済みなので再確認しない。一般ユーザー向け新マイジョブの公開は未承認。

コード検証: 一般会社の旧部品は元SHA256と一致。16入口のZiggy名、対象Lintエラー0、`/members` build、manifest参照欠落0を確認。実ブラウザ画面確認ではない。本番read-only SSHで `aa5a28f41`/main/追跡変更なし・既知未追跡5件を確認。

本番状態: 利用者が固定SSH手順を再承認し、最初のawk失敗はmerge前で停止、修正版で本番 `aa5a28f41` から `a86dafa9f` へff-only。manifest494/参照525/欠落0、`/members` baseと追跡変更なしを確認。一般ユーザー公開は行っていない。

利用者画面: 「成功」により、今回依頼したLeader上段順と日報遷移、4分類とお知らせの遷移、日報/勤務時間見出しの3点が確認済み。SunA・全権限組合せ・モバイル実画面へは拡張しない。

次の一手: 本件の画面確認を繰り返さない。変更履歴コードの保存と本番表示の扱いは `LEADER_NAV_MANAGER1.md` を確認し、週末の一般ユーザー公開準備またはSunA整理は別の利用者判断で進める。

変更履歴コード `sunbrain-leader-navigation-1` は本番未配備・未seed。共通Seederを丸ごと実行すると他のSeederも動くので、通常の一括seedはしない。

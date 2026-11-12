# SunBrain Adminタブ整理 管理

更新: 2026-11-12。設計 `ADMIN_NAV_PLAN1.md`、再開 `ADMIN_NAV1_PROMPT.md`。横断決定は `JOB_EVALUATION_CONTROL1.md`。

| 段階 | 状態 | 根拠・次の一手 |
| --- | --- | --- |
| 利用者判断 | 確認済み | SunBrainのみ。SunAは後日。権限管理の名称、4分類、日報をダッシュボード直後、勤務時間/ポートフォリオ閲覧への名称変更に同意。 |
| コード実装 | 完了・ローカル | 既存AdminナビをGeneral用へ原文のまま分離し、SunBrain用の上段6/下段19項目へ整理。共有の日報/勤務時間見出しはSunBrain Adminだけ改称。PortfolioはSB専用ページで改称。URL/ルート/権限/DB変更なし。 |
| コード検証 | 完了・ローカル基準 | General用部品は本番基準a975の元ファイルとSHA256一致。SunBrainは21項目・重複なし・最初がdashboard/diariesを静的照合。対象ESLintエラー0、既存の未使用変数警告5。ローカル用buildと `/members` build成功、manifest494件/参照525件/欠落0、app資産内の `/members/build/` を確認。実ブラウザ画面確認ではない。 |
| 本番配備 | 完了 | 利用者が固定push/SSHを承認。Sakura本番a975c63e5/main/追跡変更なし/既知未追跡5件/manifest照合後、許可16ソース資料・新資産467件＋manifest1件・削除0の差分を検査し、専用ブランチ `aa5a28f41ae74b757ffde45abfbe8b3f0a33a857` へff-only。manifest494件/参照548件/欠落0、/members base、最終追跡変更なし・既知未追跡保持。ルート/認可/DB/seed変更なし。 |
| 利用者画面確認 | 完了 | 利用者の「成功」は直前に依頼した3点を全確認。(1) SunBrain Admin上段の順と日報閲覧の遷移、(2) 4分類の下段と該当ページへの遷移、(3) 勤務時間閲覧とポートフォリオ閲覧の見出し。SunA/Leaderの実画面は今回依頼していない。 |
| 一般ユーザー公開 | 未承認 | 本件のAdmin整理とは独立。 |

変更履歴: `ChangelogSeeder` に `sunbrain-admin-navigation-1` を追加。共通Seederは他の複数エントリも実行するため、今回の本番配備後には実行していない。変更履歴の本番登録は別途差分確認と明示した実行時期の判断が必要。Adminナビ本体の確認済み状態は変えない。

2026-11-12 利用者追加: 最後に開いたURLをlocalStorageが保存するので、URL変更時のエラー復帰を要望。今回はURLを変えず、既存 `app.js` は404時に `lastTab_admin` を消してAdmin dashboardへ戻すと確認。403は既存の権限エラー表示のまま。URL移行を行う後工程では再検討する。

次の一手: 今回のAdmin整理は区切りとし、次にLeaderのタブを同じように調査・設計するか、週末の一般ユーザー公開準備へ戻るかを利用者と相談する。サンエーのタブ整理は別工程。一般ユーザー公開は未承認。

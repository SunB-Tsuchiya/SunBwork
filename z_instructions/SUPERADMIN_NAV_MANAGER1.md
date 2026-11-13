# SunBrain Super Adminタブ整理 管理

更新: 2026-11-13。設計 `SUPERADMIN_NAV_PLAN1.md`、再開 `SUPERADMIN_NAV1_PROMPT.md`、横断決定 `JOB_EVALUATION_CONTROL1.md`。

| 段階 | 状態 | 根拠・次の一手 |
| --- | --- | --- |
| 利用者判断 | 確認済み | Admin/Leaderと同形式、SunBrain先行。現行にない日報閲覧は追加しない。Super Admin固有の現行入口と表示条件を維持する。 |
| 実装 | 完了・ローカル | 会社別分離、SunBrain用の親子タブ。日報の新規入口は追加せず、18ルート・試用/分析の条件と別タブを維持。 |
| コード検証 | 完了・ローカル基準 | 一般会社用部品と元部品SHA256一致。旧18/新18入口のルート名一致、Ziggyの18名、対象ESLintエラー0。`/members` build成功、manifest494件/参照525件/欠落0、旧資産削除0・新資産467件。実ブラウザ確認ではない。 |
| 本番配備 | 完了 | 隔離ブランチ `719f0b575` をoriginへpush。利用者が固定SSH手順 SHA256 `d8155667e838830a9c47fce8c55ca03233765f7c18a720bf9db2077f0844d326` を承認。元 `a86dafa9f`/main/追跡変更なし・既知未追跡5件と許可10ソース資料/新資産467＋manifest1/削除0を検査後ff-only。config/cache/view clear、manifest494/参照525/欠落0・`/members` base、最終追跡変更なしを確認。DB/ルート/認可/一般公開変更なし。 |
| 利用者画面確認 | 未実施 | 配備後にSuper Adminの上段・子タブ・試用/別タブ入口だけを確認。Admin/Leaderの成功済み試験は繰り返さない。 |
| 一般ユーザー公開 | 未承認 | 別工程。 |

次の一手: 利用者に依頼したSuper Admin上段の順・4分類の下段遷移・試用入口と作業量分析の別タブの結果を記録する。既確認のAdmin/Leader画面は繰り返さない。

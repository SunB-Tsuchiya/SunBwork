# 作業量分析の管理画面移設 再開用 1

更新: 2026-10-30 JST。AGENTS.md → JOB_EVALUATION_CONTROL1.md → JOB_EVALUATION_ROADMAP1.md → WORKLOAD_MANAGEMENT_MOVE_PLAN1.md → MANAGER1を読む。

**現在: さくら40ad992c3へ管理画面移設を限定配備成功。次はSuperAdminの実画面確認。その後Admin/Leaderへ。**

利用者は実装計画とデプロイを承認済み。読取で本番が4b51f8049/main/変更なしと判明。別作業のリッチテキスト等が既配備だったので、元feature155413e46を直接反映せず、4bを起点としたdeploy/workload-management-20261030を作成し今回だけ適用・再検証・再ビルド。別作業28ソースは完全保持、共通Seederは今回呼出1行のみ追加。69件286断言、PHP7構文/ESLintエラー0/ビルド成功、manifest490欠落0。

承認済み版固定SSHでPHP7構文→4b51f8049から40ad992c3への早送り→独立WorkloadManagementMoveChangelogSeeder --force→config/cache/route/view成功。migrationや共通Seeder全件は実行しない。一般社員公開/月別コメントは実装していない。

配備用作業場所: /tmp/sunbwork-workload-management-deploy-20261030、deploy/workload-management-20261030。元実装/検証用: /tmp/sunbwork-evaluation-c7-20261030、feature/workload-management-move-20261030（実装f5b93c8a7、資料155413e46）。共有メイン6af669c89は別作業でdirty、変更/リセットしない。一時ツリーが消えていたら最新の保存ブランチ/資料を照合する。

管理閲覧はWorkloadAnalysisAccess、既存入力guardと採点サービス/係数は不変。Admin自社/Leader所属ユニット/SuperAdmin会社コンテキストの範囲。各roleのresults/details/analysis/person_schedule/guide/archiveを使用し、AppLayout黄/赤/橙・dashboard戻り。会社/部署切替もURLで制限。独立試用メニューのみ撤去し既存データと保護APIは保持。旧アーカイブと個人リンク認可も保持。

次の利用者画面確認はSuperAdminで①新分析が/superadmin/workload-analyzer配下で開く、②黄の通常ロールメニュー/レイアウト、③作業記録の試用が分析/通常メニューにない、④戻るとSuperAdminのダッシュボード。すべて成功なら成功、部分なら項目と結果をもらう。Admin/Leaderの実画面はその後であり未確認。ビルド/配備/画面を混同しない。追加SSHが必要ならAGENTS.mdに従い正確なコマンド承認を得る。

2026-10-30 管理移設の限定配備成功: 利用者が専用40ad992c3の正確なSSHコマンドを承認。4b51f8049/main/追跡対象変更なし/取得40ad/migration差分なしの全条件を通過。取得したPHP7ファイルの実構文確認成功後、4b51f8049→40ad992c3を早送り。独立WorkloadManagementMoveChangelogSeederだけを--forceで実行成功、config/cache/route/viewクリア成功、最終HEAD40ad992c3。別作業の既配備28ソースは保持、mainの未配備作業は取り込まず、migration/共通Seeder全件は実行しない。次はSuperAdmin画面で①/superadmin/workload-analyzer配下の新分析、②黄の通常メニュー/レイアウト、③試用項目がない、④ダッシュボードへの戻り先もSuperAdmin、を確認。Admin/Leaderの実画面はまだ未確認。配備成功を画面成功に拡張しない。

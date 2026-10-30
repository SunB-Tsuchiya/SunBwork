# 作業量分析の管理画面移設 完了引継ぎ 1

2026-10-30 JST。**この移設は完了済み。次の画面確認待ちとして再開しない。** 現在の全体方針はrootのJOB_EVALUATION_CONTROL1.md / JOB_EVALUATION_ROADMAP1.md / EVALUATION_SCORE_MANAGER1.mdを読む。

実装元はfeature/workload-management-move-20261030（実装f5b93c8a7、資料155413e46）。さくらに別作業4b51f8049が既配備だったため、同版起点のdeploy/workload-management-20261030で今回だけ重ねて再検証/再ビルドした。69件286断言、PHP7構文/ESLintエラー0、本番ビルド/manifest490参照欠落0成功。別作業28ソースは完全保持。

利用者承認の版固定SSHで4b51f8049→40ad992c3早送り、独立WorkloadManagementMoveChangelogSeeder --force、config/cache/route/view成功。migrationと共通Seeder全件は実行しない。

SuperAdmin/Admin/Leaderそれぞれの依頼4点は利用者「成功」で全確認。role prefix/黄赤橙の通常レイアウト/試用項目なし/dashboard戻り。Adminは自社内会社部署候補と簡易ガイド遷移、Leaderは所属ユニット内の対象者と簡易ガイド遷移も確認。未依頼の実機権限OFF/改変URL/全レスポンシブ等の網羅成功とはしない。

管理閲覧はWorkloadAnalysisAccessに分離。既存入力guardと採点式/係数は不変。独立試用はメニューだけ撤去しデータと保護APIは保持。一般社員向け限定公開と月別コメントは未実装、別工程。全体の正式完成や人事査定への適用は別判断。

完了3資料の本体はarchived配下。旧root名の案内は履歴参照/変更履歴design_filesを維持するためで、進行中の計画ではない。

最後の作業場所: /tmp/sunbwork-workload-management-deploy-20261030、deploy/workload-management-20261030。共有メイン6af669c89の別作業は触らない。一時ツリーが消えていたら最新コミット/ブランチと資料を照合する。追加SSHは正確なコマンドの事前承認が必要。

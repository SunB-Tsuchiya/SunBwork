# 作業量分析の管理画面移設 再開用 1

2026-10-30 JST。AGENTS.md → JOB_EVALUATION_CONTROL1.md → JOB_EVALUATION_ROADMAP1.md → WORKLOAD_MANAGEMENT_MOVE_PLAN1.md → MANAGER1を読む。

利用者は計画を「hai」で承認。3ロール管理画面への移設はコード実装・非破壊検証・本番用ビルドまで完了。**利用者がデプロイを依頼。現在版4b51f8049に他作業が既配備のため、その版に今回だけ重ねて再検証した。更新SSHはまだ実行せず、正確なコマンド承認待ち。**

専用ブランチ: feature/workload-management-move-20261030。作業ツリー: /tmp/sunbwork-evaluation-c7-20261030。最後に確認した既存さくら版f5a327635。共有メイン6af669c89は別作業でdirty、触らない。origin/mainは調査時e94584ee3。最新の保存コミットを確認して再開し、一時ツリーの存在を仮定しない。

WorkloadAnalysisAccessでAdmin自社/Leader所属ユニット/SuperAdmin会社コンテキストをGET専用に解決。DepartmentWorkControllerはGETのreadContextだけ分離し、管理用Controllerで差替え。入力側のavailableや点数計算は変更なし。各roleにresults/details/analysis/person_schedule/guide/archive。index/category入口は同prefixへ。AppLayout(fluid)、黄/赤/橙、dashboard戻り、許可された会社/部署候補、日付/方式リンク保持。旧schedule分析ページはSuperAdmin互換の管理用入口へ。一般社員は入れない。

試用タブとSuperAdminメニューの「作業記録の試用」は撤去したが、既存記録と保護されたAPIは残す。旧アーカイブは保持し月切替とrole prefixを修正、旧個人直リンクも認可する。SuperAdminの会社切替は分析画面だけ旧company_id/department_idを解除し日付/期間/方式を保持。

検証: SQLiteメモリ69件286断言成功。実Laravelルート照合、Ziggy再生成、role/方式/リンク/スコープ切替のJS直接実行、PHP対象構文・ESLintエラー0、本番用Viteとmanifest欠落0成功。旧Show.vueに既存unused警告1件。ブラウザ画面/本番/実アカウントの確認は未実施。詳細な実行環境と証拠はMANAGERを参照。

変更履歴はWorkloadManagementMoveChangelogSeederのみ実行する専用手順を用意する。migrationなし。配備許可後も正確なSSHコマンド提示/承認を得て、現在版・変更・別作業を確認して限定反映する。一般社員公開・月別コメントは今回実装しない。

2026-10-30 保存完了: 管理移設の実装コミットf5b93c8a7をfeature/workload-management-move-20261030へpush成功。追跡対象の作業ツリー変更なし。配備済み既存f5a327635との差分は今回の管理移設ソース/テスト/資料/公開ビルドのみで、採点サービス・入力試用判定・係数の差分なし。新たなSSH/さくらseed/キャッシュ操作は実行していない。デプロイ許可待ち。

2026-10-30 デプロイ準備の現在版照合: 利用者がデプロイを依頼し、別作業を巻き込まないよう指定。承認済み読取SSHでさくら4b51f8049/main/追跡対象変更なしを確認。前回f5a327635以降、別作業のリマインダー/リッチテキスト/manifest版管理等が既配備。元feature155413e46を直接早送りせず、4b51f8049を起点にdeploy/workload-management-20261030（/tmp/sunbwork-workload-management-deploy-20261030）を作り、今回のソース/資料だけ適用、Ziggyと/membersビルドを再生成。別作業の非重複28ソースは現在本番版と完全一致。共通ChangelogSeederは既存内容を保持し今回呼出1行だけ追加。移設以外の新しいmain作業は取得しない。合成69件286断言/対象ESLintエラー0/対象PHP7構文/ビルド成功、manifest参照490欠落0。migration差分なし、独立WorkloadManagementMoveChangelogSeederだけを配備時実行する。まだ更新SSHは実行していない。次は専用候補を保存して、現版4b/main/変更なしと取得版一致の条件付きSSHを正確に提示し承認を得る。

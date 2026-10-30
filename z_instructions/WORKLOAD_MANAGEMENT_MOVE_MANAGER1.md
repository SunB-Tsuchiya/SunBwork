# 作業量分析の管理画面移設 進捗管理 1

更新: 2026-10-30 JST。計画 `WORKLOAD_MANAGEMENT_MOVE_PLAN1.md`、再開 `WORKLOAD_MANAGEMENT_MOVE1_PROMPT.md`。

| 工程 | 状態 | 証拠/残る確認 |
| --- | --- | --- |
| 計画 | 利用者確認済み | 利用者「hai」で既存閲覧範囲を維持した管理画面移設を承認。デプロイは別途許可 |
| コード実装 | 完了 | WorkloadAnalysisAccess/管理用Controller、3prefixのGETルート、AppLayoutと黄/赤/橙、dashboard戻り、会社/部署選択、同prefixのAPI/画面リンク。独立試用メニュー撤去、旧アーカイブと記録は保持 |
| 認可・計算検証 | 成功 | SQLiteメモリのみ、69件286断言。権限OFF・一般/Coordinator/Clerk/校正Coordinator・guest・Admin別会社・Leaderユニット外・旧個人リンク拒否、管理用API個人指定と旧V3の実計算一致。入力試用判定は変更なし |
| ルート/JS | 成功 | 3role×6静的パスの実Laravel照合、Ziggy再生成。3role×2方式×6リンクとスコープ変更・色のロジックを直接実行。試用メニュー撤去、SuperAdmin会社コンテキスト変更時に旧scopeだけ解除し日付/期間/方式を保持 |
| 構文/ビルド | 成功 | PHP対象9ファイル構文、対象ESLintエラー0。旧Show.vueの既存unused変数warning1件は変更せず記録。本番用/members Vite成功、manifest参照487ファイル欠落0。点数計算サービス/入力guardの差分なし |
| GitHub保存 | 専用ブランチへ保存 | `feature/workload-management-move-20261030`。本番既存f5a327635起点の差分をレビュー可能にする。mainと共有作業ツリーには反映しない |
| 変更履歴 | 独立Seederの登録成功 | WorkloadManagementMoveChangelogSeeder、version workload-management-20261030（30文字以内）。共通Seederからも呼出。migrationなし |
| さくら反映 | **40ad992c3へ限定配備成功** | 別作業が既配備の4b51f8049を起点に専用版を作成。承認済み条件付きSSHでPHP7構文→早送り→独立変更履歴→config/cache/route/view成功。migrationなし |
| 利用者画面確認 | **SuperAdminの依頼4点成功。Admin/Leaderは未確認** | superadmin prefix/黄の通常レイアウト/試用項目撤去/dashboard戻りを利用者確認。Admin/Leaderの実権限・各画面遷移等は次の確認 |

**再開する1手:** SuperAdminの依頼4点は利用者確認済み。次は実際のAdminアカウントで新分析のadmin prefix/赤の通常レイアウト/自社内の候補/簡易・ガイドのリンク保持/試用項目なし/dashboard戻りを画面確認する。SuperAdminによるadmin URL閲覧をAdminの自社制限確認と混同しない。Leaderはその後。

## 設計上の境界

- 閲覧権限を入力権限から分離。PersonalWorkSchedule.availableと採点サービス・係数は変更しない。
- Adminは自社、Leaderは既存所属ユニットのメンバー。SuperAdminは会社コンテキスト内、未選択時は許可された会社から選択する。選択部署単位で集計する。
- 複数部署/会社の同時一括合算は追加しない。各部署へ切替可能。対象範囲を画面表示する。
- 旧方式アーカイブは各prefixに保持。月変更でもarchiveを維持し、旧個人直リンクにも同じ認可を適用。
- 一般社員向けの限定公開と月別コメントは実装しない。独立試用はメニュー撤去だけで、記録/保護された保存APIは削除しない。
- SuperAdminが別role画面を開く場合も、URLのprefixに合った色/戻り先を使用する。

## 環境・再現

隔離ツリー `/tmp/sunbwork-evaluation-c7-20261030`。共有メイン6af669c89の別作業は変更していない。調査時origin/main=e94584ee3、計画保存0c325c65eから今回を実装。

PHP CLIなし/Docker停止を受け、公式Ubuntuパッケージを/tmpへ展開してPHP8.3を実行。システムへインストールせず、実DBは使わない。

検証用CLI: `/tmp/sunbwork-php-validation-20261030/bin/php`。PHPUnit: `--no-configuration --do-not-cache-result --bootstrap /tmp/sunbwork-management-test-bootstrap-20261030.php`。対象: WorkloadAnalysisAccessTest/WorkloadTrialOverlapTest/WorkloadTrialTimeBoundaryTest/WorkloadTrialRangeTest/PersonalWorkPointsTest/PersonalWorkScheduleTest。

Laravelルート/ZiggyはAPP_ENV=testing、DB_CONNECTION=sqlite、DB_DATABASE=:memory:、CACHE_STORE=array、SESSION_DRIVER=arrayで実行。共有.envは変更していない。本番ビルドは隔離ツリーでVITE_APP_BASE_PATH=/membersをコマンドに限定指定。PHPの一時実行環境や作業ツリーが消えたら存在を仮定せず再構築する。

2026-10-30 最終整形確認: 新規PHP4ファイルのPint検査成功。整形後も対象69件286断言が成功。既存の大きな旧コントローラ等は無関係な全体整形をしない。

2026-10-30 保存完了: 管理移設の実装コミットf5b93c8a7をfeature/workload-management-move-20261030へpush成功。追跡対象の作業ツリー変更なし。配備済み既存f5a327635との差分は今回の管理移設ソース/テスト/資料/公開ビルドのみで、採点サービス・入力試用判定・係数の差分なし。新たなSSH/さくらseed/キャッシュ操作は実行していない。デプロイ許可待ち。

2026-10-30 デプロイ準備の現在版照合: 利用者がデプロイを依頼し、別作業を巻き込まないよう指定。承認済み読取SSHでさくら4b51f8049/main/追跡対象変更なしを確認。前回f5a327635以降、別作業のリマインダー/リッチテキスト/manifest版管理等が既配備。元feature155413e46を直接早送りせず、4b51f8049を起点にdeploy/workload-management-20261030（/tmp/sunbwork-workload-management-deploy-20261030）を作り、今回のソース/資料だけ適用、Ziggyと/membersビルドを再生成。別作業の非重複28ソースは現在本番版と完全一致。共通ChangelogSeederは既存内容を保持し今回呼出1行だけ追加。移設以外の新しいmain作業は取得しない。合成69件286断言/対象ESLintエラー0/対象PHP7構文/ビルド成功、manifest参照490欠落0。migration差分なし、独立WorkloadManagementMoveChangelogSeederだけを配備時実行する。まだ更新SSHは実行していない。次は専用候補を保存して、現版4b/main/変更なしと取得版一致の条件付きSSHを正確に提示し承認を得る。

2026-10-30 管理移設の限定配備成功: 利用者が専用40ad992c3の正確なSSHコマンドを承認。4b51f8049/main/追跡対象変更なし/取得40ad/migration差分なしの全条件を通過。取得したPHP7ファイルの実構文確認成功後、4b51f8049→40ad992c3を早送り。独立WorkloadManagementMoveChangelogSeederだけを--forceで実行成功、config/cache/route/viewクリア成功、最終HEAD40ad992c3。別作業の既配備28ソースは保持、mainの未配備作業は取り込まず、migration/共通Seeder全件は実行しない。次はSuperAdmin画面で①/superadmin/workload-analyzer配下の新分析、②黄の通常メニュー/レイアウト、③試用項目がない、④ダッシュボードへの戻り先もSuperAdmin、を確認。Admin/Leaderの実画面はまだ未確認。配備成功を画面成功に拡張しない。

2026-10-30 SuperAdmin画面4点成功: 利用者「成功」で、新分析がsuperadmin/workload-analyzer配下で開く、黄の通常メニュー/レイアウト、分析タブと現在表示中の通常メニューに独立作業記録試用がない、戻るとSuperAdmin dashboard、を全確認。モバイル等未依頼の画面やAdmin/Leaderの権限確認へ拡張しない。次は実際のAdminアカウントで/admin prefix・赤の通常レイアウト・自社内の会社部署候補・簡易/ガイド間prefix保持・試用項目なし・dashboard戻りを段階確認する。SuperAdminがAdmin URLを開く場合はSuperAdmin権限のままなので、Adminの自社制限の画面確認として代用しない。Adminが確認できない場合は未確認として保持し、アカウントの資格情報を求めない。

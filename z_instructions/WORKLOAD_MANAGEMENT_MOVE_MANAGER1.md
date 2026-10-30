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
| 変更履歴 | コード準備済み・登録未実行 | WorkloadManagementMoveChangelogSeeder、version workload-management-20261030（30文字以内）。共通Seederからも呼出。migrationなし |
| さくら反映 | **未実施・別途許可が必要** | 最後に確認した既存版f5a327635。本件のSSH/デプロイ/seed/cache操作はしていない |
| 利用者画面確認 | **未実施** | 配備後に3roleのprefix/レイアウト/色/戻り先/権限/会社部署選択/簡易詳細ガイド/アーカイブ/試用項目撤去を確認する |

**再開する1手:** 完成した専用版の差分と検証結果を利用者へ報告。デプロイは別途許可があるまで実行しない。許可後も現在版/追跡変更/他作業との差分を確認し、正確なSSHコマンドを提示してから限定反映する。

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

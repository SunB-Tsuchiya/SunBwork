# 作業量分析の管理画面移設 再開用 1

2026-10-30 JST。AGENTS.md → JOB_EVALUATION_CONTROL1.md → JOB_EVALUATION_ROADMAP1.md → WORKLOAD_MANAGEMENT_MOVE_PLAN1.md → MANAGER1を読む。

利用者は計画を「hai」で承認。3ロール管理画面への移設はコード実装・非破壊検証・本番用ビルドまで完了。**デプロイは利用者の別途許可前なので未実施。次は成果物の報告と許可待ち。**

専用ブランチ: feature/workload-management-move-20261030。作業ツリー: /tmp/sunbwork-evaluation-c7-20261030。最後に確認した既存さくら版f5a327635。共有メイン6af669c89は別作業でdirty、触らない。origin/mainは調査時e94584ee3。最新の保存コミットを確認して再開し、一時ツリーの存在を仮定しない。

WorkloadAnalysisAccessでAdmin自社/Leader所属ユニット/SuperAdmin会社コンテキストをGET専用に解決。DepartmentWorkControllerはGETのreadContextだけ分離し、管理用Controllerで差替え。入力側のavailableや点数計算は変更なし。各roleにresults/details/analysis/person_schedule/guide/archive。index/category入口は同prefixへ。AppLayout(fluid)、黄/赤/橙、dashboard戻り、許可された会社/部署候補、日付/方式リンク保持。旧schedule分析ページはSuperAdmin互換の管理用入口へ。一般社員は入れない。

試用タブとSuperAdminメニューの「作業記録の試用」は撤去したが、既存記録と保護されたAPIは残す。旧アーカイブは保持し月切替とrole prefixを修正、旧個人直リンクも認可する。SuperAdminの会社切替は分析画面だけ旧company_id/department_idを解除し日付/期間/方式を保持。

検証: SQLiteメモリ69件286断言成功。実Laravelルート照合、Ziggy再生成、role/方式/リンク/スコープ切替のJS直接実行、PHP対象構文・ESLintエラー0、本番用Viteとmanifest欠落0成功。旧Show.vueに既存unused警告1件。ブラウザ画面/本番/実アカウントの確認は未実施。詳細な実行環境と証拠はMANAGERを参照。

変更履歴はWorkloadManagementMoveChangelogSeederのみ実行する専用手順を用意する。migrationなし。配備許可後も正確なSSHコマンド提示/承認を得て、現在版・変更・別作業を確認して限定反映する。一般社員公開・月別コメントは今回実装しない。

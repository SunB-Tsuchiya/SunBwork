# 情報出版の表示対象管理 再開

AGENTS.md、JOB_EVALUATION_CONTROL1、DEPARTMENT_DISPLAY_PLAN1/MANAGER1を読む。branch feature/info-department-visibility-20261116、/tmp/sunbwork-info-department-visibility-20261116、基準c5e6d7d97。共有mainには別作業がある。
利用者はAdminから情報出版の人を部署予定/みんなの記録に載せる/載せないと確認済み。ページ閲覧可否ではない。admin/applicants/transportを参考。独立2フラグ/未設定表示/本人入力とmine保持/管理分析変更なしの具体設計を作成。まだアプリ未変更。
次: 設計確認後、専用テーブル/Service/認可/対象クエリ/UIを実装して合成検証。SSHは正確なコマンドを先に提示して承認を得る。既存画面確認を繰り返さない。

2026-11-16利用者が具体設計を承認。専用設定Model/Service/migrationとAdmin画面・ルート・権限タブ、部署予定/記録のサーバ絞込みを実装。予定/記録は独立、未設定は表示。本人mineは一覧から除外されても別計算で保持。まとめ保存は対象外ID混入を全拒否し、同時編集を版照合で409拒否。今回の合成検証/配備/画面確認は未実施。次は認可・独立フラグ・本人案内・競合テスト。

2026-11-16 実装・コード検証完了、未配備: 合成Integration6件49断言、既存関連Unit32件265断言（既存mixed-series fixtureのsupersedes_assignment_id警告1件、失敗なし）、実Vueの独立切替/全件保存/CSRF/二重送信/409入力保持/未保存離脱確認、本番用build成功。manifest495件・参照526件・欠落0・/members。承認済み読み取りSSHで本番9c187136c577b515dbc92660e5b7b08af10cca46/main、追跡変更なし、既知未追跡6件を確認。Docker再起動後、分離コンテナのSQLite :memory:だけで検証し本番業務データは未参照。次は固定差分と今回専用migrationのみの配備手順を提示して承認を得る。画面確認は未実施。過去成功済み画面試験は反復しない。

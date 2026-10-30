# 作業量分析の管理画面移設 再開用 1

2026-10-30 JST。AGENTS.md → JOB_EVALUATION_CONTROL1.md → JOB_EVALUATION_ROADMAP1.md → WORKLOAD_MANAGEMENT_MOVE_PLAN1.md → MANAGER1を読む。

利用者依頼: SuperAdminの現行V3反映版をAdmin/Leaderの「作業量分析」へ置換。3ロールのprefix・色・レイアウト・戻り先を合わせる。「作業記録の試用」は不要。一般社員向け公開は別工程。**デプロイは別途許可があるまで実行しない。**

現在は調査/計画作成まででコード未変更。AGENTS.mdの大規模作業確認待ち。Adminは既存の自社範囲、Leaderは既存ユニットメンバー範囲を維持し、権限OFF/別会社/対象外ユーザーをAPIでも拒否する。既存PersonalWorkSchedule.availableを緩めると入力権限まで広がるので禁止。V3既定/V2比較/点数式/記録/非公開保護は維持。

AppLayout(fluid)を使い、戻り先をrole dashboardへ。role別workload_analyzerの新読取ルート、動的な画面/APIリンク、必要な会社/部署選択を用意。旧予定表入口はSuperAdmin互換として扱い、一般公開しない。独立試用メニューを外すが既存データ/履歴は削除しない。旧方式アーカイブは比較用として保存。

最後の作業場所: /tmp/sunbwork-evaluation-c7-20261030、review/evaluation-c7-20261030。前回配備f5a327635。最新管理資料とブランチを照合してから進める。一時ツリーが消える場合がある。共有メイン6af669c89は別作業でdirty、変更/リセットしない。

PHP CLIなし/DockerのWSL統合停止が直近の実行環境。認可の合成検証とPHP構文を本番DBなしで実行できる環境を確認する。ESLint・ルート変更後のZiggy・/members本番ビルド・manifest確認を完了し、未検証は明記する。tests/実装/配備/画面確認を混同しない。月別コメントは構想のみ後工程。

次の1手: 計画の確認回答を得て、閲覧スコープ/APIから実装。デプロイの承認を先に求める段階ではない。

# WORKLOAD_REBUILD2_PROMPT — 再開用

SunBWork の情報出版向け作業量分析を、予定表の簡易順位とメニューの詳細分析へ分けて再構成する。まず `AGENTS.md`、`WORKLOAD_REBUILD_PLAN1.md`、`WORKLOAD_REBUILD_PLAN2.md`、両 MANAGER、`CONSOLIDATED_01_layout_and_ui.md`、`CONSOLIDATED_05_calendar_and_jobbox.md`、`CONSOLIDATED_07_workload_and_handover.md` を読む。PLAN2 は管理者が承認済み。旧方式アーカイブ、詳細画面、入力元・工程候補・対象判定はローカル実装済みで、本番未反映。

旧 `Leader/WorkloadAnalyzerController` のページ数・6カテゴリ相対順位は実態と合わず、参照用アーカイブへ保全する。現在の `Schedule/WorkAnalysis.vue` は Super Admin・情報出版限定の暫定試用で、時間ベースの点数。新詳細分析では入力元、作業種類、工程候補、記録率、時間、数量、案件管理、登録、残業を分けて示す。校正・オペレーターの継続入力者を比較の物差しとし、進行管理の担当案件数を基準値にはしない。名前・個別本番値を文書や Seeder に保存しない。

既存の `user_employment_settings.ranking_included` / `workload_tracked` を入力対象の管理に使う。非公開案件・他社・他部署は既存より広く見せない。新旧画面の切替は6か月の実データを照合してから。SSHは実行前に正確なコマンドと確認が必要。生産売上DBを調べない。`public/build` と `storage` の他者変更を保護する。通常の PHPUnit はローカル sales テーブルへ影響するため実行しない。

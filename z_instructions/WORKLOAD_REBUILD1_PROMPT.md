# WORKLOAD_REBUILD1_PROMPT — 再開用

SunBWork の情報出版部署で「作業実績の内訳」を軸に作業量分析を再設計する。まず `WORKLOAD_REBUILD_PLAN1.md` と `WORKLOAD_REBUILD_MANAGER1.md` を読むこと。AGENTS.md、`CONSOLIDATED_01_layout_and_ui.md`、`CONSOLIDATED_05_calendar_and_jobbox.md`、`CONSOLIDATED_07_workload_and_handover.md` も守る。管理者は設計文書を確認し、実装を承認済み。登録者・作成方法のDB追加と簡易作業候補の追加まで実装済み。本番は未移行。既存の `WORKLOAD_UI2_*`、`WORKLOAD_DEPT_*`、`PERSONAL_WORK_*` 計画を上書きしない。

要点: ページ数・サイズ・難易度は新ポイントの係数に使わない。マイジョブ、簡易結果、仕事の会議の時間を JST で統合し休憩・重複を除く。残業は勤務記録と認定作業時間の重なる分のみ追加点とする。進行管理案件は直近14日にチーム作業が動いたものを対象とし、リーダー1.0、副リーダー0.8を試験する。登録メンバー数も参考にし、案件点・登録点はオン/オフで実感と比較する。新規案件の実登録者をDB保存し、過去案件は現在リーダーで補完して由来を区別する。「ありがとう」は送受双方を非公開で観察し、当面点数に入れない。

本番SSHは AGENTS.md に従い、実行前に毎回正確なコマンドを提示して確認する。実在する人名と個別集計値を設計文書や Seeder に保存しない。生産売上データを調べない。`public/build` と `storage` に既存の他者変更があるため保護する。通常の PHPUnit は `tests/TestCase.php` が `sales` テーブルを消すため実行しない。

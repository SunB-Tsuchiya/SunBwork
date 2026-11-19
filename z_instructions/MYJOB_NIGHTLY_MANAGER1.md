# 新マイジョブ深夜完了 進捗1
2026-11-19。利用者が旧同等の深夜完了への修正を指示。既存バッチは旧project_job_assignmentsだけ、新personal_work_entriesが対象漏れ。実時刻00:05とJST境界をコードで確認。早期returnが旧0件時に新処理を阻害する点も確認。
確定: 本件を優先調査/修正、他作業保護、TODO画面確認は保留。提案: 同コマンドへ昨日以前の本人作業を追加、元イベント派生/当日未来除外、続きへ伝播/通知せず完了日時だけ。詳細PLAN確認待ち。
基準dbacf8381、branch feature/myjob-nightly-completion-20261119、worktree /tmp/sunbwork-myjob-nightly-20261119。直近本番7e2652606、最新SSH未確認。アプリ未実装/今回テスト未実施/未配備。次は具体設計確認後、対象漏れの合成再現と修正から進める。

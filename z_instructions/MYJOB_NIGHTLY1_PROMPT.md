# 新マイジョブ深夜完了 再開
AGENTS→JOB_EVALUATION_CONTROL1→MYJOB_NIGHTLY_PLAN1/MANAGER1を読む。基準dbacf8381、worktree /tmp/sunbwork-myjob-nightly-20261119、branch feature/myjob-nightly-completion-20261119。主作業985e6fffbのClaude/利用者変更を保護。前/tmpは消えていたので存在/保存版を照合する。
利用者要望は旧同等の深夜自動完了。既存AutoCompleteMyJobs/routes consoleは毎日00:05、旧自己割当だけが対象。新保存先を追加しearly return両方0へ。対象はINFO本人の昨日以前source_event_idなし未完了、当日未来/TODO/休暇/派生予定は除外。手動completeWorkは祖先/通知/外部連携するので呼ばない。
具体設計確認待ち、未実装。次は合成漏れ再現→実装テスト。配備は最新本番保持/正確SSH事前承認、manifest変更なし・今回DB追加なし。dry-runのみで本番手動実更新をしない。次回定期実行と利用者表示を別確認する。
TODO繰り返し4条件は未確認、休暇予定の4条件は成功済み。今回の優先修正後にTODOへ戻す。

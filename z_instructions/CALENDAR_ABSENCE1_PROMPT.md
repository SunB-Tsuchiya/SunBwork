# 休暇・遅刻・早退予定 再開
AGENTS→JOB_EVALUATION_CONTROL1→CALENDAR_ABSENCE_PLAN1/MANAGER1を読む。worktree /tmp/sunbwork-calendar-absence-20261118、branch feature/calendar-absence-plan-20261118、基準b4f6b3cd5。直近本番証拠971f5359f。旧/tmpは消えるので保存版と照合する。主作業/Claude/ziggyの未コミットを保護。
利用者の確認済み範囲は「カレンダーの休暇等の予定と在席表示連動」、日報/勤務実績は変更しない。5種類、入口名「休暇・遅刻・早退」、INFO SuperAdmin先行、専用データを在席GETに投影し後の手動優先、一方向連動が具体提案。まだ設計確認待ち、アプリ/DB未実装。
在席には既存paid_leave/half_am/half_pm/late/early_leaveがあるが既存同期はauth本人の会議等だけ。休む本人のログイン不要の投影が必要。手動退社update経由は日報自動生成があるため予定同期で呼ばない。理由を収集/公開せず手動コメントを壊さない。
TODO繰り返しは配備済みでも画面4条件が未確認。今回ユーザーが確認前に別修正を明言、確認済みへ伸ばさない。休暇設計承認後に実装、SSHは正確コマンドの事前承認、一般公開も別ゲート。

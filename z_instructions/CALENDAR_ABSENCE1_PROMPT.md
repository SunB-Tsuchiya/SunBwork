# 休暇・遅刻・早退予定 再開
AGENTS→JOB_EVALUATION_CONTROL1→CALENDAR_ABSENCE_PLAN1/MANAGER1を読む。worktree /tmp/sunbwork-calendar-absence-20261118、branch feature/calendar-absence-plan-20261118、基準b4f6b3cd5。直近本番証拠971f5359f。旧/tmpは消えるので保存版と照合する。主作業/Claude/ziggyの未コミットを保護。
利用者の確認済み範囲は「カレンダーの休暇等の予定と在席表示連動」、日報/勤務実績は変更しない。5種類、入口名「休暇・遅刻・早退」、INFO SuperAdmin先行、専用データを在席GETに投影し後の手動優先、一方向連動が具体提案。まだ設計確認待ち、アプリ/DB未実装。
在席には既存paid_leave/half_am/half_pm/late/early_leaveがあるが既存同期はauth本人の会議等だけ。休む本人のログイン不要の投影が必要。手動退社update経由は日報自動生成があるため予定同期で呼ばない。理由を収集/公開せず手動コメントを壊さない。
TODO繰り返しは配備済みでも画面4条件が未確認。今回ユーザーが確認前に別修正を明言、確認済みへ伸ばさない。休暇設計承認後に実装、SSHは正確コマンドの事前承認、一般公開も別ゲート。

2026-11-18具体設計を利用者承認。予定/在席表示のみ、5区分/日報上の入口/INFO SuperAdmin先行の実装を開始。本人保存と一括在席投影を実装中。コード検証/配備/画面確認はまだ未実施、TODO繰り返し4条件は保留。

2026-11-18実装/コード検証済み・未配備: 保存/時刻/所有境界/在席投影9ケース82断言、実在席Controllerで別人閲覧と既存会議同期の共存も成功。合成SQLite memoryの休暇/既存TODO/繰り返し/個人カレンダー（継承含む）473件4946断言成功。実Vueの休暇モーダル/ActionSheet/カレンダー連携、旧TODO/MyJob入力の回帰を確認。
利用者承認の読み取り専用SSHで、本番e0a4f436f2b5df1e638ebcc3a435ec5c56b0ecff/main、既知未追跡6件、manifest未変更を確認。前回971f5359f以降のポートフォリオPDF.js関連差分を保護するため、固定元e0a4f436fへソース差分を載せ直し、本番用資産は再ビルドする。一般公開/DB配備/画面確認は未実施。TODO繰り返しの画面4条件は保留を維持。

2026-11-18載せ直し後の最終検証: 固定元e0a4f436fのポートフォリオソースは変更なし、Ziggyは本番の全定義/URL設定を保持し新規5ルートだけを追加。再ベース後の休暇合成9ケース82断言と実Vue4スクリプト成功。変更PHP8件構文成功、/members再ビルド33.60秒、manifest887 refs918 missing0成功（固定元のmanifestも887件）。
次の1手: feature/calendar-absence-preview-20261118へ今回資産を固定してpushし、e0a4f436fから今回差分だけの配備を正確SSHコマンド/SHA256で確認する。今回専用2026_11_18_120001 migrationのみ--path --force。実装/コード検証済み、未配備・未画面確認、INFO SuperAdmin限定、一般公開/通知/Seeder/日報勤務実績変更なし。

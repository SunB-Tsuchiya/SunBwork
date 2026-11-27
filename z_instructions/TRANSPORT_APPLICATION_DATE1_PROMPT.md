# 再開: 交通費 申請日選択
AGENTS→TRANSPORT_APPLICATION_DATE_PLAN1/MANAGER1→TRAINS_PLAN2/MANAGER2→CONSOLIDATED_01/05/09を読む。TRAINSは別Claudeの資料なので上書きしない。
利用者は任意のカレンダー選択、土日祝を除く最終営業日までという案内、暦末日の既存自動ロックは今回変更しないことを確定。billing_dateが毎回submit時の当日へ上書きされるのを止め、選択日を保存/自動保存/帳票へ保持する。submitted_atと履歴イベントは実送信日時を保つ。新DB/帳票原本/集計/受理/運賃/同行者は変更しない。
具体設計は確認待ち、アプリ未実装。現在/tmp/sunbwork-transport-application-date-20261127は資料用sparse、branch feature/transport-application-date-20261127、基準bae6b4682、直近本番9640ba707。origin/mainや共有主作業を本番と同一視せず、Claude/ziggy/売上を保護。次は具体設計確認後に隔離ソースを展開。配備前に本番版を正確SSH承認で照合し固定差分だけ反映。日報と工程確定の成功済み画面は再試験しない。TODO繰り返し4条件は別件保留。

2026-11-27具体設計3点を利用者承認。隔離ソースを展開し実装開始。申請日選択と保存/帳票保持、送信実時刻と分離、締切ロック不変。検証/配備/画面確認は未実施。

2026-11-27 実装・コード検証完了、未配備・利用者画面未確認。申請日入力/対象月検証/自動保存待合せ/submit上書き廃止/管理の申請日・送信日時を実装。SQLite :memory:合成回帰116件1,202断言、実Vue入力/未来日/保存待合せ/エラー保持/読取専用を確認。PHP7件構文、本番用/membersビルド成功。Excel実fillSheetの日付セルとPDF実Blade HTMLの日付を合成確認（PDFバイナリ/実帳票の目視は未確認）。別会社・別人境界と暦月末ロック不変を合成確認。Changelogコードのみ追加、Seeder未実行。DB/migration/既存申請/通知は変更なし。
本番の読取確認は9640ba707/main、追跡変更なし・既知未追跡6件。保存基準bae6b4682、隔離branch feature/transport-application-date-20261127。次の1手: 固定差分の配備手順を作成し、正確なpush/SSHコマンドへの承認後に反映。日報/工程の成功済み確認は繰り返さず、TODO繰返し4条件は別件保留。

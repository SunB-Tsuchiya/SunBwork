# 再開: 交通費 申請日選択
AGENTS→TRANSPORT_APPLICATION_DATE_PLAN1/MANAGER1→TRAINS_PLAN2/MANAGER2→CONSOLIDATED_01/05/09を読む。TRAINSは別Claudeの資料なので上書きしない。
利用者は任意のカレンダー選択、土日祝を除く最終営業日までという案内、暦末日の既存自動ロックは今回変更しないことを確定。billing_dateが毎回submit時の当日へ上書きされるのを止め、選択日を保存/自動保存/帳票へ保持する。submitted_atと履歴イベントは実送信日時を保つ。新DB/帳票原本/集計/受理/運賃/同行者は変更しない。
具体設計は確認待ち、アプリ未実装。現在/tmp/sunbwork-transport-application-date-20261127は資料用sparse、branch feature/transport-application-date-20261127、基準bae6b4682、直近本番9640ba707。origin/mainや共有主作業を本番と同一視せず、Claude/ziggy/売上を保護。次は具体設計確認後に隔離ソースを展開。配備前に本番版を正確SSH承認で照合し固定差分だけ反映。日報と工程確定の成功済み画面は再試験しない。TODO繰り返し4条件は別件保留。

2026-11-27具体設計3点を利用者承認。隔離ソースを展開し実装開始。申請日選択と保存/帳票保持、送信実時刻と分離、締切ロック不変。検証/配備/画面確認は未実施。

2026-11-27 実装・コード検証完了、未配備・利用者画面未確認。申請日入力/対象月検証/自動保存待合せ/submit上書き廃止/管理の申請日・送信日時を実装。SQLite :memory:合成回帰116件1,202断言、実Vue入力/未来日/保存待合せ/エラー保持/読取専用を確認。PHP7件構文、本番用/membersビルド成功。Excel実fillSheetの日付セルとPDF実Blade HTMLの日付を合成確認（PDFバイナリ/実帳票の目視は未確認）。別会社・別人境界と暦月末ロック不変を合成確認。Changelogコードのみ追加、Seeder未実行。DB/migration/既存申請/通知は変更なし。
本番の読取確認は9640ba707/main、追跡変更なし・既知未追跡6件。保存基準bae6b4682、隔離branch feature/transport-application-date-20261127。次の1手: 固定差分の配備手順を作成し、正確なpush/SSHコマンドへの承認後に反映。日報/工程の成功済み確認は繰り返さず、TODO繰返し4条件は別件保留。

2026-11-27 配備完了: 利用者が正確なpush/SSHの2コマンドを承認。originへeeb32d525をpushし、SHA256 113de77bd0d6d5d3cd969462fd9be8ed946855d24afa39b9ad9a4295c51afb00の固定手順を実行。本番9640ba707→eeb32d525をfast-forward、PHP事前構文・manifest887/ref1135欠落0・/members base・HEAD/既知未追跡6件保持を確認、DEPLOY_OK。config/cache clearのみ。DB/migration/Seeder/実申請/通知は未実行。利用者画面確認は未実施。次の1手: 申請日の対象月内選択→保存/再読込保持→PDF/Excel日付を確認。実際の申請操作は今回必須にせず、送信実日時/再申請は合成確認済みとして区別する。

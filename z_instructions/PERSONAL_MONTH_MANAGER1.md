# 個人月カレンダー・TODO 進捗1

2026-11-16調査: Calendar.vue/CalendarController、ClerkScheduleCalendar/ClerkEvent/ClerkEventController、TeamCalendar、TODO名検索を確認。会社共有ClerkのDBを個人化してそのまま使うのは不可。新本人専用データを月とTODO一覧で共用する具体案を作成。
確定: 週/日は現行ジョブ入力維持、月のジョブ初期非表示・切替、新個人TODO連動（利用者回答）。
提案: イベント/締切/TODO、本人専用、情報出版SuperAdmin確認から、時間評価へ非混入、専用テーブル/再送キー/版競合検知。通知/繰返し/TODO→MyJobは後段。公開対象等は未確定。
状態: 設計・管理・再開資料作成済み。アプリ実装/コード検証/DB/本番配備/今回画面確認は未着手。
次の一手: 具体案の範囲を利用者確認。コード変更に進める承認と一般公開の承認は分ける。

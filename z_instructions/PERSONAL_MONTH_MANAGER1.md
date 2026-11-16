# 個人月カレンダー・TODO 進捗1

2026-11-16調査: Calendar.vue/CalendarController、ClerkScheduleCalendar/ClerkEvent/ClerkEventController、TeamCalendar、TODO名検索を確認。会社共有ClerkのDBを個人化してそのまま使うのは不可。新本人専用データを月とTODO一覧で共用する具体案を作成。
確定: 週/日は現行ジョブ入力維持、月のジョブ初期非表示・切替、新個人TODO連動（利用者回答）。
提案: イベント/締切/TODO、本人専用、情報出版SuperAdmin確認から、時間評価へ非混入、専用テーブル/再送キー/版競合検知。通知/繰返し/TODO→MyJobは後段。公開対象等は未確定。
状態: 設計・管理・再開資料作成済み。アプリ実装/コード検証/DB/本番配備/今回画面確認は未着手。
次の一手: 具体案の範囲を利用者確認。コード変更に進める承認と一般公開の承認は分ける。

2026-11-16再開・実装中: 利用者が第1版の範囲（本人専用月+新TODO、INFO SuperAdmin確認、一般公開別承認、通知/繰返し/MyJob連携後段）を承認。現在の本番経路はCalendarController→Pages/Calendar.vue→Components/Calendar/UserCalendar.vue→独自Schedule/MonthView.vueであると実装前調査で判明。旧Calendar.vue/FullCalendarを編集する候補を撤回し、この現行経路へ限定して設計を具体化。専用table/API/Service、版・再送、月/一覧/モーダルと月だけのジョブ切替を実装中。週日イベント配列は維持。実装/コード検証/配備/画面確認は別状態。次は合成境界テスト。

2026-11-16実装・合成確認: PHP142件1499断言成功（既存mixed-series警告1件）。新規6ケースで本人/他人/別会社/INFO以外/一般User拒否、再送/409/削除410、無期限TODOと過去期限の月非依存一覧/完了/取消/ページング、JST締切/期間/検証を確認。関連旧新MyJob/時間配分も失敗なし。実Vueで月のjob切替/保存二重送信・CSRF/通信不明再送制限/409入力保持/期間表示・選択/旧月のdrag非介入/一覧と月同一ID/古い取得レスポンス排除/文字列escapeを確認。5ルートを一時生成しZiggyの追加定義だけ反映、他不変。ChangelogSeederコード追加、Seeder未実行。次は/members最終buildと本番版の照合。配備/画面確認は未実施。

2026-11-16配備準備完了: 最終/members build成功、manifest全参照欠落0・本番baseを確認。ChangelogSeeder構文成功、Seederは実行しない。コードはSuperAdminかつSunBrain INFO実所属・会社context一致だけ有効で、一般User APIは403。専用migration以外を適用せず、既存データのコピー/変換はしない。次は直近確認本番a16fd1e9aを固定元とする差分検査付き手順への承認。版や作業中ファイルが違えば変更前に停止。今回の本番画面確認はまだ未実施。

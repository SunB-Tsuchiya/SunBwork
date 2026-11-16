# 個人カレンダーの月表示・個人TODO 設計1

2026-11-16。基準ce1fe2756、branch feature/personal-month-calendar-research-20261116、worktree /tmp/sunbwork-myjob-time-breakdown-header-20261116。本番直近確認a16fd1e9a。これは具体設計案。コード/DB/配備未着手。

## 確定と未確定
確定: 週・日は従来のジョブ入力を維持。月表示をイベント・締め切り・TODOに活用。回答で月の細かなジョブは初期非表示/切替表示、新しい個人TODO一覧と連動する、と確定した。既存TODO連携を探し続ける案を置換する。
提案（承認待ち）: 本人専用の新データで、月カレンダー+横のTODO一覧を同一画面にする。Clerkの色分け/終日/期間/完了を参考にするが会社共有データを個人へ流用しない。最初は現在の改修対象サン・ブレーン情報出版でSuperAdmin確認→対象者公開、他部署/サンエー公開は別判断。既存週/日の初期表示設定は変えない。
後続提案: TODOから新MyJobの作業枠を作る明示連携、通知/繰返し/チーム共有/外部連携は第1版へ含めず別工程。TODO完了とMyJob完了の自動連動も第1版では行わない。

## 調査根拠
ClerkScheduleCalendarはFullCalendar、色/完了、終日・期間と週間プランナーを持つ。ClerkEventは会社+calendar_kind単位、作成user_idだけで本人専用に絞る設計ではない。Clerk APIの会社生成/取得やClerk用ルール/リマインダーを個人APIへ流用しない。個人Calendar.vueにdayGridMonth/timeGridWeek/timeGridDayがあり、初期ビューは既存URL/端末による。個人CalendarControllerにはClerkの会社向けリマインダー取得もあるため既存リマインダー表示は維持する。日付はAGENTSのJST規則、レイアウトはCONSOLIDATED_01/05に従う。

## 第1版の画面
- 既存の月/週/日切替とURLを保つ。週/日のMyJob作成・ドラッグ・編集・作業分析へ影響させない。
- 月は既存の月間マス目を使い、個人イベント/締め切り/期限付きTODOを追加表示。「ジョブも表示」切替は初期OFF。新MyJobと旧ジョブ予定の両方を対象にし、単なる会議/外出まで誤って消さない。既存会議・外出と日報入口は維持する。どのレコードをjobと判別するか実装前に既存APIの型を精査する。
- 横に「未完了TODO」。狭幅では月の下。期限超過→期限が近い→期限未定の順、件数表示、追加/編集/完了。期限未定TODOは月マスへ無理に配置せず一覧だけに出る。「完了も表示」で履歴確認できる。
- 月の日付をクリックして追加モーダル、種別「イベント」「締め切り」「TODO」。月ドラッグは終日/期間の選択、週/日の時間帯ドラッグは現行MyJobの導線を維持する。
- イベント: 名前、日付/期間、終日または時刻、色、メモ。締め切り: 名前、締切日、任意の時刻、色、メモ。TODO: 名前、任意の期限日/時刻、色、メモ。タイトル以外は種別に必要な最小限だけ。
- 期限日の変更と完了は一覧/月で同じIDを更新。期限超過はJSTで判定し完了済みを除外。日付だけならその日の終わりまでは超過扱いにしない。期限のないTODOを超過にしない。
- 色はClerkの配色を参考に固定パレットから選ぶ。色名の個人編集/並べ替え管理は初版では増やさない。期限超過は色だけでなく「期限超過」と文字でも示す。
- 編集/削除は共通ルールに合わせモーダル上部の見つかる位置へ。保存中連打防止/失敗時入力保持/削除確認/背景クリック・ドラッグで閉じない。未保存破棄の確認を設ける。
- 完了はチェックと取り消し可能。TODO・締め切りの消し込みは作業実績やMyJobの完了とは別。イベントは過去日になっただけで自動完了にしない。

## データと境界（提案）
専用personal_calendar_itemsテーブル: id、company_id、user_id、item_type(event/deadline/todo文字列)、title、note(nullable)、start_date/end_date(nullable date)、start_time/end_time(nullable time)、all_day(boolean)、color_key、completed_at(nullable)、version(unsigned default1)、request_key(uuid)、request_hash、timestamps。company/user/request_keyをunique、company/user/start_dateなどにindex。
TODOのstart_dateは期限日を意味し、UIでは期限日と表示。deadlineは必須の締切日、eventは必須の開始日。event以外にend_date/end_timeは受け付けない。TODOの期日未設定ならtimeも不可。時刻指定の終了は開始以後、期間は初版最大366日。曖昧な終日endの扱いはDB上で包含日、FullCalendarへ渡すときだけJSTの日付で翌日排他終端へ変換。dateキャストはdate:Y-m-d、toISOStringで日付を作らない。
ユーザー本人+実会社で全GET/POST/更新/完了/削除を制限。別人ID/別会社ID/会社コンテキスト不一致を拒否。会社・所有者はPOSTから変更できない。Admin/Leader/Coordinatorでも他人の個人メモを追加閲覧する入口は作らない。情報出版限定公開判定は別に持ち、個人データの所有は部署ではなく本人+会社に置く。部署異動や会社切替で誤公開しない。
既存events/PersonalWorkEntry/ClerkEventへコピー保存しない。日報取込/部署予定/みんなの記録/作業点数・時間集計に新データを混ぜない。期日を書いただけでは作業時間にならない。

## APIと保存
認証済みweb.phpへ個人API。月の表示範囲に重なる日付付きitem取得、TODO一覧は期間に関係なく未完了をページ分割取得（期限未定/昔の未完了を月範囲で落とさない）。完了履歴は上限付きページング。GETで共有Clerkの予定生成等の書込をしない。JST範囲を検証し最大表示範囲を制限。
作成はrequest_keyと検証済み内容hashを保存。同一key・同一内容の再送は既存1件を返し、同じkeyの異なる内容は409拒否。更新/完了/削除はversionと行ロックで古い画面からの上書きを409拒否し、入力を保持。種別変更時の不要な期日/終了値は明示検証し古い情報を黙って引き継がない。HTMLは文字列として表示しメモからスクリプトを実行しない。
月のジョブ表示切替は本人別のlocalStorageキーとし、読取不能/不正値はOFF。既存のlastTabやカレンダービュー保存キーは流用しない。月を離れても週/日のジョブは必ず現行どおり表示する。

## 実装段階・影響候補
1. model/専用migration/認可Service/API/合成テストを実装。既存DB移行/一括書換なし。
2. 月のレコード表示とTODO一覧/共通入力モーダル、日付/完了を同一データに接続。Calendar.vueの月分岐を小さくし、週/日の処理は維持。新部品はPersonalMonthPlanner/PersonalTodoList/PersonalCalendarItemModal等を候補とする。
3. job切替、URL/保存状態/レスポンス競合、配色/モバイル/フォーカスを検証。SuperAdmin限定で今回範囲を確認し、一般公開は固定差分と対象を別途承認してから。
対象候補はCalendarController/Calendar.vue、新model/migration/Service/Controller/部品、routes/Ziggy、PHP/実Vueテスト、管理/変更履歴/統合資料。ClerkのAPI/保存範囲は変更しない。

## 検証と未決
合成: 本人/他人/別会社/INFO以外/会社コンテキスト、作成再送/古い版/全操作認可、期限未定/終日/複数日/月跨ぎ/日付のみ締切/取消完了/削除、注入文字列、範囲/ページ上限。既存点数/作業時間の不変。
実Vue: 月・一覧の同一ID更新、ジョブ初期OFF/切替/週日へ戻る、未完了の別月・無期限TODO、保存失敗/競合で入力保持、二重操作、狭幅/フォーカス、旧ジョブ/会議/日報を混同しない。/members build/Ziggy/manifest欠落、本番は今回専用migrationだけ--path --forceで適用。
未決（利用者確認）: 最初の公開は情報出版でよいか/本人専用でよいか、第1版を月+TODOまでに絞り、通知・繰返し・TODO→MyJob連携を後段とするか。本計画の確認前にコード変更はしない。SSHの正確なコマンド事前確認を守る。

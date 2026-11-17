# 個人TODOワークスペース 第1段階 設計1

2026-11-17、基準70926c894（公式アプリ調査資料）、branch feature/personal-todo-workspace-plan-20261117、隔離worktree /tmp/sunbwork-personal-month-resume-20261117。本番直近の実行証拠ff17e8671。共有mainのClaude/利用者作業は保護する。参照: AGENTS、JOB_EVALUATION_CONTROL1、PERSONAL_MONTH_PLAN1/MANAGER1、PERSONAL_TODO_RESEARCH_20261117、CONSOLIDATED_01/05。

## 確定範囲と状態
利用者が公式4製品の調査に基づく案へ「OKです。その案で行きましょう」と回答。カレンダー左を日々の確認用、別ページを整理/編集/設定用にする。採用順序は①専用ページ/自動一覧/検索/今日やる/重要/個人リスト/チェック項目/表示設定、②繰返し/通知/まとめ操作、③TODOからMyJobの明示連携。第1段階で後段の通知や作業実績連動を暗黙追加しない。
本人専用/INFO実所属SuperAdmin限定で実装・確認、一般公開は別承認。旧新MyJob、日報、Clerk共有カレンダー、部署の作業と点数集計は現状維持。今回の設計はアプリ実装前の具体化であり、下記DB/互換方針の確認後に実装する。

## 現行との接続
現在personal_calendar_itemsがイベント/締切/TODOを所有し、PersonalMonthCalendarが本人+実会社+context一致と試用対象を全操作で認可、versionの競合検知、request_key/hashによる作成再送の一意性、削除後再送410を実装済み。UserCalendarのusePersonalCalendarStateを月週日共通で保持し、左PersonalTodoListと月PersonalMonthPlannerが同じ取得/編集/完了を使う。保存/期限/完了・配置3条件・時間表記は利用者確認済みで反復試験を依頼しない。
この所有テーブルの同じIDを使う。専用TODOページ向けに別のタスクをコピーしない。日付付きイベントは月表示のまま、TODOページの整理対象はtodo/deadline。個人リストは本人の整理用で、共通の大まかな案件分類や正式project_jobsと混同しない。

## 画面
### カレンダー左の確認欄
「TODO・締切（件数）」の開閉と本人+会社別の開閉状態を維持。完了/簡易追加/期限表示を継承し、下部へ「TODOを開く」。初期表示は期限超過・今後7日・今日やるを合わせた直近対象、最大10件（設定で5/10/20/全未完了）。期限未定は直近一覧から外れても専用ページ/期限未定一覧へ必ず残り、全未完了にも切替可。直近へ変えたことを説明する。左欄を全件整理画面にしない。狭幅TODO件数ボタンは継承。
### 専用TODOページ
AppLayout+UserNavigationTabs、header左に灰色の「← カレンダーに戻る」と見出し。戻る先は入口のdate/viewを名前付きrouteへ引継ぎ、未指定なら既存カレンダー。上段のユーザーメインタブは増やさず、左の「TODOを開く」とカレンダー見出し付近を入口にする。
内部タブを「TODO」「リスト管理」「表示設定」の3URLに分け、既存の役割別UI/戻る/編集削除配置を踏襲。SuperAdmin専用の新しい管理者メニューではなく、User画面の本人TODOの試用として扱う。
TODOタブは左: 自動一覧/本人リスト、中央: 検索・並び・追加欄/タスク一覧、右: 選択TODOの詳細。狭幅は一覧→詳細と戻る。未選択では右に案内。詳細は名前/期限日・時刻/今日やる/重要/リスト/色/メモ/チェック項目。タスク名だけの簡易追加ができ、分類や期限は任意。URLにview/list/選択ID/検索を持てるが、対象の本人認可は毎回サーバで行う。
本文編集は明示「変更を保存」。重要/今日やる/完了/チェック項目の単独切替はクリックで保存し失敗時は元状態を保持。未保存編集がある状態で選択切替・一覧/URL移動・閉じる場合は破棄確認、競合409では内容を残す。詳細の削除は赤ボタン+確認。完了は取り消せる。エラーだけ出して無言で古い内容に戻さない。

## 自動一覧・意味
- 今日やる: focus_dateがJSTの今日で未完了のTODO/締切。締切を変えず本人が選ぶ。日が変わってもTODO本体は消さず、すべて/元リストに残る。前日のfocus_dateを自動で翌日に変更しない。
- 重要: is_important=true、未完了。最初は重要/通常の1段階。
- 期限超過: 日付だけならJST当日の終了後、時刻があれば指定時刻後。完了済みを除く。
- 今後7日: JST今日から6日後までに期限がある未完了。超過と期限未定は別に示す。
- 期限未定: start_date=NULLのtodo。deadlineは日付必須を継承。
- すべて: 全未完了todo/deadline。
- 完了: 完了済みの履歴。名前/メモ検索とリスト絞込ができる。
本人リストは上記と独立した所属。リストなしは仮想「未整理」。期限や今日やるで別リストへ移動しない。どの自動一覧も同じIDへの絞込であり重複タスクを作らない。
検索はタイトル/メモの文字列、最大100字。SQL LIKEのワイルドカードをescapeする。並びは期限順（時刻なしは当日末尾、無期限は最後）/重要順/追加順、同順はIDで安定。ページ分割30件。集計件数も同じ本人scopeを使う。未知のフィルター/保存値は安全な「すべて」へ、他人IDや別会社IDは404/403で拒否する。

## DB（具体案）
今回専用migrationを1本用意する。既存行のID/期限/メモ/完了・request_key/hash/version/所有者を一括書換しない。
1. personal_calendar_itemsに追加: todo_list_id(nullable foreignId)、is_important(boolean default false)、focus_date(nullable date)、steps(nullable JSON)。dateはdate:Y-m-d、stepsはarrayキャスト。既存行は未整理/通常/今日選択なし/チェック項目なしとして使える。
2. 新personal_todo_lists: id、company_id、user_id、name(max80)、color_key、sort_order、archived_at(nullable)、version(default1)、request_key(uuid)、request_hash(64)、timestamps。所有者+request_keyをunique。本人の有効リスト内の正規化同名は部署ではなく本人単位で拒否。本人User行ロック下で追加/並べ替えを直列化。最大100リスト、項目は現在のCalendarColorsから選ぶ。
3. 新personal_todo_settings: id、company_id、user_id、initial_view(all/today/important/upcoming/undated)、default_sort(due/important/created)、sidebar_view(upcoming/today/all)、sidebar_limit(5/10/20/30)、show_completed(defaultfalse)、version、timestamps。本人+会社unique。レコードなしなら定義済み既定値、保存で初めて作成。開閉状態は既存localStorageのままでDBに二重保存しない。
各新テーブルのFK/複合index名はMySQL長さ制約以内。stepsは1階層・最大50個、各{id: UUID, title: string max255, completed: bool}、順は配列順。親versionでまとめて保護する。チェック完了2/3を表示するが、全チェックが完了しても親を自動完了しない。親完了でも未チェックを勝手に書換しない。

## 個人リストと設定
リストの追加/名称・色編集/並び替え/アーカイブ・復元。初版では中身を一括移動して削除する機能を設けず、既存タスクの所属と履歴を守る。アーカイブ済みのリストは追加先から外すが、そのタスクは「すべて」/検索で見つかり、設定で復元できる。現在所属済みのアーカイブリストをほかの項目の保存で無言解除しない。他人リストや別会社リストは指定不可。
リストの並び替えは本人の全有効兄弟ID・各versionを照合してtransaction内で保存し、漏れ/重複/他人混入は全拒否。新リストは現在の末尾へ。共有の大まかな案件の並びには触れない。
設定の初回保存も本人User行をロックしuniqueとversionで競合を検知。設定と一覧の反映は共通Serviceから返す。古い画面が本人設定を黙って上書きしない。

## API・互換性・境界
PersonalMonthCalendarの認可/所有queryを共用するModel scopeまたは共通Serviceに揃え、専用TODO GET/詳細/リスト/設定にも同じINFO SuperAdmin/実会社context一致を適用する。別人を見られるAdmin/Leader例外は作らない。専用ページの表示だけでなく全JSON操作で再認可する。
既存personal_calendar.items APIは後方互換を維持。既存のイベント/予定編集から未送信のtodo_list_id/is_important/focus_date/stepsを消さない。新規で追加メタ未指定の場合は既存のcreate request_hashの算出内容を変えず、試用版の保存キー再送を重複扱いにしない。新メタが明示送信された場合だけ検証・hashに含める。update/完了/単独切替はすべて同じ親versionを更新する。
新メタはtodo/deadlineだけ。eventへ種別変更する場合はリスト/重要/今日/チェック項目をどう扱うか黙って初期化せず、確認または解除を明示する。第1版は専用TODO詳細ではeventへの種別変更入口を持たず、既存カレンダー側でTODOからeventへ変える場合は新メタがあると422で確認案内して保持する。
GET専用ページはInertia、JSON一覧/詳細/メタPATCH/リスト/設定はweb.phpの認証付きroute。route()は名前付きパラメータをobjectで渡す。CSRFはmetaから。URLのクエリ保存に本文メモは入れない。新ルートは旧lastTab URLを変更しない。
作成系（リストを含む）はrequest_key/hashで同一再送を返す。更新/完了/チェック/並び/設定/アーカイブはversion+行ロック、競合409。複数IDを送る並びは全件検証後に保存し、部分更新をしない。JSONやメモは文字列表示でHTMLを実行しない。

## 段階・影響候補
1. 既存互換を守るModel/migration/Service/APIと合成テスト。stageのソース変更は専用branch/worktree内。既存レコードの移行CLIや一括補完は作らない。
2. 専用Inertiaページ/3列・モバイル詳細/共通編集とチェック項目、リスト/表示設定。既存個人モーダルから保存しても新メタ保持を確認。
3. カレンダー左の直近表示と「TODOを開く」を接続。TODOページから戻る日付・ビューを保持。既存月のイベント/締切表示、週日のジョブを維持。専用ページとカレンダーを開きっぱなしにした競合も検証。
4. /members build/Ziggy/manifest、固定差分・今回専用migration --path --forceだけを事前承認後に配備。SuperAdminの今回新規範囲を確認し、一般公開は別判断。完了時にChangelogSeederのupdateOrCreate、統合資料、管理/再開を更新する。
候補: PersonalCalendarItem/新List/Setting Model、専用migration、PersonalMonthCalendarの互換拡張/共通所有scope、PersonalTodoWorkspace Service/Controller、新Pages/PersonalTodo/*、入力/チェック項目/リスト部品、PersonalTodoList/usePersonalCalendarState、routes/Ziggy、関連PHP/実Vueテストと資料。

## 検証・完了ゲート
合成: 全GET/単独切替/リスト/設定の本人・会社・context・試用対象、別人ID、送信漏れの新メタ保持、旧hashの再送、日付未定/期限超過/今日やる日跨ぎ、安定ソート/検索escape/ページ上限、チェックID/順と50個制約、リスト重複/他人/アーカイブ/版/全件並び、設定初期値/版。データを消すDBリセットは使わずSQLite memory等の合成だけ。
実Vue: 3列と狭幅、未保存入力の選択/離脱保護、409入力保持、重要/今日と期限の独立、同じIDがカレンダー/一覧で更新されること、左欄の件数/直近/期限未定への入口、チェック操作/作成再送/失敗、リスト/表示設定、localStorage例外、名前付きrouteとCSRF。既存MyJob/日報/評価値は非回帰。
状態を実装/コード検証/配備/利用者画面/一般公開で分ける。既確認のカレンダー保存・配置・日報注意・記録時間の画面を反復しない。

次: 本具体設計（同一ID維持/3テーブル変更/今日と期限分離/チェックと親完了独立/旧編集の互換）を利用者確認し、承認後に第1段階のDB/Serviceから実装。

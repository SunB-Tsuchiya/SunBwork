# 個人カレンダー 休暇・遅刻・早退の予定 設計1

2026-11-18 JST。基準b4f6b3cd5（前工程配備記録）、直近本番実行証拠971f5359f。主作業985e6fffbと未コミットziggy/Claude作業は保護。branch feature/calendar-absence-plan-20261118、worktree /tmp/sunbwork-calendar-absence-20261118。旧/tmpは消失していたため保存commitから隔離復元。新しい本番版はSSH前確認を経るまで断定しない。

## 決定・今回の位置
利用者が繰り返しTODOの画面確認前に別件を指定し、休暇/半休/遅刻等を個人カレンダーから任意登録し在席ボードと連動する要望。追加質問へ「まず予定・在席表示を連動する（推奨）」と回答済み。日報/WorkRecordの時刻・実績や評価値の自動変更は今回しない。繰り返しTODOは本番971f5359f反映済みだが依頼した4条件は未確認、成功扱いにしない。
具体実装はこのPLAN/MANAGER/PROMPT確認後。公開範囲は従来の慎重な運用を継ぎ、まず情報出版実所属SuperAdminの本人登録だけ試用、一般Userへの登録公開は別確認。既存ボードの同社閲覧者には登録者の現在の予定状態が見える。

## 調査結果
参照: AGENTS/JOB_EVALUATION_CONTROL1・ROADMAP1/PERSONAL_TODO_REPEAT_MANAGER1、CONSOLIDATED_01/05、archived/IRUKA_PLAN1、UserPresenceController/UserPresenceStatus/IrukaStatusOrder/statusConfig、CalendarController/UserCalendar/ActionSheet、UserMonthlySchedule/UserSetting/Worktype/UserMonthlyBreak、CalculatesEventTime、DiaryControllerとWorkloadTrialAnalysis。
- ボード既存slug: paid_leave（有給休暇）、half_am（AM半休）、half_pm（PM半休）、late（遅刻）、early_leave（早退）。会社ごとの有効状態/名称/色を管理できる。新たな重複ステータスを作らない。
- 既存syncCalendarStatusはGET /presenceでauth本人だけを同期し、会議・打合せ・外出・来客のeventsを参照。休暇の予約データや未来日の在席予約はない。本人が休んでログインしない場合にも同社のボード閲覧で反映できるよう拡張が必要。
- UserPresenceStatusは現在値で、手動状態とコメント/変更時刻を保持する。未来の休暇で今日の状態を変更してはいけない。
- 手動「退社」はmaybeAutoCreateDiaryでDiary/WorkRecordを作る経路がある。今回の予定連動はそのupdate経路を呼ばず、日報や勤怠を発生させない。
- 日別勤務形態はUserMonthlySchedule→UserSetting→会社の既存既定、昼休憩はUserMonthlyBreak→既存ユーザー設定/既定を参照する。勤務形態自体には午前休/午後休の属性はない。

## UI
ActionSheetの日報入力のすぐ上に「休暇・遅刻・早退」を追加。初期は新しい試用共有フラグで条件表示し、既存会社/部署の入口は変えない。クリックでモーダル、背景やEscapeでは閉じず明示保存/キャンセルで閉じる。未保存変更は破棄確認、処理中二重送信禁止、409/422/通信失敗は入力保持。
項目: 日付（選択日を継承）、種類（有休/午前休/午後休/遅刻/早退）、時刻（必要な種類だけ）。私用理由の入力は初版では作らず、カレンダー/ボードへ不要な理由を公開しない。
- 有休: 終日。当日の00:00〜翌00:00で在席予定を表示。
- 午前休: 対象日の勤務開始〜午後の出勤予定時刻。出勤予定は既存昼休憩終了を初期値にし、本人が確認/修正できる。
- 午後休: 退出予定時刻〜勤務終了。退出予定は既存昼休憩開始を初期値、確認/修正可能。
- 遅刻: 勤務開始〜到着予定時刻。到着予定を必須入力。
- 早退: 退出予定時刻〜勤務終了。退出予定を必須入力。
勤務開始/終了は当日の勤務形態から初期表示するが、未設定/夜勤/日跨ぎ等で日内の有効区間を確定できない場合、明示時刻を入力してもらい推測で保存しない。勤務時間や休憩設定を変更しない。
モーダルに「在席ボードに予定として反映します。日報の勤務時刻は変更しません。」を短く表示。保存時に対象日/種類/適用区間を本人が確認できる。日内のstart<end、終日と時間指定の排他、半休・遅刻等の時刻矛盾はサーバでも検証。
保存した項目は個人カレンダーの月/週/日で区別できる予定として表示。有休は終日、他は不在時間の帯/表示で示す。クリックから編集/削除。初版はドラッグによる種別/時刻変更を止め、明示編集へ誘導する。既存MyJob/通常予定のドラッグ動作は変えない。

## データと境界
新personal_absence_plans（仮称）へ独立保存。company_id/user_id、date(date:Y-m-d)、kind（上記5slug）、starts_at/ends_at（JSTとして明示）、version、request_key/hash、timestamps、softDeletes。owner+date/index、owner+request_key unique。1回は1日内（有休終了だけ翌00:00）、複数日の一括/繰り返し登録は後続。初版で時間が重なる不在予定は本人内で422拒否、午前と午後が隣接する別予定は許可する。
新APIのGET/保存/更新/削除は実会社とUser会社context一致とINFO実所属SuperAdmin、owner条件を全て検証。Bodyの任意user_id/company_idで対象を変えない。作成はUser行ロック/key hashで再送一意、更新/削除はversion+行ロック。旧データを一括登録/変換しない。
通常eventsやpersonal_work_entries、project_job_assignments、private TODOへコピーしない。カレンダー表示は専用APIの投影（_source=absence-plan、prefix ID）で重複DBを作らない。このデータを作業時間・会議・勤務実績として集計しない。カレンダーの会議通知/参加者招待も発火しない。

## 在席ボード連動
新AbsencePlanService（仮称）の投影をGET /presenceの同社・表示対象ユーザーへ一括適用。ユーザー本人のログイン不要。既存ポーリング（約30秒）で現在時刻[JST,start<=now<end]に該当すると対応する既存slugを返す。未来は現在の状態に影響させない。午前休の終了や到着予定を過ぎたらこの投影だけを外し、既存ボードの状態処理へ戻す。無条件に「在席」にしない。
UserPresenceStatusの手動値/コメントを上書きしない。対応する会社ステータスが無効なら無言で別slugへ置換せず、登録時に説明し連動不可を案内する（初版は該当種別を選択不可、既存予約の表示は保持）。
適用開始または予定の最終保存より後に手動変更されている場合は手動を優先。比較基準はmax(予定の適用開始, updated_at)。例: 休暇登録後に出社して「在席」へ変更したら、それを戻さない。予定を新しく変更/保存した場合は新しい本人操作として再適用する。会議由来source=calendarの値は休暇投影より優先させない。
APIには予定連動のsourceと期間を付け、ボード/バッジで現在の状態が予定連動であると区別できるようにする。理由は返さず、コメントは従来の本人コメントを保持。通常会議連動は今回広範に書換えず、休暇投影と共存する。GETの投影は在席DBへ書かないため、予定の編集/削除時も手動の情報を壊さない。
在席ボードから手動で有休/遅刻にした値は時刻/日付/範囲がないため、自動でカレンダーに逆登録しない。初版はカレンダー→在席表示の一方向。後日の逆登録導線は別設計にする。

## 変更候補・検証
専用migration/Model/Service/Controller、web.php/CalendarController共有フラグ、UserCalendar/ActionSheet/新モーダル/カレンダーDTO・ドラッグ区別、UserPresenceControllerとIruka表示の予定連動注記、関連合成PHP/実Vueテスト、Ziggy、ChangelogSeederと統合資料。レイアウトはAppLayoutや既存モーダルの青/戻る/編集削除の配置を踏襲。CSRFはmeta、routeは名前付きobject、日付はUTCに変換せずJSTで扱う。
合成SQLite memory: 本人/会社/部署/公開範囲、key再送/hash競合/version、当日・未来・境界・有休翌00:00、半休/遅刻の時刻/重なり、本人不在時の別ユーザー閲覧、一括投影と有効status、手動優先・コメント保持・削除で投影解除、日報/WorkRecord/仕事集計が増えないこと、通常会議連動共存。実Vue: 選択日継承/種類別必須時刻/保存失敗入力保持/外側クリック不閉/二重送信/編集削除/月週日表示/一般会社に入口なし。
本番配備は最新HEAD/作業中ファイルの読み取りを正確SSHコマンドで承認後に確認。固定元/target/差分hash、今回専用migration --path --forceだけを実行、他作業/未追跡を保護。コード/配備/利用者画面/一般公開を別管理する。TODOの未確認4条件は完了扱いせず継続資料へ残す。

次の1手: 入口名・5種類・まずINFO SuperAdmin試用・一方向連動/手動優先の具体設計を確認後に実装する。

# カレンダー・JobBox・イベント（統合）
最終更新: 2026-10-08

## 情報出版の「部署の作業」（2026-10-08 追加）

- ヘッダー「予定表」内の「部署の作業」タブは、同じ会社・同じ `INFO` 部署の利用者だけに表示する。取得・保存 API は `schedule.department_work.*`。他部署・グループ会社のデータは返さない。
- 本番試用中は `PersonalWorkSchedule::available()` を Super Admin かつ `INFO` 所属に限定する。カレンダーの簡易入力、予定表の「部署の作業」「作業実績の内訳」、関連 API、日報の作業結果欄に同じ判定を適用する。一般公開時にはこの判定を見直す。
- 情報出版の本人は User の個人「カレンダー」で日付・時間帯をドラッグし、操作メニューの「簡単な作業予定を追加」を選ぶ。選択した時刻を簡単入力モーダルへ渡し、大区分と任意の案件を登録する。既存の「会議などの予定を追加」などの詳細入力は同じメニューに残る。カレンダー上部「今日の作業・部署一覧」は閲覧用の部署一覧へ進む。SuperAdmin など情報出版部署に属さないアカウントには入口を出さない。
- 個人カレンダーは既に `schedule.events.range` と `calendar.events.range` を重複排除して統合している。新しい部署タブも既存 `events` を参照し、会議の `visibility=public/company/group` は同社の既存公開規則に合わせて件名を表示する。`private` と個人予定は本人・招待者以外には伏せる。会議を実績化したコピーは招待元との二重表示を避ける。
- 部署一覧はオペレーター・校正カレンダーと同じ「担当者を縦、時刻を横」の日別タイムラインを基調にし、本人の予定と結果の詳細も選択できる。上部の重複した案内枠は置かず、日付と日報入口をタイムライン見出しに置く。予定表のタブから個人カレンダーへ移動できる。既存の予約・校正管理操作は持ち込まない。
- 部署一覧の人別行は予定バーの本数に応じてコンパクトに表示する。「予定あり」の曖昧な表示は省き、今日のタイムラインには現在時刻の縦線と時刻ラベルを表示して1分ごとに更新する。
- 部署一覧の簡易予定は個人カレンダーと同じ淡いピンク、件名は「案件名／作業名」で表示する。既存ジョブ予定も個人カレンダーの割当種別による色を使い、非公開の予定は灰色で伏せる。
- 部署一覧の下段とタイムラインのツールチップでは、保存元を「簡易予定／マイジョブ／依頼ジョブ／その他の予定」と分ける。マイジョブは `project_job_assignments.sender_id = events.user_id` で判定し、案件を選んだだけの簡易予定はマイジョブとは呼ばない。ツールチップは表示権限内の件名・時間・区分・結果を示す。
- 朝は大区分（進行管理／営業／組版・DTP・制作／校正／その他）と予定枠（午前／午後／終日／時間指定）だけで簡易予定を追加できる。カレンダーでドラッグした場合は15分刻みの選択時刻を初期値にする。本人の個人カレンダーは `schedule.department_work.mine` から簡易予定を再表示し、クリック編集・ドラッグ移動を受け付ける。既存ジョブ予定は `events` のまま表示し、簡易予定をそこへ複製しない。`personal_work_entries` は既存 JobBox の状態や作業量分析を更新しない。
- 簡単入力の案件候補は本人のチーム案件を優先し、簡易予定・過去のマイジョブで使った案件を続けて表示する。キーワード検索は同社の案件名・番号・取引先名を対象とし、完了案件も選べる。`show_on_department_schedule=false` の案件は本人が関係者の場合だけ候補・保存を許す。固定のお気に入り登録は設けず、最近使った案件を候補にする。
- 人別の埋まり具合は本人の勤務時間・日別休憩と予定時間の和集合から算出する。未登録は「不明」とし、仕事量や評価点とは見なさない。既存ジョブの校正イベントは `CalculatesEventTime::resolveJstCarbon()` で JST に変換する。
- 案件の `show_on_department_schedule` は初期値 true。false の案件と、行単位で private とした簡易予定は、関係者以外に件名・案件名・結果・数量を返さない。対象は新タブのみで、既存カレンダー・案件・日報の権限は変えない。公開前に稼働中の極秘案件を非公開に設定する。
- 日報の作成・編集では簡易予定と既存の本人ジョブ予定を個別に選択し、選択行の結果文を必須、数量・単位を任意で保存する。予定外の作業も追加できる。日報本文と作業行は同一トランザクションで保存し、日報専用欄には既存 `DiaryPolicy` が適用される。
- 作業種類は朝の簡易予定では任意、帰りに日報へ取り込む行では必須。`jimu`（事務）を大区分に加え、Clerk 権限者も兼務する組版・校正等へ区分を切り替えられる。事務は伝票チェック・書類作成・書類処理等、校正の修正は重・軽を選ぶ。簡易予定の作業時間はカレンダーの時間枠から計算し、既存ジョブ予定・予定外作業の実績時間は日報で入力する。作業実績の内訳は予定表の独立ページ `schedule.department_work.results` で日・月曜始まりの週・暦月を切り替える。日表示には結果未入力の予定を別欄で示し、実績合計へ加えない。分析 API は `schedule.department_work.analysis`、予定欄は部署限定の既存 API を使う。
- 情報出版に所属する Clerk の専用カレンダーには、共通の個人カレンダー（簡易作業入力）と部署の作業・実績へのリンクを表示する。大区分の初期値は本人が最近使った種類を優先し、履歴がない Clerk は事務を初期値にする。権限・担当で選択肢を制限しない。
- 関連実装: `app/Services/PersonalWorkSchedule.php`、`app/Services/DiaryWorkEntries.php`、`app/Support/PersonalWorkActivities.php`、`app/Http/Controllers/Schedule/DepartmentWorkController.php`、`app/Models/PersonalWorkEntry.php`、`resources/js/Components/Schedule/DepartmentWorkBoard.vue`。本番適用時は3件の migration を実行する。
- 試験分析では `WorkloadTrialAnalysis` が過去のジョブ予定・仕事の会議と簡易結果を時間で統合し、重複と昼休憩を除く。登録者・作成方法の2件の migration も必要。試験点の算式は `CONSOLIDATED_07_workload_and_handover.md` を参照。

---

## UTC / JST 混在ルール（重要）

`events.starts_at / ends_at` の保存形式が2種類ある:

| イベント種別 | 保存形式 | 読み出し時の注意 |
|---|---|---|
| 通常イベント（社内予定・外出・client-event・internal-event 等） | **JST 文字列**をそのまま保存 | `Carbon::parse($v)` で JST として扱える |
| 校正ジョブイベント（`job_type='proof'`） | **UTC 文字列**で保存 | そのまま parse すると 9 時間ずれる |

**必ずこのルールに従うこと:**

```php
// NG: proof イベントで 9 時間ずれる
$start = Carbon::parse($event->starts_at);

// OK: JST Carbon を返す
$event->load('projectJobAssignment:id,job_type');
$start = $this->resolveJstCarbon($event, 'starts_at');
```

**`CalculatesEventTime` Trait:**
- パス: `app/Http/Controllers/Concerns/CalculatesEventTime.php`
- メソッド: `resolveJstCarbon($event, 'starts_at')` — JST の Carbon を返す
- メソッド: `computeLunchMinutes($start, $end, $userId, $cache)` — 昼休憩分計算（UserMonthlyBreak → UserSetting → デフォルト 12:00–13:00 優先順）
- 使い方: コントローラで `use CalculatesEventTime;` を宣言し、`projectJobAssignment:id,job_type` を eager load してから呼ぶ

### 書き込み・期間フィルタ・Vue の日付（2026-08-13 追加）

読み出しだけルールを守っても意味がない。**書き込み側・期間フィルタ側も同じ規則に従うこと。**

| 場面 | 使うもの |
|---|---|
| **書き込み**（JST 日時 → 保存値） | `toEventStorageString($event, $jstDateTime)` |
| 読み出し（保存値 → JST） | `resolveJstCarbon($event, $field)` |
| 生値（退避した旧値など）→ JST | `rawToJstCarbon($event, $raw)` |
| 保存 TZ の判定 | `eventStorageTimezone($event)` |
| 重複時間の再計算 | `recalcInterruptionMinutes($event, $oldStart, $oldEnd)` |

- **書き込み**: `$event->start = $date.' '.$h.':'.$m` のような直接代入は proof で 9 時間ずれ、保存のたびに累積する。
  新規作成時は `project_job_assignment_id` をセットしてから変換すること（未保存モデルは `setRelation()` で渡す）
- **期間フィルタ**: DB の文字列比較だけで絞らない。**±9 時間のバッファで取得 → `resolveJstCarbon()` で JST 判定**。
  日境界は JST のまま持つこと（UTC 化して比較すると通常イベントの JST 15:00 以降が当日から消える）。
  模範実装は `CalendarEventsController::range()`
- **カレンダーのドラッグ**: `events` だけ更新するとジョブ修正ページで古い時刻が復元される。
  `project_job_assignments.start_time`（開始）も同期する。`desired_time`（＝自己割当では作業終了時刻、
  Coordinator 割当では締め切り時刻）の同期は**自己割当のときのみ**
- **Vue の日付**: 「今日」や日付移動は `toLocaleDateString('sv-SE')` を使う。
  ただし allDay の ±1 日計算（日付のみ入力で UTC 一貫）と、`+09:00` を明示した `toISOString()` は**正しいので変更しない**。
  一括置換は禁止、1 箇所ずつ用途を確認すること

詳細と NG/OK 例は `CLAUDE.md`「UTC / JST 混在ルール ④〜⑦」を参照。

---

## FullCalendar 注意点

- Vue の reactive Proxy をそのまま渡すと空になる等の問題が発生する → `structuredClone` などで plain オブジェクトを渡す
- `JobBox 側の "予定を編集" からカレンダーを開く際は URL に `?date=YYYY-MM-DD&user_id=...` を付与し、Calendar 側はそのパラメータを受け取って `gotoDate` を呼ぶ

---

## TimelineDiary コンポーネント（R5-14 / R5-16, 2026-05-23）

**パス:** `resources/js/Components/TimelineDiary.vue`

**Props:**
- `:date` — 表示日（YYYY-MM-DD）
- `:events` — イベント配列
- `:editable` — 編集可否（true でドラッグ/リサイズ有効）

**編集モード（`:editable="true"`）のイベント:**

| emit | 発火タイミング | ペイロード |
|------|-------------|-----------|
| `@update:events` | ドラッグ/リサイズ完了 | `{ id, start, end, date }` |
| `@open-edit` | イベントクリック | `{ id, date }` |
| `@open-create` | 空スペースクリック | `{ date, hour }` |

**`@update:events` の処理パターン:**

```js
async function onTimelineUpdate(payload) {
  await axios.put(`/events/${payload.id}/calendar`, {
    starts_at: payload.start,
    ends_at: payload.end,
  });
  await fetchDayEvents(payload.date);
}
```

**ルート:** `PUT /events/{event}/calendar` → `events.update_from_calendar`

**Edit.vue での日付変更対応（`watch`）:**

```js
watch(() => form.date, (newDate) => {
  if (newDate) fetchDayEvents(newDate);
});
```

**その他:**
- `ResizeObserver` でラッパー幅をリアクティブ取得。`minWidth` をピクセルで強制しない（水平スクロールが発生する）
- 夜勤モード: `defaultWorktype.start_time >= 16:00` の場合 `slotMaxTime: '30:00:00'`（翌6時）

---

## イベント種別（event_item_types）

| 種別 | job_type 相当 | 保存形式 |
|------|-------------|---------|
| 通常予定・外出等 | `internal-event` / `client-event` 等 | JST |
| 校正ジョブ | `proof` | UTC |

---

## JobBox / MyJobBox

- JobBox 側の完了ルート: `jobbox.assignments.complete`
- MyJobBox 側の完了ルート: `myjobbox.assignments.complete`
- `JobBox 側データソース: `job_assignment_messages` JOIN
- MyJobBox データソース: `project_job_assignments` の `selfAssigned()` スコープ

---

## 校正予約（2026-06-30）

- Coordinator 案件一覧から案件に紐づく校正予約を送信する。
- 通常校正依頼の `proof_requests` とは分離し、`proof_reservations` を正規データとする。
- 依頼予定と締め切りは、それぞれ `datetime` または自由記述 `text` を選択できる。
- 日時入力はJSTとして受け取り、`requested_at` / `deadline_at` にUTC文字列で保存する。
- 両方が日時入力かつ依頼予定 < 締め切りの場合のみ、予約詳細から校正カレンダーへ登録できる。
- 登録状態は `calendar_registered_at` で管理する。`proof_schedules` は校正員別の日次作業枠なので予約期間の保存には流用しない。
- 校正カレンダー月表示では、依頼予定日を開始、締め切り日を終了とする1本の期間ストリップを表示する。
- FullCalendar の all-day `end` は排他的なので、締め切り日の翌日を描画用 `end` として渡す。
- 期間ストリップをクリックすると `proof_coordinator.reservations.show` へ遷移する。
- 予約モーダルの「送信予約一覧」は、同じ案件に送信済みの予約を依頼予定・締め切り・カレンダー登録状態とともに表示する。
- 同じ案件で「タイトルが一致」または「依頼予定日と締め切り日の両方が一致（時間は無視）」する予約を重複候補とする。
- 重複確認は事前確認APIだけでなく保存処理でも再判定し、`duplicate_confirmed=true` の場合のみ重複候補を送信できる。
- 予約ステータスは `reserved`（予約受付）/ `in_progress`（校正中）/ `completed`（完了）/ `deleted`（削除）の4状態。
- 削除は履歴を保つ論理状態であり、レコードを物理削除しない。削除状態の予約は校正カレンダーに表示しない。
- 予約一覧の「完了を表示しない」はデフォルトONで、`sbw_proof_reservations_hide_completed` としてlocalStorageへ保存する。
- proof-admin の受信箱・校正予約一覧・ジョブ管理は、`created_at` を基準に新しい順/古い順を切り替える。指定がない場合は新しい順（`desc`）。
- 検索・年月・タブ切替時も `sort_order` をクエリへ引き継ぐ。

---

## 管理シートテンプレート（2026-06-30）

- 管理シート用テンプレートの正規データは `progress_templates` の `sheet_type = management`
- 専用画面: `/coordinator/management-templates`
- Controller: `Coordinator/ManagementTemplateController`
- Inertiaページ: `Coordinator/ManagementTemplates/Index.vue`, `Edit.vue`
- 一覧には共有テンプレート、またはログインユーザー自身が作成したテンプレートを表示
- 編集・削除は作成者、Admin、SuperAdminのみ可能
- 管理シート作成時は `column_config` をコピーし、`workflow_sheets.template_id` には保存しない
  - `workflow_sheets.template_id` は旧 `workflow_templates` への外部キーであり、`progress_templates.id` を保存してはいけない
- 旧 `WorkflowTemplate` / `workflow_templates` は `stage_config` 形式の別系統。互換性維持のため残すが、新しい管理シートテンプレート機能には接続しない
- `sheet_type = null` または `progress` は進行管理表側、`management` は管理シート側として一覧と作成モーダルを分離する

---

## CSV インポート（NormalizesCsvEncoding Trait）

**全 CSV インポートは Shift-JIS + CRLF + BOM に対応すること。**

- Trait パス: `app/Http/Controllers/Concerns/NormalizesCsvEncoding.php`
- 各コントローラで `use NormalizesCsvEncoding;` を宣言

| メソッド | 用途 |
|---------|------|
| `$this->normalizeCsvStoredFile($storagePath)` | store() で保存済みファイルを正規化（上書き） |
| `$this->normalizeCsvToTemp($file)` | UploadedFile を正規化した一時ファイルパスを返す（使用後 `@unlink` 必須） |
| `$this->normalizeCsvContent($raw)` | バイト列を UTF-8 文字列として返す |

---

## EventController

- `EventController::complete()`: `project_job_assignments.completed` のみ更新
- `EventController::store()`: `job_id` の有無に応じて `ProjectJobAssignment` を参照し、必要なら通知 Message を作成
- パンくず: `Events/Show.vue` の `events.project_job_assignment_id` は `project_job_assignments` への FK

---

## ルーティング

SPA ルートは必ず `routes/web.php` に置く（`routes/api.php` は StartSession が通らず SPA 認証が失敗する）。


## Clerkの連続カレンダー・祝日設定（2026-09-15）

- 対象は`clerk.calendar`。日曜〜土曜の7列で年間＋前後5週を連続表示する。FullCalendar基本dayGridのカスタム`visibleRange`を使い、`duration`は指定しない。
- FullCalendar内部の日付スクロール領域を5週分の実測高に合わせる。曜日見出し固定、年月選択で月の1日を含む日曜の週へ移動。初期値はJSTの現在年月。
- 土曜は青、日曜と登録祝日は赤。土曜祝日は赤優先。今日の枠は休日背景と共存。祝日名を日付セル内に表示する。
- `clerkCalendarDates.js`は日付キーの演算をUTCで一貫処理し、JSTの今日のみ明示的にAsia/Tokyoから得る。既存予定の保存形式・終日endの排他変換は変更しない。
- 別タブの週間プランナーはISO週のまま。`clerk_week_posts`のyear/weekを変更しない。
- 「カレンダー設定」→「祝日設定」で年別に追加・修正・削除。会社ごとの共有設定。将来の締め日設定はこのメニューに追加できるが未実装。
- `clerk_calendar_holidays`: company_id/date/name、会社＋日付を一意制約。dateは`date:Y-m-d`キャスト。
- `clerk_calendar_years`: company_id/year/initialized_at、会社＋年を一意制約。初回参照時のみ初期データ投入、トランザクションと行ロックで制御。全件削除しても初期化済みマーカーを残す。
- 初期データは`resources/data/clerk_holidays.json`（内閣府、2026-09-15確認、2026/2027年）。2026/9/22と振替休日を含む。他の年は空の一覧から手動追加。既に初期化した年へJSON更新を自動で上書きしない。
- 翌年の運用: 設定画面の年を選び、内閣府で確定日を確認して登録する。春分・秋分を推測しない。変更は会社のカレンダーへ反映される。共通useJapaneseHolidaysは別機能のため変更なし。
- 権限は既存Clerkミドルウェアと同一。SuperAdminは会社コンテキスト優先、未選択時は所属会社。別会社の祝日IDは404。
- 必要なmigration: `2026_09_15_100000_create_clerk_calendar_holidays_tables.php`。本番配備時にも適用が必要。
- テスト: `node --test tests/js/clerkCalendarDates.test.mjs`、`docker compose exec laravel php vendor/bin/phpunit tests/Unit/ClerkCalendarHolidayTest.php`。PHPテストは専用インメモリSQLiteを使用し、Tests\TestCaseとRefreshDatabaseを使用しない。
- 予定APIは選択年の年間ストリップ範囲（前年側35日、翌年側35日を含む）だけを返す。年変更時に再取得し、範囲をまたぐ予定は開始・終了の重なりで含める。CSV出力は全期間のまま。検索には`clerk_events(company_id, starts_at)`の複合インデックスを使う。

## Clerk予定の複製・予定日設定（2026-09-15）

- 予定詳細の「複製」はタイトル・内容・色・終日属性・期間を新規フォームへコピーする。開始日変更時は期間日数を保って終了日も移動する。元の完了状態と予定日設定の紐づけはコピーしない。
- カレンダー設定の「予定日設定」で、`monthly_day`（毎月1〜31日）、`month_end`、`monthly_weekday`（第1〜5または最終＋曜日）、`custom_dates`を会社単位で登録する。
- 第N曜日はその月のN回目。存在しない31日・第5曜日は生成しない。月末は実際の末日。休日による前後営業日への移動は行わない。
- 定義は`clerk_schedule_rules`、各生成回は`clerk_schedule_occurrences`。`rule_id + nominal_date`を一意にして、画面表示と日次処理が並行しても二重登録しない。
- 予定は通常の`clerk_events`として作成するため、連続カレンダー・週間プランナー・一覧・CSVへ表示される。
- 自動予定を個別更新・ドラッグ・完了すると生成回を`customized`、個別削除すると`cancelled`にする。ルール変更や再生成で戻さない。
- ルール変更・停止・削除は、今日以降の未完了・`generated`の予定だけに反映。過去、完了済み、個別変更済みは保持する。条件から外れた自動予定は`retired`とし、停止解除や再設定で必要なら将来分だけ再生成する。
- 保存時に今後18か月を生成。カレンダー表示年の読込時はその年の前後余白も補充する。`clerk:generate-schedules`をAsia/Tokyoの毎日0:15、`withoutOverlapping`で実行する。
- 作成者ユーザーが削除された場合はルール・生成履歴・そのユーザーのClerk予定をDBのcascadeで整理する。ルールの通常削除はSoftDeletesを使い、残した過去予定の由来を保持する。
- 必須migration: `2026_09_15_110000_create_clerk_schedule_rules_tables.php`と`2026_09_15_110001_update_clerk_schedule_rule_creator_delete.php`。
- 自動テストは専用インメモリSQLiteを使う`ClerkScheduleGeneratorTest`と、複製・日付の`tests/js/clerkCalendarDates.test.mjs`。

## Clerkカレンダー・リマインダー（2026-09-15）

- `clerk_calendar_reminders`へ会社単位で内容、表示開始日、表示終了日、有効状態を保存する。日付キャストは`date:Y-m-d`。
- 表示条件は有効かつ`starts_on <= JSTの今日 <= ends_on`。開始日・終了日の両端を含み、サーバーの`Asia/Tokyo`で判定する。
- 一般ユーザーの`/calendar`で、ログインユーザーの所属会社に一致するリマインダーだけを表示する。SuperAdminがUser画面を確認する場合は、選択中の会社コンテキストを優先する。Clerk会社共有カレンダーには表示しない。
- `color_key`はClerk予定と同じ11色。選択色の濃い枠線と薄い背景を持つ一段の帯として表示する。期間外・停止中・削除済みは表示しない。
- 通知受信、`events`、`clerk_events`へレコードを作らないため、通知ランプ、既読状態、個人予定、会社予定の件数に影響しない。
- Clerk管理ルートは`clerk.reminders.*`。別会社のレコードを編集・停止・削除しようとした場合は404。
- 必須migrationは`2026_09_15_120000_create_clerk_calendar_reminders_table.php`と`2026_09_15_120001_add_color_key_to_clerk_calendar_reminders_table.php`。テストは専用インメモリSQLiteの`ClerkCalendarReminderTest`を使う。

## 個人カレンダーの簡易作業予定（2026-10-09）

- 情報出版の利用者は個人カレンダーのドラッグ操作から「簡単な作業予定を追加」を選び、大区分・予定枠・任意の作業種類・案件を登録する。簡易予定は `personal_work_entries` に保存し、個人カレンダー専用 API で重ねて表示する。`events` には複製しない。
- 簡易予定はドラッグ操作の選択肢と個人カレンダー上で淡いピンクを使い、会議・マイジョブ・進行表ジョブ・日報の色と区別する。カレンダー上の背景は `#fbcfe8`、文字は `#831843`、枠線は `#f472b6`。大区分は予定名で識別する。月表示では先に簡易予定を表示し、表示枠からあふれる予定は残件数を示す。
- 午前・午後・終日の登録者に勤務形態がない場合、カレンダー表示だけ 09:00–18:00、休憩 12:00–13:00 を仮枠に使う。実績時間の初期値は作らない。時間指定の予定は指定時刻を使う。
- 簡易予定を保存・削除したら対象日に移動して再取得する。他の予定 API が失敗しても簡易予定の取得を反映し、簡易予定 API 自体が失敗した場合は画面にエラーを表示する。
- 簡易予定の編集画面で数量・単位・短い結果を後から追記できる。朝は空欄で保存でき、数量だけ先に入れることもできる。実績時間欄は設けず、ドラッグ指定した時刻の差を作業時間とする。午前・午後・終日は本人の勤務時間と昼休憩から計算し、勤務形態がない場合は表示上の仮枠を使う。結果を記録する場合は作業種類を必須とする。日報作成時に完成した簡易予定は結果を文章として選択・保存し、空欄がある予定は日報側で本人が入力できる。完成済みの実績はカレンダーの元記録で修正する。日報から外しても元記録の結果は保持する。部署の作業分析は日報の取り込み前でも完成した実績を集計する。
- 日報作成・編集では試用対象者に、勤務枠内の認定作業時間と30分以上の未記録時間、結果や作業種類が未完成の簡易予定件数を非強制で案内する。集計は試験分析と同じ重複・昼休憩除外を使う。勤務形態未設定ならA日程の7時間30分と記録時間合計による概算であることを明示する。案内から日付を保って個人カレンダーの日表示へ移動できる。日報の保存可否は変えない。
- 実績欄は「作業名（任意）」の直下。単位は本人の直近の入力履歴を優先し、「ページ・ファイル・件・枚・点・冊」も入力候補として表示する。候補ボタンはワンクリックで選べ、自由入力も許可する。集計は単位文字列ごとに分け、ページとファイルを合算しない。保存時は前後の空白だけ取り除く。
- 個人カレンダーの簡易予定タイトルは、案件と作業名が両方あれば「案件名／作業名」と表示する。片方だけならその名前を表示し、両方なければ大区分名に戻す。作業名の入力欄には本人の簡易予定と自己登録マイジョブの過去の作業名を最大15件取得し、フォーカス時に候補を表示する。同じ案件で使った作業名を上位に並べ、自由入力も許可する。

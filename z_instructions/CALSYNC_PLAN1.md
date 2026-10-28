# CALSYNC_PLAN1.md — チームルーム／案件カレンダーを Clerk カレンダー相当にする（詳細仕様）

作成日: 2026-10-28
進捗: CALSYNC_MANAGER1.md ／ 継続プロンプト: CALSYNC1_PROMPT.md

## 1. 目的
Clerk のカレンダー（`/clerk/calendar`）と同等の機能・見た目を、次の 2 つに反映する。
- **チームルームのスケジュール**（`/team-rooms/{team}?tab=schedule`。`TeamScheduleCalendar.vue`）
- **案件のスケジュール**（`/coordinator/project_jobs/{id}?tab=schedule`。`ProjectCalendar.vue`）

ユーザーが挙げた機能: 予定の作成、色設定、月のスクロールがストリップ、祝日の表示。

## 2. 現状の差（調査済み）
| | Clerk（お手本） | チームルーム | 案件 |
|---|---|---|---|
| 月のスクロール | ストリップ（`useClerkCalendarStrip.js`） | 前へ・次へ・今日 | 同左 |
| 祝日 | 会社の休日カレンダー | なし | なし |
| 色設定 | あり（`ClerkCalendarColorPanel`） | なし（固定 1 色） | なし（期限・完了の状態色） |
| 予定の作成・編集 | あり | あり | あり |
| ドラッグ移動・リサイズ・CSV・週プランナー・パネル編集 | あり | **既にあり** | **既にあり** |
| 完了チェック・複製 | あり | **なし** | あり |

## 3. 確定した仕様（ユーザーと 1 問ずつ確認。2026-10-28）
1. **色設定の単位 = A**: チームルームごと、案件ごとに**独立**した色設定（色ごとの名前・並び順）。会社共通にはしない。新しい表が 2 つ。
2. **案件の予定の色 = A**: **状態色を優先**（完了＝緑／期限超過＝赤／期限が近い＝黄）。通常のとき（今の青）だけ、予定に選んだ「種類の色」で塗る。
3. **チームルームに完了チェックと複製を足す = A**: Clerk と同等にする。
4. **色設定を変更できる人 = A**: チームルームは**チームのメンバー全員**、案件は**その案件を編集できる人**（コーディネーター・Leader・管理者）。閲覧のみの人は変更できない。
5. **完了チェックを押せる人 = A**: **予定の作成者（と SuperAdmin）だけ**（今の編集権限と同じ）。**複製はメンバー全員**（新しい予定を作るだけ。複製した人が作成者になる）。

## 4. 私が決めた既定（変えたければ指摘してもらう）
- **祝日**: そのチーム／案件の**会社**の休日カレンダー（Clerk の `ClerkCalendarHoliday`）を、読み取りだけで表示する。
- **色設定の「履歴」**: 色設定の表に、**最後に変更した人と日時**（`updated_by`・`updated_at`）を持たせ、色設定の窓に表示する（完全な変更履歴ではない）。
- **色の種類**: Clerk と同じ 11 色（indigo, blue, cyan, teal, green, yellow, orange, red, pink, purple, gray）。チームルームの予定の初期色は indigo（今の色）。
- **案件の適用範囲**: 案件詳細のスケジュールタブ（`Coordinator/ProjectJobs/Show.vue`、閲覧用の `User/ProjectJobs/Show.vue`）に適用する。別画面 `Coordinator/ProjectSchedules/Calendar.vue` は**変更しない**（`ProjectCalendar` は新しい prop で、opt-in にする）。
- **Clerk のカレンダーの動きは変えない**: ストリップ部品は、祝日の取得先を、引数で差し替えられるようにするだけ（既定は今の Clerk の経路）。色設定の窓は、Clerk のものを直接触らず、汎用の新部品を作る。
- 案件の「色の種類」は、既存の `project_schedules.color`（旧・16 進色）とは別の新しい列 `color_key` に持つ。`color_key` が空のときは、今の青（通常の状態色）のまま。

## 5. DB 設計（migration。本番で実行が必要）
**外部キー名は 64 文字以内の短い名前を明示する**（TRAINS の migration で長すぎて失敗した教訓）。
- `team_calendar_colors`: `id, team_id(FK, 連動削除), color_key(20), label(20, null), sort_order, updated_by(null), timestamps`、一意 `(team_id, color_key)`
- `project_calendar_colors`: `id, project_job_id(FK, 連動削除), color_key(20), label(20, null), sort_order, updated_by(null), timestamps`、一意 `(project_job_id, color_key)`
- `team_events`: `color_key string(20) default 'indigo'`、`completed_at timestamp null`
- `project_schedules`: `color_key string(20) null`
- 既存データは、そのまま動く（チームの予定は indigo、案件の予定は色なし）。

## 6. API・ルート（概要。実装時に既存のルート構成に合わせて確定）
- **祝日**: 会社の祝日を返す共通処理を、`ClerkCalendarHolidays`（サービス）側に出す。`data()` と同じ内容（年の前後を含む）。
  - チームルーム: `team-rooms/{team}/calendar/holidays`（メンバーのみ。会社は `team.company_id`）
  - 案件: 案件詳細の経路（コーディネーター用・ユーザー用）に、同様の祝日の取得を足す（会社は `project_job.company_id`）
- **色設定**: 一覧（無ければ 11 色を作る）／ラベル更新／並び替え
  - チームルーム: `team-rooms/{team}/calendar/colors`（メンバー全員が読み書き）
  - 案件: 案件ごとの同様の経路（読み取りは閲覧者も可、書き込みは案件を編集できる人）
- **チームの予定**: `color_key` を保存・返す。`PATCH team-rooms/{team}/events/{event}/complete`（作成者・SuperAdmin）。複製は画面側で、新規作成（store）を呼ぶ。
- **案件の予定**: 作成・更新の API で `color_key` を受け取り、一覧にも返す。

## 7. 画面の変更
- `useClerkCalendarStrip.js`: 祝日の取得先を、引数（URL を返す関数）で差し替えられるようにする。**既定は今の Clerk の経路**。
- `Components/Calendar/ScheduleColorPanel.vue`（新規）: 汎用の色設定の窓（取得・更新・並び替えの URL と、変更できるかを props で受ける）。「最後に変更した人・日時」を出す。
- `TeamRoom/TeamScheduleCalendar.vue`: `ClerkScheduleCalendar.vue` に合わせて、ストリップ・祝日・色設定・色の選択・完了チェック・複製を足す。
- `ProjectCalendar.vue`: 新しい prop（例 `stripStyle`）が真のときだけ、ストリップ・祝日・色設定・色の選択を有効にする。状態色が優先、通常のときだけ種類色。
- `Coordinator/ProjectJobs/Show.vue`・`User/ProjectJobs/Show.vue`: 新しい prop を渡す。

## 8. フェーズ
- **P1 共通**: migration、ストリップ部品の引数化、祝日の共通処理、汎用の色設定の窓、色の定数
- **P2 チームルーム**: API（色・祝日・完了）、`TeamScheduleCalendar.vue` の全面反映、テスト
- **P3 案件**: API（色・祝日・`color_key`）、`ProjectCalendar.vue` の opt-in 反映、テスト
- **P4 仕上げ**: lint・テスト全体、ChangelogSeeder・CONSOLIDATED_05 の更新、デプロイ（migration あり）

## 9. テスト方針
- PHP: 色設定の権限（メンバー全員・案件編集者のみ・閲覧のみは拒否）、色の初期作成、`color_key` の保存、完了の権限（作成者・SuperAdmin のみ）、祝日が会社で分かれること。
- JS: ストリップの日付計算の既存テスト（`tests/js`）を壊さないこと。
- 画面の確認はユーザーがブラウザで行う（`npm run build` まで）。

## 10. 変更ファイル一覧（見込み）
- 新規: migration（2〜3 本）、`TeamCalendarColor`・`ProjectCalendarColor` モデル、色設定のコントローラー（チーム・案件）、`ScheduleColorPanel.vue`、テスト
- 変更: `TeamEventController`、`TeamEvent`、`ProjectSchedulesController`（作成・更新）と案件の予定の返却、`ClerkCalendarHolidays`（祝日の共通処理）、`ClerkCalendarHolidayController`（`data` を共通処理に）、`useClerkCalendarStrip.js`、`TeamScheduleCalendar.vue`、`ProjectCalendar.vue`、案件詳細ページ 2 つ、`routes/web.php`
- 文書: `CONSOLIDATED_05_calendar_and_jobbox.md`、`ChangelogSeeder`

## 11. 注意（過去の教訓）
- migration の外部キー名は短く明示する。テストは 1 つずつ実行する（同時実行はテスト用 DB を壊す）。
- 日付は JST。FullCalendar の allDay の終了日は排他的（+1 日）で、既存の扱いを変えない。
- ストリップ部品は、Clerk のカレンダーが使っているので、既存の動きを変えないこと。
- 大きなファイル（`ProjectCalendar.vue` 約 2200 行）は、opt-in の追加だけにして、既存の動きは触らない。

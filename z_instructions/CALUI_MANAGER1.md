# CALUI_MANAGER1.md — Calendar UI リフレッシュ 進捗管理

## ステータス

開始日: 2026-06-21  
担当: Claude Code  
フェーズ数: 4

---

## 進捗テーブル

| Phase | タスク | ステータス | 備考 |
|---|---|---|---|
| **Phase 1** | useCalendarCore.js 作成 | 未着手 | |
| Phase 1 | CalendarShell.vue 作成 | 未着手 | |
| Phase 1 | ScheduleCalendar → CalendarShell 対応 | 未着手 | |
| Phase 1 | Schedule ページ動作確認 | 未着手 | |
| **Phase 2** | UserCalendar.vue 作成 | 未着手 | |
| Phase 2 | API-1 (schedule.events.range) 呼び出し | 未着手 | |
| Phase 2 | CalendarEventsController 作成 | 未着手 | |
| Phase 2 | API-2 (calendar.events.range) 呼び出し | 未着手 | |
| Phase 2 | MonthView/WeekView/DayView イベント描画 | 未着手 | |
| Phase 2 | EventModal 組み込み | 未着手 | |
| Phase 2 | 既存ボタン（マイジョブ・日報等）移植 | 未着手 | |
| **Phase 3** | Pages/Calendar/Index.vue 更新 | 未着手 | |
| Phase 3 | CalendarController props 追加 | 未着手 | |
| Phase 3 | routes/web.php 追加 | 未着手 | |
| Phase 3 | npm run build | 未着手 | |
| **Phase 4** | スタイル・レイアウト確認 | 未着手 | |
| Phase 4 | 日程設定・休憩設定確認 | 未着手 | |
| Phase 4 | 本番（さくら）既存イベント確認 | 未着手 | |

---

## 作業ログ

### 2026-06-21
- 調査完了
  - Calendar と Schedule は同じ `events` テーブルを使用
  - Calendar で作成した 案件打合せ・外出/社内予定 は `is_company_event=NULL` → Schedule に表示されない（既存バグ）
  - Schedule で作成したイベントは Calendar に表示される（一方向のみ機能中）
- PLAN / MANAGER / PROMPT 作成完了
- ユーザー確認待ち

---

## チェックポイント（Phase 完了時に確認）

### Phase 1 完了チェック
- [ ] Schedule ページ（/schedule）が Phase 1 前と同じ見た目・動作か
- [ ] CalendarShell のスロットが正常に描画されるか
- [ ] npm run build 成功

### Phase 2 完了チェック
- [ ] UserCalendar で会社イベントが表示されるか（API-1）
- [ ] UserCalendar でジョブイベントが表示されるか（API-2）
- [ ] EventModal から予定を作成して Calendar に反映されるか
- [ ] EventModal から作成した予定が Schedule にも出るか（is_company_event=true 確認）
- [ ] npm run build 成功

### Phase 3 完了チェック
- [ ] /calendar でユーザーカレンダーが表示されるか
- [ ] CalendarController の Inertia::render ターゲットが正しいか確認
- [ ] npm run build 成功

### Phase 4 完了チェック
- [ ] さくら本番で既存イベントが消えていないか
- [ ] MiniCalendar が表示されているか
- [ ] 月/週/日 ビュー切り替えが動くか
- [ ] 日程設定・休憩設定モーダルが動くか

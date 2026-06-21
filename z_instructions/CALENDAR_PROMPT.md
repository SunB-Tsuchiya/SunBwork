# カレンダー機能 引継ぎプロンプト

> 作成: 2026-06-21  
> 直近コミット: `c8dd5abf6 feat: 予定表（SCHED）機能を追加・Codex R3 バグ修正適用`  
> 以降の変更はすべて **未コミット**（ビルド済み）

---

## このプロジェクトについて

Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS の社内業務管理 SPA。
**CLAUDE.md** を必ず先に読むこと（作業ルール・UTC/JST 混在ルール・さくら本番ルールなど）。

---

## カレンダー機能の全体像

FullCalendar を廃止し、**ゼロから書いたカスタムカレンダー**に置き換えた。

| ページ | URL | Vue ページ | 用途 |
|---|---|---|---|
| ユーザーカレンダー | `/calendar` | `Pages/Calendar.vue` | ログインユーザー個人の予定・日報・ジョブ確認 |
| 予定表（スケジュール） | `/schedule` | `Pages/Schedule/Index.vue` | 複数ユーザーのオーバーレイ・会議室予約管理 |

---

## ファイル構成（カレンダー関連）

### 新規作成（未トラック・コミット未）

```
resources/js/Components/Calendar/          ← ユーザーカレンダー専用
  ActionSheet.vue         クリック/ドラッグ後に出るアクション選択シート
  UserCalendar.vue        カレンダーのルートコンポーネント（月/週/日ビュー切替）
  UserDayView.vue         日ビュー（左タイムライン + 右サマリーパネル 6:4）
  AttendeeSelector.vue    参加者選択UI
  CalendarShell.vue       ← Scheduleと共用！ミニカレンダー付き2ペインシェル
  DayView.vue             ← Schedule専用日ビュー（複数カラム構成）
  EventDetailModal.vue    予定詳細モーダル
  EventModal.vue          予定作成/編集モーダル
  MiniCalendar.vue        サイドバーのミニカレンダー
  MonthView.vue           月ビュー
  NotificationPanel.vue   通知パネル
  OverlayPanel.vue        オーバーレイユーザー管理パネル
  RoomReservationModal.vue 会議室予約モーダル
  ScheduleCalendar.vue    Scheduleページのルートコンポーネント
  WeekView.vue            週ビュー（ScheduleとCalendarの両方が使用）
  useCalendarCore.js      イベント取得・CRUD の共通ロジック
```

**重要:** `Calendar/` 配下と `Schedule/CalendarShell.vue` は git 未追跡（`??`）。
`Schedule/WeekView.vue`・`DayView.vue`・`MonthView.vue`・`ScheduleCalendar.vue` は変更済み（`M`）。

### 変更済みバックエンドファイル（未コミット）

| ファイル | 変更内容 |
|---|---|
| `app/Http/Controllers/CalendarController.php` | ユーザーカレンダー用に全面書き直し。worktypes・dailyWorktypes・dailyBreaks・defaultBreak・defaultWorktype を Inertia に渡す |
| `app/Http/Controllers/Schedule/ScheduleController.php` | worktypes・dailyWorktypes・defaultWorktype を追加で渡す |
| `resources/js/Pages/Calendar.vue` | AppLayout + UserCalendar に切り替え。rooms があるときだけ会議室テスト中バナーを表示 |
| `resources/js/Pages/Schedule/Index.vue` | props に worktypes/dailyWorktypes/defaultWorktype を追加 |
| `resources/js/Composables/useEventTypeColors.js` | カスタムカラー対応 |
| `routes/web.php` | カレンダー関連ルートの整理（詳細は `routes/web.php` 参照） |

---

## 実装済みの主要機能

### CalendarShell（両カレンダー共通シェル）
- `provide/inject` で `calendarScrollEl` ref を子コンポーネントに渡す
  - `WeekView` と `DayView` がこれを inject してスクロール位置を制御する
- `md` 未満でミニカレンダーサイドバーを非表示（レスポンシブ）
- `#sidebar` / `#toolbar-extra` スロットで各カレンダーが追加UI を差し込む

### WeekView（週ビュー）
- ドラッグ選択で予定作成、`clickToCreate` prop が true のとき1クリックでも `create` emit
- CalendarShell の `scrollEl` を inject → 現在時刻にスクロール
- `nowMin` reactive ref + 60秒タイマーで現在時刻ラインを更新（赤線）
- スクロール計算：`getBoundingClientRect` 廃止 → 定数から直接算出
  - `scrollTop = 12(CalendarShell p-3) + HEADER_H(40) + WORKTYPE_H(22) + targetOffset - 160`

### DayView（Scheduleの日ビュー、多カラム）
- 自分 / 会議室 / オーバーレイユーザーの複数カラム
- カラムのドラッグ並び替え・折りたたみ対応（localStorage 永続化）
- 現在時刻ライン（nowTop が null 返しで範囲外ガード済み）
- inject した `scrollEl` でスクロール制御
  - `scrollTop = 12(CalendarShell p-3) + HEADER_H(48) + targetOffset - 160`

### UserDayView（Calendarの日ビュー）
- 左タイムライン + 右サマリーパネル（6:4比率）
- 右パネル: 勤務形態・休憩・日報ステータス・今日のジョブ一覧
- 自前の `timelineRef` で左タイムラインのみスクロール（CalendarShell inject は使わない）
- クリック-ドラッグバグ修正済み: `origStart/origEnd` を保持、変化がなければ `update` emit しない
- 休憩オーバーレイ: `e <= s` のガード済み

### ActionSheet
- 4つのアクション（予定追加・マイジョブ・進行表ジョブ・日報）
- パステルカラー（emerald/indigo/violet/orange の -50/-100 系）

---

## Codex レビューで指摘された未修正バグ（優先度順）

### 1. WeekView / DayView のクリック-ドラッグ update バグ（pre-existing）
UserDayView では修正済みだが、`WeekView.vue` と `Schedule/DayView.vue` のイベント
mousedown → mouseup で startMin/endMin が変化していない場合も `update` emit してしまう。

**修正方針:**
```js
// onEventMousedown 内
const origStart = localMin(ev.starts_at);
const origEnd   = localMin(ev.ends_at);
dragging.value = { ..., origStart, origEnd };

// onMouseup 内
if (startMin !== origStart || endMin !== origEnd) {
    emit('update', ...);
}
```

### 2. UserCalendar のレースコンディション
`loadEvents()` に中断・順序制御がないため、高速ナビゲーションで古いレスポンスが上書きする可能性。
AbortController または requestId カウンタで対処する。

### 3. DayView コラム順序キーの props.date 非追従
`STORAGE_KEY_ORDER` が初回 `props.date` で固定されている。
日付変更時に読み書きキーがずれる。`computed` 化するか `watch(props.date, ...)` で更新する。

### 4. UserCalendar localDailyWorktypes / localDailyBreaks が props 更新に非追従
`ref()` でコピーしているため Inertia の props 更新が伝わらない。
`watch(() => props.dailyWorktypes, ...)` などで同期する。

### 5. ダブルクリックで予定が2件作成される
`clickToCreate` の mouseup が2回 fire して create を2回 emit する。
`Date.now()` で直前の emit から一定時間内は無視するデバウンスを入れる。

---

## 現在未解決の不具合（ユーザー報告）

- **スクロール位置がまだ正確でない可能性**: 現在時刻へのスクロールを `getBoundingClientRect` から定数計算に切り替えたが未検証。ビルドは通っているので動作確認が必要。
- **週ビューの赤い現在時刻ライン**: この会話内で実装完了。動作確認をユーザーにしてもらう。

---

## 注意事項・落とし穴

1. **CalendarShell は `resources/js/Components/Schedule/` に置いてある**
   Calendar/ 配下ではない。ScheduleCalendar も UserCalendar も同じ CalendarShell を使う。

2. **CSS overflow の罠**
   `overflow-x: auto` があると CSS 仕様で `overflow-y` も `auto` になる。
   DayView のルートが `overflow-x-auto` を持つため、以前は DOM 探索が DayView で止まっていた。
   `provide/inject` でスクロールコンテナを渡すことで解決済み。

3. **日付比較は `toLocaleDateString('sv-SE')` または明示的な year/month/day 連結で**
   `new Date().toISOString().slice(0,10)` は UTC になるので JST 環境では日付がずれる。

4. **ビルドコマンド**: `npm run build`（プロジェクトルートで）
   Vue/JS を変更したら毎回実行すること。

5. **本番デプロイ**: `z_instructions/DEPLOY_SAKURA.md` を参照。
   `VITE_APP_BASE_PATH=/members` の切り替えを忘れないこと。

6. **会議室予約はテスト機能**: ページ上部にバナーを表示している。将来正式化する予定。

---

## 次にやること（優先度順）

1. スクロール・赤い現在時刻ラインの動作確認（ユーザーによる目視確認）
2. Codex 指摘バグ #1（WeekView/DayView クリック-ドラッグ）の修正
3. コミット（未コミットの変更が大量にある）
4. Codex 指摘バグ #2〜#5 の対応（優先度に応じて）

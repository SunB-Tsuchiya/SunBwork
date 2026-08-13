# TZFIX_PLAN1 — UTC / JST 混在の残存箇所 総対応

作成日: 2026-08-13
対象ブランチ: main

---

## 1. 背景

2026-08-13 に「校正ジョブの予定が保存のたびに 9 時間ずつ後ろにずれる」不具合を修正した（Changelog: `proof-event-timezone-fix-1`）。
原因は **読み出し側だけが UTC/JST 混在ルールを実装しており、書き込み側が常に JST を書いていた**こと。

その後 codex による全体点検を実施し、**同種の残存箇所**が見つかった。本 PLAN はその総対応を行う。

### 前提となる仕様（CLAUDE.md「UTC / JST 混在ルール」参照）

- アプリは JST（Asia/Tokyo）で稼働
- `events.starts_at / ends_at` は保存形式が2種類混在
  - 校正ジョブ（`project_job_assignments.job_type = 'proof'`）→ **UTC 文字列**
  - それ以外の通常イベント → **JST 文字列**
- 吸収は `app/Http/Controllers/Concerns/CalculatesEventTime.php` のヘルパーで行う
  - 読み: `resolveJstCarbon()` / `rawToJstCarbon()`
  - 書き: `toEventStorageString()`
  - 保存TZ判定: `eventStorageTimezone()`
  - 重複時間: `recalcInterruptionMinutes()`

### 本 PLAN 着手時点で **対応済み**（再修正不要）

| 箇所 | 内容 |
|---|---|
| `EventController::store / update / update_from_calendar` | `toEventStorageString()` 経由に修正済 |
| `User\ProjectJobAssignmentController::update` | 同上 + 重複計算をトレイトに委譲 |
| `JobBoxController::storeSchedule` | 同上 + `scheduled_at` を JST に修正 |
| `CalendarEventsController::range` | ±9h バッファ取得 + `resolveJstCarbon()` 再フィルタ（**模範実装**） |
| 本番データ | events 4件 / proof_schedules 3件を -9h 補正済 |

---

## 2. 対応スコープ

### フェーズ1（最優先）: events の範囲フィルタが混在に対応していない

**なぜ急ぐか:** 現存する proof イベント 11 件はたまたま UTC 日付と JST 日付が一致していて表に出ていないだけ。
**8 時シフトの勤務者がいる**ため、JST 09:00 より前に始まる校正予定が入った時点で「カレンダーに出ない」「工数集計から漏れる」が発生する。

| # | ファイル | 該当 | 症状 |
|---|---|---|---|
| 1-1 | `app/Http/Controllers/EventController.php` | `index()` の日付絞り込み（`whereBetween('starts_at', ...)`） | JST 日境界で DB 文字列比較のみ。proof が隣接日に紛れる／落ちる |
| 1-2 | `app/Http/Controllers/DashboardController.php` | 110 行付近 `whereBetween('starts_at', [$event_from, $event_to])` | 月境界付近の proof イベントが欠落 |
| 1-3 | `app/Http/Controllers/Leader/WorkloadAnalyzerController.php` | 254 / 1096 / 1500 行 | 分数計算は `resolveJstCarbon()` 済だが**母集合の取得**が直接比較。期間境界の proof が集計漏れ |
| 1-4 | `app/Http/Controllers/ProofCoordinator/CalendarController.php` | 91 / 134 行 | **要検証**（下記） |

#### ⚠️ 1-4 は他と性質が異なる — 着手前に必ず検証すること

現行コードは日境界を **JST→UTC 変換**してから `events.starts_at` と比較している:

```php
$dayStart = Carbon::parse($date . ' 00:00:00', 'Asia/Tokyo')->utc();  // JST 00:00 → UTC 前日 15:00
$eventModels = Event::whereIn('user_id', $memberIds)
    ->where(fn($q) => $q->whereBetween('starts_at', [$dayStart, $dayEnd]) ...)
```

しかし返却側のコメントには「events.starts_at は JST 文字列格納」と書かれており、`$e->starts_at->utc()` で UTC 化して返している。

- **通常イベント（JST 保存）を UTC 範囲で比較している疑いがある** → proof 以前に通常イベントで 9 時間ずれている可能性
- フロントが UTC ISO を受け取って JST 表示する設計なのかも含めて、**実データで往復検証してから**方針を決めること
- 検証の結果「通常イベントも既にずれている」なら、それは本 PLAN の想定より重い不具合なので、ユーザーに報告して優先度を上げる

#### 修正方針（1-1〜1-3 共通）

`CalendarEventsController::range()` と同じ形に揃える:

1. DB クエリは **±9 時間のバッファ**を付けて広めに取得する（混在形式では DB 側で正確に絞れないため）
2. `->with('projectJobAssignment:id,job_type')` を eager load（N+1 回避。**必須**）
3. PHP 側で `resolveJstCarbon()` により JST 化してから期間判定・フィルタ

```php
// 参考: CalendarEventsController::range() の実装
->where('starts_at', '<=', $end->copy()->addHours(9))
// ... 取得後 ...
$jstStart = $this->resolveJstCarbon($e, 'starts_at');
if ($jstStart && $jstStart->gt($end)) return null;
```

**注意:** 集計系（1-3）は**変更で数値が変わる**。フェーズ完了時に変更前後の集計値を必ず比較すること（後述の検証手順参照）。

---

### フェーズ2: Eloquent の date キャストが `'date'` のまま（CLAUDE.md ルール① 違反）

`'date'` キャストは JSON シリアライズ時に UTC 変換され、Vue 側で `slice(0,10)` すると **1 日ずれる**。日付のみのカラムは `'date:Y-m-d'` にする。

| # | モデル | カラム | 事前調査の所見 |
|---|---|---|---|
| 2-1 | `ProjectJob.php:39` | `plate_submission_date` / `plate_down_date` | 下版日・入稿日。`Create.vue` は空文字初期値なので新規作成は無害。**Edit/Show 系での表示経路を要確認** |
| 2-2 | `UserDailyWorktype.php:11` | `date` | ⚠️ codex は「カレンダーで map のキーに使う」と指摘したが、**事前調査では否定的**。`dailyWorktypes` は `UserMonthlySchedule` から `year_month . '-' . $dd` の文字列連結で生成されており、このモデルを経由していない。`user.daily_worktypes.store` 経由の利用実態を確認すること |
| 2-3 | `ProjectMemo.php:24` | `date` | モデルをそのまま JSON/Inertia に渡す経路あり（codex 確認済） |
| 2-4 | `Changelog.php:23` | `released_at` | 同上。更新履歴ページの日付表示 |
| 2-5 | `ProgressCell.php:28` | 日付カラム | 進行表。**セル表示の日付ずれは影響大** |
| 2-6 | `WorkflowCell.php:32` | 日付カラム | 管理シート。同上 |
| 2-7 | `ProjectScheduleComment.php:14` | 日付カラム | |
| 2-8 | `DispatchProfile.php:23` | 日付カラム | |
| 2-9 | `TransportExpense.php:23` / `TransportExpenseItem.php:24` / `TransportBillingRequest.php:19` | 日付カラム | 交通費。金額・締め処理に絡むため要注意 |

#### 手順（モデルごとに実施）

1. そのカラムが **本当に日付のみ**か（時刻を持たないか）migration で確認する
2. Inertia / JSON に渡る経路を洗う（Controller で明示 `format()` していれば実害なし → 変更は任意）
3. `'date:Y-m-d'` に変更
4. **受け側の Vue を確認**: `slice(0,10)` は変更後も動くが、`new Date(値)` でパースしているコードは挙動が変わり得る

---

### フェーズ3: Vue / JS 側の日付生成が UTC になっている（CLAUDE.md ルール② 違反）

`new Date().toISOString().slice(0,10)` は UTC 日付を返すため、**JST 00:00〜08:59 の間は前日**になる。
→ `new Date().toLocaleDateString('sv-SE')` に統一する。

#### 3-A: 「今日」の生成（P2 相当・実害が出やすい）

| ファイル | 行 |
|---|---|
| `resources/js/Pages/ProofCoordinator/Calendar.vue` | 84 |
| `resources/js/Components/ProofTimelinePickerModal.vue` | 26 |
| `resources/js/Pages/Proof/Calendar.vue` | 66 |
| `resources/js/Pages/Coordinator/ProjectJobs/MemberSchedule.vue` | 32 |
| `resources/js/Pages/JobBox/Show.vue` | 426 |
| `resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue` | 1148 |

#### 3-B: 既存 Date からの日付化（P3 相当）

| ファイル | 行 |
|---|---|
| `resources/js/Components/Calendar.vue` | 1267 / 1323 |
| `resources/js/Pages/JobBox/Show.vue` | 417 |
| `resources/js/Components/GanttWrapper.vue` | 67 |
| `resources/js/Pages/Coordinator/ProjectJobs/CalendarAll.vue` | 36 |

> 3-B は「時刻付き Date からローカル日付を出す」用途なら要修正。
> **元から UTC 基準で扱う意図の箇所は変更しない**（各箇所で用途を確認すること）。

---

### フェーズ4: 再発防止とドキュメント

| # | 作業 |
|---|---|
| 4-1 | `CLAUDE.md`「UTC / JST 混在ルール」に**範囲フィルタのルール**（DB では絞り切らず ±9h バッファ＋PHP 側で JST 判定）を追記 |
| 4-2 | `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` に本件を反映 |
| 4-3 | `ChangelogSeeder` に追記し `php artisan db:seed --class=ChangelogSeeder --force` を本番で実行 |
| 4-4 | 本 PLAN / MANAGER / PROMPT を `z_instructions/archived/` へ移動 |

---

## 3. 変更ファイル一覧（想定）

### PHP（フェーズ1）
```
app/Http/Controllers/EventController.php
app/Http/Controllers/DashboardController.php
app/Http/Controllers/Leader/WorkloadAnalyzerController.php
app/Http/Controllers/ProofCoordinator/CalendarController.php   ← 要検証後に着手
```

### PHP（フェーズ2）
```
app/Models/ProjectJob.php
app/Models/UserDailyWorktype.php
app/Models/ProjectMemo.php
app/Models/Changelog.php
app/Models/ProgressCell.php
app/Models/WorkflowCell.php
app/Models/ProjectScheduleComment.php
app/Models/DispatchProfile.php
app/Models/TransportExpense.php
app/Models/TransportExpenseItem.php
app/Models/TransportBillingRequest.php
```

### Vue（フェーズ3）
```
resources/js/Pages/ProofCoordinator/Calendar.vue
resources/js/Components/ProofTimelinePickerModal.vue
resources/js/Pages/Proof/Calendar.vue
resources/js/Pages/Coordinator/ProjectJobs/MemberSchedule.vue
resources/js/Pages/JobBox/Show.vue
resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm.vue
resources/js/Components/Calendar.vue
resources/js/Components/GanttWrapper.vue
resources/js/Pages/Coordinator/ProjectJobs/CalendarAll.vue
```

### ドキュメント（フェーズ4）
```
CLAUDE.md
z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md
database/seeders/ChangelogSeeder.php
```

---

## 4. 検証手順（各フェーズ共通・必須）

ローカル DB には **proof イベントが 0 件**のため、通常の操作では proof 経路を踏めない。
既存の検証パターン（今回の修正で実績あり）を踏襲する:

```php
// トランザクション内で一時的に proof 化 → 検証 → ロールバック（DB は無傷）
DB::beginTransaction();
$pja->job_type = 'proof'; $pja->save();
// ... 対象メソッドを呼ぶ ...
// ... DB生値と画面表示（range など）を突き合わせる ...
DB::rollBack();
```

### フェーズ1 の追加検証

- **8 時シフト相当のケースを必ず作る**: JST 08:00 開始の proof イベント（UTC 前日 23:00 保存）を作り、当日のカレンダー・ダッシュボード・工数集計に**現れること**を確認する
- **集計系（1-3）は変更前後で数値比較**: 同一期間の集計を修正前後で取り、proof を含まないケースで**数値が一致すること**（＝デグレしていないこと）を確認する

### フェーズ2 の検証

- 変更前後で Inertia レスポンスの該当フィールドを比較（`"2026-06-03T15:00:00.000000Z"` → `"2026-06-04"` になること）
- 受け側 Vue で表示崩れがないこと

### フェーズ3 の検証

- `npm run build` が通ること
- ブラウザ確認は**ユーザーが行う**（Claude は実装とビルドまで）

---

## 5. デプロイ時の注意

- **フェーズ1・2 は PHP のみ** → ビルド不要。`git pull` + `config:clear` + `cache:clear` のみ
- **フェーズ3 は Vue 変更あり** → `z_instructions/DEPLOY_SAKURA.md` の
  **VITE_APP_BASE_PATH 6 ステップを必ず実施**（①`/members` に切替 → ②build → ③commit/push → ④さくら pull → ⑤空に戻す → ⑥ローカル build（コミットしない））
- `public/build/` にはローカル用ビルドの差分が常時大量に出る。**フェーズ3 以外では絶対にコミットしない**

---

## 6. リスクと留意点

| リスク | 対策 |
|---|---|
| フェーズ1 で集計値が変わり、過去の数字と食い違う | 変更前後の比較を必ず実施。proof を含まない期間で一致することを確認 |
| ±9h バッファで取得件数が増え、性能が落ちる | `projectJobAssignment:id,job_type` を eager load。件数の多い画面では実行時間を計測 |
| フェーズ2 で API レスポンス形式が変わり、Vue 側が壊れる | モデル単位で受け側を確認してから変更。一括変更しない |
| 1-4 が想定より重い不具合だった場合 | 検証で判明した時点でユーザーに報告し、優先度を再判断 |

---

## 7. 進め方

**フェーズ1 → 2 → 3 → 4 の順**に、フェーズ単位でコミット・デプロイする。
一度に全部やらない（問題発生時の切り分けが困難になるため）。

進捗は `TZFIX_MANAGER1.md` に記録すること。

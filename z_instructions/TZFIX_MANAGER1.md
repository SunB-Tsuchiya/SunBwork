# TZFIX_MANAGER1 — 進捗管理

対象 PLAN: `TZFIX_PLAN1.md`
開始日: 2026-08-13

---

## 作業フロー（各タスク共通）

1. 対象コードを読む（**推測で直さない**）
2. 修正する
3. `php -l` で構文チェック
4. **トランザクション内で proof 化して往復検証 → ロールバック**（ローカルに proof データが無いため）
5. `TZFIX_MANAGER1.md` の進捗表を更新
6. フェーズ完了時にコミット → push → さくら反映
7. ユーザーへ報告（ブラウザ確認を依頼）

> ⚠️ Vue を変更したフェーズ3 のみ `npm run build` と VITE_APP_BASE_PATH の 6 ステップが必要。

---

## 進捗一覧

### フェーズ1: events の範囲フィルタ（最優先・8時シフト対応）

| # | 対象 | 状態 | 備考 |
|---|---|---|---|
| 1-1 | `EventController::index` の日付絞り込み | ✅ 完了 | ±9h バッファ + `resolveJstCarbon()` 再フィルタ |
| 1-2 | `DashboardController` 110 行付近 | ✅ 完了 | トレイト追加 + バッファ取得 + 再フィルタ |
| 1-3 | `WorkloadAnalyzerController` 254 / 1096 / 1500 | ✅ 完了 | 3 箇所とも対応。集計ハッシュ比較でデグレなし確認 |
| 1-4 | `ProofCoordinator/CalendarController` 91 / 134 | ✅ 完了 | ⚠️ **検証の結果バグ2件を確認**（下記ログ参照）。範囲比較と返却値の両方を修正 |
| 1-V | 8時シフト相当（JST 08:00 開始）の proof で表示・集計を確認 | ✅ 完了 | 4 経路すべてで確認 |

### フェーズ2: date キャストの是正

| # | モデル | 状態 | 備考 |
|---|---|---|---|
| 2-1 | `ProjectJob`（`plate_submission_date` / `plate_down_date`） | 未着手 | Edit/Show 系の表示経路を要確認 |
| 2-2 | `UserDailyWorktype`（`date`） | 未着手 | ⚠️ codex の指摘に疑義あり。利用実態を先に確認 |
| 2-3 | `ProjectMemo`（`date`） | 未着手 | |
| 2-4 | `Changelog`（`released_at`） | 未着手 | |
| 2-5 | `ProgressCell` | 未着手 | 進行表。影響大 |
| 2-6 | `WorkflowCell` | 未着手 | 管理シート |
| 2-7 | `ProjectScheduleComment` | 未着手 | |
| 2-8 | `DispatchProfile` | 未着手 | |
| 2-9 | `TransportExpense` / `TransportExpenseItem` / `TransportBillingRequest` | 未着手 | 交通費・締め処理に注意 |

### フェーズ3: Vue の日付生成

| # | 対象 | 状態 | 備考 |
|---|---|---|---|
| 3-A | 「今日」生成 6 箇所 | 未着手 | `toLocaleDateString('sv-SE')` へ |
| 3-B | 既存 Date からの日付化 5 箇所 | 未着手 | **用途を確認**。UTC 基準が正しい箇所は変更しない |
| 3-V | `npm run build` | 未着手 | |

### フェーズ4: 再発防止・ドキュメント

| # | 作業 | 状態 |
|---|---|---|
| 4-1 | `CLAUDE.md` に範囲フィルタのルールを追記 | 未着手 |
| 4-2 | `CONSOLIDATED_05_calendar_and_jobbox.md` 更新 | 未着手 |
| 4-3 | `ChangelogSeeder` 追記 + 本番 seed | 未着手 |
| 4-4 | PLAN / MANAGER / PROMPT を `archived/` へ移動 | 未着手 |

---

## 作業ログ

### 2026-08-13

- 前提となる修正（本 PLAN の**対象外**・完了済み）
  - `proof-event-timezone-fix-1` として校正ジョブの 9 時間ずれを修正、デプロイ済み
    - `EventController::store / update / update_from_calendar`
    - `User\ProjectJobAssignmentController::update`
    - `JobBoxController::storeSchedule`（codex 点検で追加検出）
  - 本番データ補正: events 4件（2802 / 2840 / 3073 / 3718）、proof_schedules 3件（5 / 8 / 21）を -9h
  - `CLAUDE.md` に UTC/JST 混在ルール ④（書き込み）⑤（カレンダードラッグ時の assignment 同期）を追記
- codex による全体点検を実施。指摘を実コード・本番データで検証した結果:
  - **実バグ**: `JobBoxController::storeSchedule`（対応済み）
  - **現状実害なしだが要対応**: 範囲フィルタ 4 箇所 → 8時シフト勤務者がいるため本 PLAN で対応
  - **指摘は正しいが実害は箇所により異なる**: date キャスト 11 モデル、Vue の `toISOString` 11 箇所
  - **誤検知に近い**: `ProjectJobAssignmentController::store`（この経路は `job_type` を設定しないため proof にならない）
- 本 PLAN / MANAGER / PROMPT を作成（実装は未着手）

#### フェーズ1 実施（同日）— 完了

**1-1 `EventController::index`**
- ±9h バッファ取得 + 取得後に `resolveJstCarbon()` で JST 再フィルタ
- 検証（8時シフト proof = JST 04-24 08:00 / UTC 04-23 23:00 保存）:

  | | 04-24 の一覧に出る | 04-23 の一覧に誤って出る |
  |---|---|---|
  | 修正前 | **NO（漏れ）** | **YES（誤混入）** |
  | 修正後 | YES | NO |

**1-2 `DashboardController`**
- `CalculatesEventTime` トレイトを追加（未使用だった）
- 月境界ケース（JST 07-01 08:00 = UTC 06-30 23:00）で修正後は取得できることを確認

**1-3 `WorkloadAnalyzerController`（3 箇所）**
- 母集合の取得を ±9h バッファに変更し、ループ内の `resolveJstCarbon()` 直後に JST 期間判定を追加
- 8時シフト月初（JST 08-01 08:00 = UTC 07-31 23:00）の集計:
  - 修正前 `event: 0` / `overall: 300` → 修正後 `event: 4` / `overall: 350`
- **デグレ確認**: DB を変更せず既存データで ym=2026-04 / 2026-06 / 2026-08 の `companies` を md5 比較 → **修正前後で完全一致**

**1-4 `ProofCoordinator/CalendarController`（要検証としていた箇所）**
- ⚠️ 検証の結果、**想定より重い 2 件のバグを確認**した:
  1. **通常イベントが当日から漏れる**（proof 以前の問題）。日境界を JST→UTC 変換して `starts_at`（JST 保存）と比較していたため、**JST 15:00 以降の予定が当日の表示から欠落**していた
  2. **proof の返却値が 9 時間ずれる**。`$e->starts_at->utc()` は datetime キャスト（JST 解釈）を経るため、UTC 保存の proof では二重変換になっていた
- 修正: 日境界を JST のまま保持 → ±9h バッファ取得 → `resolveJstCarbon()` で重なり判定。返却値も `resolveJstCarbon()` 経由に変更
- 検証（`pickerData`）:

  | ケース | 修正前 | 修正後 |
  |---|---|---|
  | A) 通常 JST 16:00 | **含まれない** | 含まれる / 返却値正 |
  | B) 通常 JST 10:00 | 含まれる / 返却値正 | 同左（変化なし） |
  | C) proof JST 10:00 | 含まれるが**返却値が -9h ずれ** | 返却値正 |
  | D) proof 8時シフト | — | 含まれる / 返却値正 |

---

## 判明した注意事項（作業中に追記していくこと）

- ローカル DB に proof イベントは **0 件**。検証はトランザクション内で `job_type='proof'` に一時変更して行う
- 本番の proof 割当は 22 件（自己割当 13 / Coordinator 割当 9）、イベントが紐づくのは 11 件
- `Request::create()` で作ったリクエストは `$request->user()` が **null** になる。コントローラを直接呼ぶ検証では `setUserResolver()` が必要
- 本番 `events` テーブルに `date` カラムは**存在しない**（コード側の `Schema::hasColumn('events','date')` 判定はこのため）

# TZFIX1_PROMPT — 新セッション開始用プロンプト

## このファイルの使い方

新しいセッションで作業を再開するとき、以下をそのまま貼り付ける。

---

## 貼り付け用プロンプト

```
z_instructions/TZFIX_PLAN1.md と z_instructions/TZFIX_MANAGER1.md を読んで、
UTC / JST 混在の残存箇所の対応を続けてください。
MANAGER の進捗一覧で「未着手」のものを、フェーズ順（1→2→3→4）に進めてください。
着手前に対象コードを必ず読むこと。フェーズ1-4（ProofCoordinator/CalendarController）は
検証してから方針を決めること。
```

---

## 設計サマリー（コンテキスト用）

### 何をする作業か

`events` テーブルの **UTC / JST 混在**に起因する不具合の残存箇所を潰す。
2026-08-13 に「校正ジョブの予定が保存のたびに 9 時間ずれる」不具合を修正済み（`proof-event-timezone-fix-1`）。
その後 codex で全体点検し、見つかった残存箇所を本作業で対応する。

### 大前提

- アプリは **JST（Asia/Tokyo）** で稼働
- `events.starts_at / ends_at` は保存形式が2種類混在
  - 校正ジョブ（`project_job_assignments.job_type = 'proof'`）→ **UTC 文字列**
  - それ以外 → **JST 文字列**
- 吸収は `app/Http/Controllers/Concerns/CalculatesEventTime.php` のヘルパーで行う

| メソッド | 用途 |
|---|---|
| `resolveJstCarbon($event, $field)` | 保存値 → JST Carbon（読み） |
| `rawToJstCarbon($event, $raw)` | 生値（旧値など）→ JST Carbon |
| `toEventStorageString($event, $jstDateTime)` | JST 日時 → 保存形式の文字列（**書き**） |
| `eventStorageTimezone($event)` | 保存 TZ（proof=UTC / 通常=JST） |
| `recalcInterruptionMinutes($event, $old...)` | 重複時間の再計算 |

### 3 つの対応フェーズ

| フェーズ | 内容 | 優先度 |
|---|---|---|
| 1 | **events の範囲フィルタ**が混在に未対応（4 箇所） | 最優先 |
| 2 | Eloquent の **date キャスト**が `'date'` のまま（11 モデル） | 中 |
| 3 | Vue の **`toISOString()`** による日付ずれ（11 箇所） | 中 |
| 4 | ドキュメント・Changelog・再発防止 | 最後 |

### フェーズ1 を急ぐ理由

現存する proof イベント 11 件はたまたま UTC 日付と JST 日付が一致していて表面化していないだけ。
**8 時シフトの勤務者がいる**ため、JST 09:00 より前に始まる校正予定が入った時点で、
カレンダーに出ない・工数集計から漏れる、という実害が出る。

### フェーズ1 の修正方針

`CalendarEventsController::range()` が**模範実装**。これに揃える:

1. DB クエリは **±9 時間のバッファ**を付けて広く取得（混在形式では DB 側で正確に絞れない）
2. `->with('projectJobAssignment:id,job_type')` を eager load（N+1 回避・**必須**）
3. PHP 側で `resolveJstCarbon()` により JST 化してから期間判定

### ⚠️ 特に注意すること

- **ローカル DB に proof イベントは 0 件**。検証はトランザクション内で `job_type='proof'` に一時変更 → 検証 → `DB::rollBack()` で行う（DB は無傷）
- **8 時シフト相当（JST 08:00 開始 = UTC 前日 23:00 保存）のケースを必ず作って検証すること**。これがフェーズ1 の受け入れ条件
- **集計系（WorkloadAnalyzerController）は変更で数値が変わる**。修正前後で同一期間の集計を比較し、proof を含まない期間で数値が一致することを確認する
- **フェーズ1-4（`ProofCoordinator/CalendarController`）は要検証**。日境界を JST→UTC 変換して比較しており、proof 以前に**通常イベントでずれている疑い**がある。検証結果によっては想定より重い不具合なので、判明した時点でユーザーに報告して優先度を再判断する
- **codex の指摘を鵜呑みにしない**。実際に `UserDailyWorktype`（フェーズ2-2）は「カレンダーで map のキーに使う」と指摘されたが、事前調査では `dailyWorktypes` は別モデル（`UserMonthlySchedule`）から文字列連結で生成されており、このモデルを経由していなかった
- `Request::create()` のリクエストは `$request->user()` が **null**。コントローラ直呼びの検証では `setUserResolver()` が必要

### デプロイ

- フェーズ1・2 は **PHP のみ** → ビルド不要。`git pull` + `config:clear` + `cache:clear`
- フェーズ3 は **Vue 変更あり** → `z_instructions/DEPLOY_SAKURA.md` の VITE_APP_BASE_PATH **6 ステップ必須**
- `public/build/` はフェーズ3 以外で**絶対にコミットしない**

### 参照ドキュメント

| ファイル | 内容 |
|---|---|
| `CLAUDE.md`「UTC / JST 混在ルール」 | ①date キャスト ②Vue の日付 ③events の保存形式 ④書き込みルール ⑤カレンダードラッグ時の同期 |
| `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` | カレンダー・JobBox 仕様 |
| `z_instructions/DEPLOY_SAKURA.md` | デプロイ手順 |

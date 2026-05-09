# SunBWork 修繕計画書 第3版 — 時間計算統一
作成日: 2026-05-09

---

## 背景・目的

ユーザーがカレンダーに登録するジョブの「昼休憩除算」と「重複除算（時間重複による差し引き）」の計算が不安定。
もともと除算機能を後付けで追加したため、適用漏れ・更新漏れ・用語の不統一が散見される。
本計画書はそれらを体系的に洗い出し、一本化されたルールのもとで正確に計算・表示されるよう修正する。

---

## 用語定義（本計画書内の統一用語）

| 用語 | 意味 | DBカラム / 計算方式 |
|------|------|---------------------|
| **生時間** | イベントの ends_at - starts_at の差分（分） | リアルタイム計算 |
| **昼休憩分** | 昼休憩時間帯とイベント時間が重複する分数 | リアルタイム計算（UserMonthlyBreak > user_settings > 12:00-13:00） |
| **重複分** | 同一ユーザーの他のイベントと時間が重複し、**長い方**から差し引かれる分数 | `events.interruption_minutes`（stored値） |
| **実作業時間** | `生時間 - 昼休憩分 - 重複分` | 上記3値から算出 |

> **重複分の方針:** `interruption_minutes` を DB に保存し、全ての分析・表示でこの stored 値を使う。
> ただし stored 値が常に最新である保証をコード側が担保する（→ Q-01・Q-02）。

---

## 作業方針

1. **フェーズ1 — バグ修正（最優先）:** stored 値が stale になるバグを修正
2. **フェーズ2 — コード品質:** 昼休憩ロジックを共通ヘルパーに統合
3. **フェーズ3 — 表示改善:** 用語・表示の統一

各フェーズは前のフェーズ完了後に着手する。フェーズ内は番号順に実施。

---

## フェーズ1：バグ修正

### Q-01 EventController::update() / update_from_calendar() で重複が再計算されない

**症状:**
- `Events/Edit.vue` でイベントの時間を変更・保存しても `interruption_minutes` が再計算されない
- カレンダー上でイベントをドラッグ移動・リサイズしても再計算されない
- 結果として `Analysis.vue`（ProjectJobController::analysis）と `WorkloadAnalyzer` で実作業時間が誤表示される

**根本原因:**
`EventController` に `recalcInterruptionMinutes(Event $event, ?string $oldStart, ?string $oldEnd)` という private メソッドが存在する（L.1270）が、**どこからも呼ばれていない（dead code）**。このメソッドを適切に呼び出すだけで解決する。

**調査先:**
- `app/Http/Controllers/EventController.php`
  - `update()` メソッド（L.725）— 保存後に再計算なし
  - `update_from_calendar()` メソッド（L.84）— 保存後に再計算なし
  - `recalcInterruptionMinutes()` メソッド（L.1270）— 実装済みだが未使用

**対応:**
1. `update()` で保存前に旧時刻を記録し、保存後に `$this->recalcInterruptionMinutes($event, $oldStart, $oldEnd)` を呼ぶ
2. `update_from_calendar()` でも同様に呼ぶ
3. `ClientEventController` / `InternalEventController` に update メソッドが存在する場合は同様に対応

**同時対応（Q-01 に含める）: 同じ長さのイベントの二重差し引きバグ修正**

現在 `recalcInterruptionMinutes()` と `recalcSingleStoredInterruption()` のスキップ条件が `<`（小なり）のため、
同じ長さ（例: 両方120分）の場合に両方のイベントから差し引かれてしまう（二重差し引きバグ）。

```php
// 現在（バグあり）
if ($myDurationMins < $ovDuration) continue;

// 修正後: 同じ長さのときは ID が大きい方（新しい方）を「差し引かれる側」とする
if ($myDurationMins < $ovDuration) continue;
if ($myDurationMins === $ovDuration && $event->id < $ov->id) continue;
```

同じ修正を `recalcSingleStoredInterruption()` にも適用する。
これにより「新しく追加したイベントから差し引く」というルールが一貫して守られる。

**同時対応（Q-01 に含める）: `starts_at` が NULL のイベントへのガード追加**

`recalcInterruptionMinutes()` の overlaps クエリに `whereNotNull('starts_at')` を追加する。
`Carbon::parse(null)` は現在時刻を返すため、NULL イベントが誤って重複対象に含まれる可能性がある。

```php
$overlaps = Event::where('user_id', $event->user_id)
    ->where('id', '!=', $event->id)
    ->whereNotNull('starts_at')  // ← 追加
    ->whereNotNull('ends_at')    // ← 追加
    ->where('starts_at', '<', $myEnd->toDateTimeString())
    ->where('ends_at', '>', $myStart->toDateTimeString())
    ->get(['id', 'starts_at', 'ends_at']);
```

**変更ファイル:**
- `app/Http/Controllers/EventController.php`
- `app/Http/Controllers/Events/ClientEventController.php`（update があれば）
- `app/Http/Controllers/Events/InternalEventController.php`（update があれば）

---

### Q-02 EventController::destroy() 後、重複していた他イベントの stored 値が更新されない

**症状:**
- イベント A（長い）とイベント B（短い）が重複 → A の `interruption_minutes` に B の重複分が加算
- その後 B を削除 → A の `interruption_minutes` は減算されず古い値のまま残る
- 結果として Analysis.vue・WorkloadAnalyzer で A の実作業時間が過少に表示される

**調査先:**
- `app/Http/Controllers/EventController.php`
  - `destroy()` メソッド（L.830）— 削除後に他イベントへの影響を更新していない

**対応:**
- `destroy()` でイベント削除前に、このイベントと重複していた他イベントのリストを取得
- 削除後に各イベントに対して `recalcSingleStoredInterruption()` を呼ぶ

```php
// destroy() の $event->delete() 前に追加
$overlappingBefore = Event::where('user_id', $event->user_id)
    ->where('id', '!=', $event->id)
    ->where('starts_at', '<', $event->ends_at)
    ->where('ends_at', '>', $event->starts_at)
    ->pluck('id');

$event->delete();

// 削除後に波及更新
foreach ($overlappingBefore as $oid) {
    $ov = Event::find($oid);
    if ($ov) $this->recalcSingleStoredInterruption($ov);
}
```

**変更ファイル:**
- `app/Http/Controllers/EventController.php`

---

### Q-03 store() のフロントエンド重複計算の精度向上

**現状:**
- `Events/Create.vue` はフロントエンドで重複を検出し、`own_interruption_minutes` と `interrupted_event_ids` をサーバーに送る
- サーバー側は受け取った値をそのまま保存（サーバー側での独立検証なし）
- フロントエンドの判定がずれた場合（例: タイムゾーン差）に stored 値が誤る

**対応:**
- `EventController::store()` の完了後に `recalcInterruptionMinutes($event)` を呼ぶ
  - これにより、フロントエンドの計算結果に頼らず、サーバー側で正確に再計算・上書きする
- フロントエンドからの `own_interruption_minutes` / `interrupted_event_ids` は引き続き受け取るが、最終的な値はサーバーが正とする

**変更ファイル:**
- `app/Http/Controllers/EventController.php`（store() の末尾）
- `app/Http/Controllers/Events/ClientEventController.php`（store() の末尾）
- `app/Http/Controllers/Events/InternalEventController.php`（store() の末尾）

---

### Q-06 校正イベント（UTC保存）と通常イベント（JST保存）の混在による重複計算ずれ

**症状:**
同一ユーザーが校正ジョブのイベント（`job_type='proof'`）と通常のジョブイベントの両方を持つ場合、
`recalcInterruptionMinutes()` が重複時間を正しく計算できない。

**根本原因:**
- 通常イベント: `starts_at` = JST 文字列（例: 9:00 JST → DB上 `"09:00:00"`）
- 校正イベント: `starts_at` = UTC 文字列（例: 9:00 JST → DB上 `"00:00:00"`）

`recalcInterruptionMinutes()` の overlaps クエリは文字列比較のため、**DB レベルで誤った重複判定**になる:
```
通常イベント A (9:00-17:00 JST): ends_at DB値 = "17:00:00"
校正イベント B (9:00-11:00 JST): ends_at DB値 = "02:00:00" (UTC)

WHERE ends_at > "09:00:00" → "02:00:00" > "09:00:00" → FALSE
→ B が重複対象として拾われない（実際は9:00-11:00で重複しているのに）
```

さらに仮に B が拾えたとしても、`Carbon::parse("00:00:00")` は 0:00 JST として解釈され、
重複時間の計算自体も誤る。

**既存の解決策:**
`EventController` の末尾（L.2523）に `resolveEventJstCarbon(Event $event, string $field)` が実装済み。
これは `job_type === 'proof'` のとき UTC、通常のとき JST で正しく解釈して JST Carbon を返す。
`recalcInterruptionMinutes()` と `recalcSingleStoredInterruption()` でこれを使えばよい。

**対応方針:**
- overlaps クエリを DB レベルの文字列比較に頼らず、**PHP 側で JST 変換してから比較**する
  1. 対象ユーザーの当日前後の全イベントを取得（`starts_at` 近辺で粗く絞る）
  2. 各イベントを `resolveEventJstCarbon()` で JST Carbon に変換
  3. PHP で重複判定・重複分計算

```php
// 変更後のイメージ（overlaps 取得部分）
$candidates = Event::where('user_id', $event->user_id)
    ->where('id', '!=', $event->id)
    ->whereNotNull('starts_at')->whereNotNull('ends_at')
    ->get(['id', 'starts_at', 'ends_at', 'project_job_assignment_id']);

$overlaps = $candidates->filter(function ($ov) use ($myStart, $myEnd) {
    $ovStart = $this->resolveEventJstCarbon($ov, 'starts_at');
    $ovEnd   = $this->resolveEventJstCarbon($ov, 'ends_at');
    if (!$ovStart || !$ovEnd) return false;
    return $ovStart->lt($myEnd) && $ovEnd->gt($myStart);
})->values();
```

> **注意:** `resolveEventJstCarbon()` は `projectJobAssignment` リレーションをロードしている必要がある。
> overlaps の取得時に `with('projectJobAssignment:id,job_type')` を付ける。

**優先度:** 中（proof ロールと通常ロールを兼任するユーザーのみ影響）

**変更ファイル:**
- `app/Http/Controllers/EventController.php`（`recalcInterruptionMinutes()` と `recalcSingleStoredInterruption()`）

---

### 既知の制限（修正対象外）: 3つ以上のイベントが互いに重複する場合の多重カウント

**内容:**
イベント A(9:00-17:00) が B(10:00-11:00) と C(10:30-11:30) の両方と重複する場合:
- A.interruption += B との重複 60分
- A.interruption += C との重複 60分
- A.interruption_minutes = 120

実際の「中断される時間帯」は 10:00〜11:30 = 90分だが、120分が差し引かれる（多重カウント）。

**対応:** 正確に直すには「区間の和集合（union of intervals）」アルゴリズムが必要で実装コストが高い。
また、この状況（短いイベント2つが互いに重複しつつ、両方が長いイベントの中に入る）は実運用では稀。
現時点では修正しないが、将来的な改善候補として記録する。

---

## フェーズ2：コード品質

### Q-04 昼休憩計算ロジックの共通化

**現状:**
以下の6箇所に同一の昼休憩計算ロジックがコピペで存在する。

| # | ファイル | メソッド |
|---|---------|---------|
| 1 | `EventController.php` | `index()` 内 |
| 2 | `EventController.php` | `show()` 内 |
| 3 | `EventController.php` | `showForCoordinator()` 内 |
| 4 | `ProjectJobController.php` | `analysis()` 内 |
| 5 | `WorkloadAnalyzerController.php` | `calcAggregates()` クロージャ内 |
| 6 | `WorkloadAnalyzerController.php` | `show()` 内（eventTypeSums 計算） |

**昼休憩計算ロジック（共通）:**
```php
// 優先順: 日別設定（UserMonthlyBreak）> グローバル設定（user_settings）> デフォルト（12:00-13:00）
$bi = UserMonthlyBreak::breakForDate($userId, $eventDate);
if (!$bi) {
    $bi = ['start' => ($userSetting?->lunch_start ?: '12:00'), 'end' => ($userSetting?->lunch_end ?: '13:00')];
}
$lunchS = Carbon::parse($eventDate . ' ' . $bi['start']);
$lunchE = Carbon::parse($eventDate . ' ' . $bi['end']);
$oS = $evStart->gt($lunchS) ? $evStart : $lunchS;
$oE = $evEnd->lt($lunchE)   ? $evEnd   : $lunchE;
$lunchMins = max(0, (int)$oS->diffInMinutes($oE, false));
```

**対応:**
- `app/Http/Controllers/Concerns/CalculatesEventTime.php` トレイトを新規作成
- メソッド: `computeLunchMinutes(Carbon $evStart, Carbon $evEnd, int $userId, array &$cache = []): int`
- 全6箇所からこのメソッドを呼ぶよう置き換え

**変更ファイル:**
- `app/Http/Controllers/Concerns/CalculatesEventTime.php`（新規作成）
- `app/Http/Controllers/EventController.php`
- `app/Http/Controllers/Coordinator/ProjectJobController.php`
- `app/Http/Controllers/Leader/WorkloadAnalyzerController.php`

---

## フェーズ3：表示改善

### Q-05 用語統一（「重複除算」vs「中断」）

**現状:**
- `Analysis.vue` のテーブルヘッダー: **「重複除算」**（`interruption_minutes`）
- `Events/Show.vue` の表示: **「中断 −XX分」**（`dynamic_interruption_minutes`）
- 同じデータを指すが用語が異なる → ユーザーが混乱する

**統一方針:** 「**重複・中断**」に統一する
- 「重複」= 他のイベントと時間が被っている
- 「中断」= その被っている時間の中で長い方が差し引く時間
- 短い説明: 「**他の予定との重複分**」

**変更内容:**
- `Analysis.vue` の列名「重複除算」→「重複・中断」に変更
- `Events/Show.vue` の「中断 −XX分」→「重複・中断 −XX分」に変更
- `Analysis.vue` の小計行の「重複除算: −XX」→「重複・中断: −XX」に変更

**変更ファイル:**
- `resources/js/Pages/Coordinator/ProjectJobs/Analysis.vue`
- `resources/js/Pages/Events/Show.vue`

---

### Q-07 全コントローラーにおける UTC/JST 混在バグの統合修正

**背景:**
`events.starts_at / ends_at` には2種類の格納形式が混在している。

| イベント種別 | DB格納値（例: 9:00 JST） | 原因 |
|------------|------------------------|------|
| 通常イベント | `"09:00:00"` (JST文字列そのまま) | EventController::store() が変換なしで保存 |
| 校正イベント (job_type='proof') | `"00:00:00"` (UTC文字列) | ProofRequestController が `.utc()` で変換して保存 |

`Carbon::parse($event->starts_at)` で安易にパースすると、**校正イベントは9時間ずれた時刻**として扱われる。

**影響範囲の整理:**

| バグ種別 | 影響 | 深刻度 |
|---------|------|--------|
| A: 昼休憩計算でずれ | proof イベントが 00:00〜08:00 と誤解釈 → 12:00〜13:00 の昼休憩と重複なしと判定 → 実作業時間が多く計算される | 高 |
| B: cross-type 重複検出ずれ | proof イベントと通常イベントのDB文字列比較がずれる → Q-06 で recalcInterruptionMinutes を修正するため、Q-01+Q-06 完了後は影響軽微 | 低 (Q-06 で対処済み) |
| C: 表示時刻ずれ | JobBoxController / Coordinator/ProjectJobController の jobHistory で proof イベント時刻が深夜0時台に表示される | 中 |

**A: 昼休憩計算の修正 (Q-04 と同時実施)**

Q-04 で作成する `CalculatesEventTime` トレイトに、JST 解決ヘルパーを組み込む。
`computeLunchMinutes()` の呼び出し前に引数 `$evStart / $evEnd` を JST 変換済み Carbon で渡す。

```php
// Q-04 トレイトに追加するヘルパー（EventController::resolveEventJstCarbon の共通版）
protected function resolveJstCarbon(Event $event, string $field): ?\Carbon\Carbon
{
    $raw = $event->getRawOriginal($field);
    if (! $raw) return null;
    // projectJobAssignment をロード済みであること（job_type 参照のため）
    $isProof = ($event->projectJobAssignment?->job_type ?? null) === 'proof';
    return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $raw, $isProof ? 'UTC' : 'Asia/Tokyo')
                         ->setTimezone('Asia/Tokyo');
}
```

修正対象 (A):
- `WorkloadAnalyzerController::calcAggregates()` (L.264) — `Carbon::parse($ev->starts_at)` → `$this->resolveJstCarbon($ev, 'starts_at')` へ
- `WorkloadAnalyzerController::show()` eventTypeSums 計算 (L.1119) — 同上
- `ProjectJobController::analysis()` (L.911) — 同上

ただしこれらのクエリに `with('projectJobAssignment:id,job_type')` を追加する必要がある。

**C: 表示時刻の修正**

修正対象 (C):
- `Coordinator/ProjectJobController.php` (L.674, L.678) — `Carbon::parse($ev->starts_at)->setTimezone('Asia/Tokyo')` → JST 解決メソッドへ
- `ProjectJobs/JobBoxController.php` (L.519) — 同上

修正後イメージ:
```php
// 修正前
$s = $ev->starts_at ? \Carbon\Carbon::parse($ev->starts_at)->setTimezone('Asia/Tokyo') : null;

// 修正後（resolveJstCarbon をトレイト or 同じクラスに用意してから）
$s = $this->resolveJstCarbon($ev, 'starts_at');
```

**前提条件:** Q-04 完了後（CalculatesEventTime トレイトに resolveJstCarbon を実装してから）に着手。

**変更ファイル:**
- `app/Http/Controllers/Concerns/CalculatesEventTime.php`（Q-04 と同時: `resolveJstCarbon()` 追加）
- `app/Http/Controllers/Leader/WorkloadAnalyzerController.php`（昼休憩 + 表示時刻）
- `app/Http/Controllers/Coordinator/ProjectJobController.php`（昼休憩 + 表示時刻）
- `app/Http/Controllers/ProjectJobs/JobBoxController.php`（表示時刻）

---

## 全変更ファイル一覧

| フェーズ | ファイル | 変更内容 |
|---------|---------|---------|
| Q-01 | `app/Http/Controllers/EventController.php` | update()/update_from_calendar() に recalcInterruptionMinutes() 追加 |
| Q-01 | `app/Http/Controllers/Events/ClientEventController.php` | update() に同上（存在する場合） |
| Q-01 | `app/Http/Controllers/Events/InternalEventController.php` | update() に同上（存在する場合） |
| Q-02 | `app/Http/Controllers/EventController.php` | destroy() に波及更新追加 |
| Q-03 | `app/Http/Controllers/EventController.php` | store() 末尾に recalcInterruptionMinutes() 追加 |
| Q-03 | `app/Http/Controllers/Events/ClientEventController.php` | store() 末尾に同上 |
| Q-03 | `app/Http/Controllers/Events/InternalEventController.php` | store() 末尾に同上 |
| Q-04 | `app/Http/Controllers/Concerns/CalculatesEventTime.php` | 新規作成（昼休憩計算 + resolveJstCarbon トレイト） |
| Q-04 | `app/Http/Controllers/EventController.php` | 昼休憩計算を共通メソッド呼び出しに置換 |
| Q-04 | `app/Http/Controllers/Coordinator/ProjectJobController.php` | 同上 |
| Q-04 | `app/Http/Controllers/Leader/WorkloadAnalyzerController.php` | 同上 |
| Q-05 | `resources/js/Pages/Coordinator/ProjectJobs/Analysis.vue` | 「重複除算」→「重複・中断」 |
| Q-05 | `resources/js/Pages/Events/Show.vue` | 「中断」→「重複・中断」 |
| Q-06 | `app/Http/Controllers/EventController.php` | recalcInterruptionMinutes / recalcSingleStoredInterruption を resolveEventJstCarbon 対応に変更 |
| Q-07 | `app/Http/Controllers/Concerns/CalculatesEventTime.php` | resolveJstCarbon() 追加（Q-04 と同時） |
| Q-07 | `app/Http/Controllers/Leader/WorkloadAnalyzerController.php` | 昼休憩計算に resolveJstCarbon 適用 + クエリに with() 追加 |
| Q-07 | `app/Http/Controllers/Coordinator/ProjectJobController.php` | 同上 |
| Q-07 | `app/Http/Controllers/ProjectJobs/JobBoxController.php` | jobHistory 表示時刻を resolveJstCarbon で修正 |

---

## 作業ログ

| 日付 | フェーズ | 項目 | 状態 |
|------|---------|------|------|
| 2026-05-09 | — | 計画書（REPAIR_PLAN3.md）・管理書（REPAIR_MANAGER3.md）作成 | 完了 |
| 2026-05-09 | — | Q-01 に同一長さ二重差し引きバグ修正・NULLガード追加。Q-06（校正イベントUTC/JST混在）新規追加。多重重複を既知制限として記録 | 完了 |
| 2026-05-09 | — | Q-07 追加: WorkloadAnalyzerController / ProjectJobController::analysis() / JobBoxController の UTC/JST 混在バグ調査・設計完了。Q-04 と同時実施方針で resolveJstCarbon をトレイトに収録する方針決定 | 完了 |

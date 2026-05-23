# SunBWork 修繕 作業管理書 第3版 — 時間計算統一
作成日: 2026-05-09
更新日: 2026-05-09

---

## この管理書の使い方

**ユーザーへ:**
- 各作業の開始時に「Q-01を始めましょう」などと Claude に伝えてください
- Claude は以下の「作業フロー」に従って安全に進めます
- 作業完了ごとに「進捗一覧」を更新します

**Claude へ（セッション開始時に必ず読むこと）:**
1. この管理書（REPAIR_MANAGER3.md）を読む
2. `z_instructions/REPAIR_PLAN3.md` を読む（詳細仕様・変更ファイル一覧）
3. 現在の進捗一覧を確認し、ユーザーに次の推奨作業を提示する
4. 以下の「作業フロー」に従って進める

---

## 参照ドキュメント

| ドキュメント | 内容 |
|-------------|------|
| `z_instructions/REPAIR_PLAN3.md` | 修繕計画3の詳細仕様・変更ファイル一覧 |
| `z_instructions/REPAIR_MANAGER2.md` | 修繕計画2の管理書（完了済み項目の参照用） |
| `z_instructions/CHANGELOG_SINCE_APR20.md` | 4月20日以降の全変更履歴 |
| `CLAUDE.md` | プロジェクト全体のルール（必読） |

---

## 作業フロー（Claude はこの手順を厳守すること）

```
STEP 1: 計画書を読む
  → REPAIR_PLAN3.md の該当項目を読み、仕様を把握する
  → 関連ファイルをコードで確認する（推測で作業しない）

STEP 2: 設計・方針の提示（ユーザーに確認を取る）
  → 変更ファイル一覧・変更内容の概要・影響範囲を提示
  → 不明点があれば1つずつ質問する（複数同時に質問しない）
  → ユーザーの「OK」を確認してから次のステップへ

STEP 3: 実装
  → 承認された設計に従って実装する
  → PHP ファイルのみの変更なら npm run build は不要
  → Vue/JS ファイルを変更したら npm run build を実行
  → Artisan が必要な場合は docker compose exec 経由で実行

STEP 4: 動作確認の依頼
  → 変更内容を簡潔に報告する
  → ユーザーに動作確認をお願いする

STEP 5: 完了記録
  → ユーザーから「OK」が出たら進捗一覧のステータスを「完了」に更新
  → 備考欄に変更したファイル名を記録
  → 次の推奨作業を提示して止まる（自動で次の作業に進まない）
```

### 安全ルール（必ず守ること）

- STEP 2 でユーザーの確認なしに実装を始めない
- 1つの作業が完了するまで次の作業に移らない
- エラーが出た場合は同じ操作を繰り返さず、原因を調べてから対処する
- 完了後は次の推奨作業を提示して止まる（自動進行しない）

---

## 進捗一覧

### フェーズ1：バグ修正（最優先）

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| Q-01 | update() / update_from_calendar() に recalcInterruptionMinutes() 追加 + 同一長さ二重差し引きバグ修正 + NULLガード | ✅ 完了 | CalculatesEventTime トレイトに実装 |
| Q-02 | EventController::destroy() 後、重複していた他イベントの stored 値を更新 | ✅ 完了 | — |
| Q-03 | store()（EventController / ClientEventController / InternalEventController）末尾に recalcInterruptionMinutes() 追加 | ✅ 完了 | — |
| Q-06 | recalcInterruptionMinutes / recalcSingleStoredInterruption を resolveJstCarbon 対応に変更（UTC/JST混在バグ） | ✅ 完了 | resolveJstCarbon をトレイトに収録 |

### フェーズ2：コード品質

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| Q-04 | 昼休憩計算ロジックを CalculatesEventTime トレイトに共通化（6箇所 → 1箇所） | ✅ 完了 | computeLunchMinutes をトレイトに収録 |

### フェーズ3：表示改善

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| Q-05 | 「重複除算」「中断」の用語を「重複・中断」に統一（Analysis.vue / Events/Show.vue） | ✅ 完了 | — |

### フェーズ4：UTC/JST 混在バグ統合修正

| ID | 内容 | ステータス | 備考 |
|----|------|-----------|------|
| Q-07 | WorkloadAnalyzerController / ProjectJobController::analysis() / JobBoxController の proof イベント時刻ずれ修正 | ✅ 完了 | Q-04 と同時実施済み |

---

## ステータス凡例

| 記号 | 意味 |
|------|------|
| 🔲 未着手 | まだ始めていない |
| 🔍 調査中 | コード調査・仕様確認中 |
| 📝 設計中 | 設計・方針をユーザーと確認中 |
| 🔨 実装中 | コード変更・ビルド中 |
| ✅ 完了 | ユーザー確認済み |
| ⏸ 保留 | 依存関係・仕様未定のため一時停止 |

---

## Q-07 実装時の参照メモ

### UTC/JST 混在の根本構造

```
events.starts_at (TIMESTAMP カラム) の格納値:

通常イベント: JST 文字列そのまま
  例: 9:00 JST → DB値 "09:00:00"
  Carbon::parse("09:00:00") → 09:00 JST ✅

校正イベント (job_type='proof'): UTC 文字列
  例: 9:00 JST → DB値 "00:00:00" (UTC)
  Carbon::parse("00:00:00") → 00:00 JST ❌ (9時間ずれ)
```

### resolveJstCarbon() の実装（Q-04 トレイトに収録）

```php
protected function resolveJstCarbon(Event $event, string $field): ?\Carbon\Carbon
{
    $raw = $event->getRawOriginal($field);
    if (! $raw) return null;
    $isProof = ($event->projectJobAssignment?->job_type ?? null) === 'proof';
    return \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $raw, $isProof ? 'UTC' : 'Asia/Tokyo')
                         ->setTimezone('Asia/Tokyo');
}
```

> `projectJobAssignment` リレーションが必要。クエリに `->with('projectJobAssignment:id,job_type')` を付けること。

### Q-07 修正箇所一覧

| ファイル | 箇所 | 修正内容 |
|---------|------|---------|
| `WorkloadAnalyzerController.php` | L.252-298 (calcAggregates) | `Carbon::parse($ev->starts_at/ends_at)` → `resolveJstCarbon($ev, ...)` + クエリに `with()` 追加 |
| `WorkloadAnalyzerController.php` | L.1108-1153 (show/eventTypeSums) | 同上 |
| `ProjectJobController.php` | L.885-929 (analysis) | 同上 |
| `JobBoxController.php` | L.518-521 (jobHistory/all_events) | 表示時刻を `resolveJstCarbon` で修正 |
| `ProjectJobController.php` | L.673-679 (jobHistory) | 同上 |

### B種（store 時 cross-type 重複）は対処不要

Q-01 + Q-03 + Q-06 完了後、サーバーサイドの `recalcInterruptionMinutes()` が正しく再計算して上書きする。
store 時のフロントエンド計算ずれは最終的に修正される。別途対処不要。

---

## 重要な設計メモ（実装時に参照）

### Q-01 実装時の追加対応（同時に修正する）

**① 同一長さの二重差し引きバグ（recalcInterruptionMinutes / recalcSingleStoredInterruption 両方）:**
```php
// 現在（バグ）: 同じ長さでもスキップしない → 両方から差し引かれる
if ($myDurationMins < $ovDuration) continue;

// 修正後: 同じ長さのとき ID が小さい方（古い方）はスキップ → 新しい方だけ差し引かれる
if ($myDurationMins < $ovDuration) continue;
if ($myDurationMins === $ovDuration && $event->id < $ov->id) continue;
```

**② NULL ガード（overlaps クエリに追加）:**
```php
->whereNotNull('starts_at')
->whereNotNull('ends_at')
```

### interruption_minutes の正しい保存ルール

**長い方のイベントに重複分を加算する。**

```
イベント A (9:00-17:00, 480分)
イベント B (10:00-11:00, 60分) ← 短い方

重複: 10:00-11:00 = 60分
→ A.interruption_minutes += 60  （長い方 A から差し引く）
→ B.interruption_minutes は変更しない
```

同じ長さの場合 → 新しいイベント（自分自身）が「長い」として処理（E-08基準）。

### recalcInterruptionMinutes() の呼び出しタイミング

| 操作 | 呼び出し | 引数 |
|------|---------|------|
| store() | 保存後 | `$this->recalcInterruptionMinutes($event)` |
| update() | 保存後 | `$this->recalcInterruptionMinutes($event, $oldStart, $oldEnd)` |
| update_from_calendar() | 保存後 | `$this->recalcInterruptionMinutes($event, $oldStart, $oldEnd)` |
| destroy() | 削除後（他イベント対象） | `$this->recalcSingleStoredInterruption($overlappingEvent)` |

### Q-06 校正イベントUTC/JST混在バグ（Q-01 と同時実施推奨）

**問題:** `recalcInterruptionMinutes()` と `recalcSingleStoredInterruption()` が `Carbon::parse($ov->starts_at)` で
UTC/JST を区別せずパースするため、校正イベントの時刻が9時間ずれて計算される。
DBクエリ（文字列比較）も誤るため、校正イベントが重複候補として検出されないケースがある。

**修正方針:**
- 既存の `resolveEventJstCarbon(Event $event, string $field)` メソッド（EventController L.2523）を活用
- overlaps を DB クエリで粗く取得した後、PHP 側で JST 変換してフィルタリングする
- 取得時に `with('projectJobAssignment:id,job_type')` を付けて job_type をロードする

**既知の制限（修正対象外）:**
- 3つ以上のイベントが互いに重複する場合、中断時間が多重カウントされる可能性がある
- 正確な修正には「区間の和集合」アルゴリズムが必要（実運用では稀なケースのため保留）

### 昼休憩の優先順（Q-04 実装時）

```
優先順:
1. UserMonthlyBreak::breakForDate($userId, $date) — 日別設定
2. user_settings.lunch_start / lunch_end — ユーザーグローバル設定
3. デフォルト: 12:00〜13:00
```

### 実作業時間の計算式（全箇所で統一）

```
実作業時間（分）= max(0, 生時間 - 重複分(stored) - 昼休憩分(リアルタイム))
```

---

## 作業ログ

| 日付 | ID | 内容 | 対応者 |
|------|----|------|--------|
| 2026-05-09 | — | 計画書（REPAIR_PLAN3.md）・管理書（REPAIR_MANAGER3.md）作成 | Claude |
| 2026-05-09 | — | Q-01 に同一長さバグ・NULLガード追加。Q-06（UTC/JST混在）新規追加。多重重複は既知制限として記録 | Claude |
| 2026-05-09 | Q-07 | UTC/JST 混在バグを全コントローラーにわたって調査。WorkloadAnalyzerController / ProjectJobController / JobBoxController の昼休憩計算・表示時刻ずれを特定。設計完了（Q-04 と同時実施方針） | Claude |
| 2026-05-09 | Q-01〜Q-07 | 全タスク実装完了。CalculatesEventTime トレイト新規作成。EventController / ClientEventController / InternalEventController / ProjectJobController / WorkloadAnalyzerController / JobBoxController に適用。Analysis.vue・Events/Show.vue 用語統一。npm build 成功。 | Claude |

---

## 次の推奨作業

**現時点の推奨:** Q-01 + Q-06 を同時実施。どちらも `recalcInterruptionMinutes()` / `recalcSingleStoredInterruption()` を修正する作業なので、同じ場所を一度に直す方が効率的。
- Q-01: 呼び出し追加 + 二重差し引きバグ修正 + NULLガード
- Q-06: overlaps 取得を `resolveEventJstCarbon()` 対応に変更

続いて Q-02 → Q-03 → **Q-04 + Q-07 同時** → Q-05 の順で進める。

**Q-04 と Q-07 を必ず同時実施すること:**
Q-04 で作成する `CalculatesEventTime` トレイトに `resolveJstCarbon()` を収録し、
Q-07 でそのメソッドを WorkloadAnalyzerController / ProjectJobController / JobBoxController から呼ぶ。
別々に実施すると Q-04 後の中間状態でもまだ proof イベントの昼休憩ずれが残る。

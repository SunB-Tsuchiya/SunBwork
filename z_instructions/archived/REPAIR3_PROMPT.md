# SunBWork 修繕第3版 Claude向けプロンプトファイル — 時間計算統一
作成日: 2026-05-09

---

## このファイルの使い方

新しい Claude セッションを開始するとき、このファイルの内容をそのまま冒頭に貼り付けてください。
または「REPAIR3_PROMPT.md を読んで実装を続けてください」と指示してください。

---

## セッション開始時のプロンプト（コピーして使用）

```
あなたはこれからSunBWorkプロジェクトの修繕作業（第3版：時間計算統一）を行います。

まず以下のファイルを必ず順番に読んでください：
1. `/home/tchirosb/SunBWork/CLAUDE.md`（プロジェクト全体ルール・必読）
2. `/home/tchirosb/SunBWork/z_instructions/REPAIR_MANAGER3.md`（作業管理書・フロー・進捗一覧）
3. `/home/tchirosb/SunBWork/z_instructions/REPAIR_PLAN3.md`（各作業の詳細仕様・変更ファイル一覧）

読み終えたら、以下を報告してください：
- 現在の進捗状況（未着手・進行中・完了の件数）
- 次に着手すべき推奨作業（理由も添えて）

作業は REPAIR_MANAGER3.md に記載された「作業フロー（5ステップ）」と「安全ルール」に従って進めてください。
特に STEP 2（設計・方針の提示）でユーザーの「OK」を得るまで絶対に実装を始めないこと。

各Q-xx作業の完了・進捗状況は必ず REPAIR_MANAGER3.md に記録してください。
```

---

## 設計サマリー（Claude向け補足）

### この修繕の背景

昼休憩除算・重複除算機能はもともとなく、後付けで追加されてきた。都度修正を重ねたが、
以下の根本的な問題が残っている。

### 根本原因

`EventController` に `recalcInterruptionMinutes(Event $event, ?string $oldStart, ?string $oldEnd)` という
private メソッドが L.1270 に存在するが、**どこからも呼ばれていない（dead code）**。

このメソッドを以下のタイミングで呼び出すことで大半の問題が解決する：

| メソッド | 呼び出し追加箇所 |
|---------|----------------|
| `store()` | 保存後 |
| `update()` | 保存後（旧時刻を記録してから渡す） |
| `update_from_calendar()` | 保存後（同上） |
| `destroy()` | 削除後（重複していた他イベントに `recalcSingleStoredInterruption()` を呼ぶ） |

### 用語の整理（全箇所で統一）

| 用語 | 意味 |
|------|------|
| 生時間 | starts_at と ends_at の差（分） |
| 昼休憩分 | 昼休憩時間帯との重複（リアルタイム計算） |
| 重複分 | 他のイベントとの重複で長い方が差し引く分（stored interruption_minutes） |
| 実作業時間 | 生時間 - 昼休憩分 - 重複分 |

### 作業ID一覧

| フェーズ | ID | 内容（短縮） | 変更ファイル数 |
|--------|-----|------------|-------------|
| バグ修正 | Q-01 | update / update_from_calendar に recalcInterruptionMinutes 組み込み + 同一長さ二重差し引きバグ + NULLガード | 3ファイル |
| バグ修正 | Q-02 | destroy 後の波及更新追加 | 1ファイル |
| バグ修正 | Q-03 | store 末尾に recalcInterruptionMinutes 追加（3コントローラー） | 3ファイル |
| バグ修正 | Q-06 | recalcInterruptionMinutes / recalcSingleStoredInterruption を resolveEventJstCarbon 対応に（UTC/JST混在バグ） | 1ファイル |
| コード品質 | Q-04 | 昼休憩計算を CalculatesEventTime トレイトに共通化 + resolveJstCarbon() も収録 | 4ファイル+新規1 |
| UTC/JST修正 | Q-07 | WorkloadAnalyzerController / ProjectJobController / JobBoxController の昼休憩・表示時刻を resolveJstCarbon 対応に（Q-04 と同時実施） | 4ファイル |
| 表示改善 | Q-05 | 「重複除算」「中断」→「重複・中断」に用語統一 | 2ファイル |

### Q-06 の重要な実装ポイント（UTC/JST混在バグ）

`recalcInterruptionMinutes()` と `recalcSingleStoredInterruption()` が `Carbon::parse($ov->starts_at)` で
UTC/JST を区別せずパースしている。校正イベント（proof）は UTC 保存、通常イベントは JST 保存のため
9時間ずれた計算になる。DB クエリ（文字列比較）でも重複が正しく検出されない。

**修正の手がかり:**
- `EventController` 末尾（L.2523）に `resolveEventJstCarbon(Event $event, string $field): ?Carbon` が実装済み
- proof なら UTC、通常なら JST として解釈して JST Carbon を返す
- overlaps 取得を DB 粗フィルタ + PHP 側 JST 変換フィルタに変更する
- overlaps の取得時に `with('projectJobAssignment:id,job_type')` を付けること（resolveEventJstCarbon が job_type を参照するため）

**既知の制限（修正対象外）:**
- 3つ以上が互いに重複する多重カウント問題は実運用では稀なため保留

### Q-07 の重要な実装ポイント（UTC/JST 全体修正）

`events.starts_at / ends_at` には2種類の格納形式が混在している。

| 種別 | DB格納値（9:00 JST の場合） |
|------|--------------------------|
| 通常イベント | `"09:00:00"` (JST そのまま) |
| 校正イベント (proof) | `"00:00:00"` (UTC変換済み) |

`Carbon::parse($ev->starts_at)` だと proof イベントは 00:00 JST になり **昼休憩との重複を見逃す**。

**修正の手がかり:**
- Q-04 で作成する `CalculatesEventTime` トレイトに `resolveJstCarbon(Event $ev, string $field): ?Carbon` を追加する
- `projectJobAssignment` リレーションのロードが必要（`job_type` 参照のため）
- Q-04 と Q-07 は **必ず同時実施** すること

**修正対象コントローラーと箇所:**
- `WorkloadAnalyzerController` L.264 / L.1119 — 昼休憩計算の `$evStart / $evEnd`
- `ProjectJobController::analysis()` L.911 — 同上
- `JobBoxController` L.519 / `ProjectJobController` L.674 — jobHistory 表示時刻

---

### よくある落とし穴（過去の修正から）

- さくら本番: `route()` 必須・ハードコードパス禁止
- さくら本番: `events.interruption_minutes` カラムはさくらに存在するか要確認（存在しない場合は `Schema::hasColumn()` でガード）
- `recalcInterruptionMinutes()` の引数 `$oldStart/$oldEnd` は update 時のみ必要（store では null でよい）
- `saveQuietly()` を使って再帰ループを避けること（recalcInterruptionMinutes 内は既に saveQuietly を使用）
- Q-04 のトレイト作成時: メソッド内でキャッシュ（`array &$cache`）を使ってDBクエリを減らす

### 主要ファイルパス（よく触るもの）

```
app/Http/Controllers/EventController.php           ← Q-01/Q-02/Q-03/Q-04
app/Http/Controllers/Events/ClientEventController.php  ← Q-01/Q-03
app/Http/Controllers/Events/InternalEventController.php ← Q-01/Q-03
app/Http/Controllers/Concerns/CalculatesEventTime.php  ← Q-04（新規作成）
app/Http/Controllers/Coordinator/ProjectJobController.php ← Q-04
app/Http/Controllers/Leader/WorkloadAnalyzerController.php ← Q-04
resources/js/Pages/Coordinator/ProjectJobs/Analysis.vue ← Q-05
resources/js/Pages/Events/Show.vue                 ← Q-05
```

### 昼休憩計算ロジック（Q-04 で共通化する対象）

現在6箇所にコピペされているロジック：

```php
// EventController index/show/showForCoordinator
// ProjectJobController::analysis
// WorkloadAnalyzerController（2箇所）で全く同じコード
$bi = \App\Models\UserMonthlyBreak::breakForDate((int)$userId, $evDate);
if (!$bi) {
    $bi = ['start' => ($userSetting?->lunch_start ?: '12:00'), 'end' => ($userSetting?->lunch_end ?: '13:00')];
}
$lunchS = \Carbon\Carbon::parse($evDate . ' ' . $bi['start']);
$lunchE = \Carbon\Carbon::parse($evDate . ' ' . $bi['end']);
$oS = $evStart->gt($lunchS) ? $evStart : $lunchS;
$oE = $evEnd->lt($lunchE)   ? $evEnd   : $lunchE;
$lunchMins = max(0, (int)$oS->diffInMinutes($oE, false));
```

Q-04 でこれを `CalculatesEventTime` トレイトの `computeLunchMinutes()` に統合する。

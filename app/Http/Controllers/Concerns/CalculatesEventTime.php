<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Event;
use Carbon\Carbon;

/**
 * 時間計算共通トレイト
 *
 * - resolveJstCarbon()              : proof/通常イベントのUTC/JST混在を正しく解決してJST Carbonを返す
 * - computeLunchMinutes()           : 昼休憩とイベントの重複分数を計算（Q-04）
 * - recalcInterruptionMinutes()     : interruption_minutes を再計算・保存（Q-01/Q-06）
 * - recalcSingleStoredInterruption(): 1件の interruption_minutes を再計算・保存
 */
trait CalculatesEventTime
{
    /**
     * イベントの starts_at / ends_at を JST Carbon として返す。
     * proof ジョブ（job_type='proof'）は UTC 保存、通常イベントは JST 保存のため
     * job_type を見て元タイムゾーンを切り替える。
     *
     * 前提: $event->projectJobAssignment リレーションがロード済みであること。
     */
    protected function resolveJstCarbon(Event $event, string $field): ?Carbon
    {
        $raw = $event->getRawOriginal($field);
        if (! $raw) return null;
        $isProof = ($event->projectJobAssignment?->job_type ?? null) === 'proof';
        return Carbon::createFromFormat('Y-m-d H:i:s', $raw, $isProof ? 'UTC' : 'Asia/Tokyo')
                     ->setTimezone('Asia/Tokyo');
    }

    /**
     * イベント時間帯と昼休憩の重複分数を返す。
     *
     * 優先順: UserMonthlyBreak（日別）> user_settings（グローバル）> デフォルト 12:00-13:00
     *
     * @param Carbon $evStart イベント開始（JST）
     * @param Carbon $evEnd   イベント終了（JST）
     * @param int    $userId
     * @param array  $cache   日付別キャッシュ ['YYYY-MM-DD' => ['start','end']] （参照渡し）
     */
    protected function computeLunchMinutes(Carbon $evStart, Carbon $evEnd, int $userId, array &$cache = []): int
    {
        try {
            $evDate = $evStart->toDateString();
            if (!array_key_exists($evDate, $cache)) {
                $bi = \App\Models\UserMonthlyBreak::breakForDate($userId, $evDate);
                if (!$bi) {
                    $us = \App\Models\UserSetting::where('user_id', $userId)->first();
                    $bi = ['start' => ($us?->lunch_start ?: '12:00'), 'end' => ($us?->lunch_end ?: '13:00')];
                }
                $cache[$evDate] = $bi;
            }
            $bi = $cache[$evDate];
            if (!$bi) return 0;
            $lunchS = Carbon::parse($evDate . ' ' . $bi['start']);
            $lunchE = Carbon::parse($evDate . ' ' . $bi['end']);
            $oS = $evStart->gt($lunchS) ? $evStart : $lunchS;
            $oE = $evEnd->lt($lunchE)   ? $evEnd   : $lunchE;
            return max(0, (int) $oS->diffInMinutes($oE, false));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * イベントの interruption_minutes を再計算して DB に保存する。
     * store() / update() の後に呼ぶことで stored 値を常に正確に保つ。
     *
     * - Q-01: 呼び出し箇所追加 + 同一長さ二重差し引きバグ修正 + NULL ガード
     * - Q-06: resolveJstCarbon で UTC/JST 混在を正しく処理
     *
     * @param Event       $event    対象イベント（DB に存在すること）
     * @param string|null $oldStart 更新前の starts_at 生文字列（update 時に旧重複範囲の解除に使用）
     * @param string|null $oldEnd   更新前の ends_at 生文字列
     */
    protected function recalcInterruptionMinutes(Event $event, ?string $oldStart = null, ?string $oldEnd = null): void
    {
        try {
            if (!$event->relationLoaded('projectJobAssignment')) {
                $event->load('projectJobAssignment:id,job_type');
            }

            $myStart = $this->resolveJstCarbon($event, 'starts_at');
            $myEnd   = $this->resolveJstCarbon($event, 'ends_at');
            if (!$myStart || !$myEnd) return;

            $myDurationMins = abs((int) $myEnd->diffInMinutes($myStart));

            // 粗いウィンドウで候補取得（±1日の余裕で UTC/JST 混在に対応）
            $windowStart = $myStart->copy()->subDay()->toDateTimeString();
            $windowEnd   = $myEnd->copy()->addDay()->toDateTimeString();

            $candidates = Event::where('user_id', $event->user_id)
                ->where('id', '!=', $event->id)
                ->whereNotNull('starts_at')
                ->whereNotNull('ends_at')
                ->where('starts_at', '<', $windowEnd)
                ->where('ends_at', '>', $windowStart)
                ->with('projectJobAssignment:id,job_type')
                ->get(['id', 'starts_at', 'ends_at', 'project_job_assignment_id']);

            // PHP 側で JST 変換してから重複判定（DB 文字列比較に依存しない）
            $overlapsWithCarbon = [];
            foreach ($candidates as $ov) {
                $ovStart = $this->resolveJstCarbon($ov, 'starts_at');
                $ovEnd   = $this->resolveJstCarbon($ov, 'ends_at');
                if (!$ovStart || !$ovEnd) continue;
                if (!$ovStart->lt($myEnd) || !$ovEnd->gt($myStart)) continue;
                $overlapsWithCarbon[] = ['event' => $ov, 'start' => $ovStart, 'end' => $ovEnd];
            }

            // ① 自分の interruption_minutes を計算
            $selfInterruption = 0;
            foreach ($overlapsWithCarbon as $item) {
                $ovDuration = abs((int) $item['end']->diffInMinutes($item['start']));
                if ($myDurationMins < $ovDuration) continue; // 自分が短い側 → 差し引かれない
                if ($myDurationMins === $ovDuration && $event->id < $item['event']->id) continue; // 同一長さ: 古い方はスキップ
                $overlapStart = $myStart->gt($item['start']) ? $myStart : $item['start'];
                $overlapEnd   = $myEnd->lt($item['end'])     ? $myEnd   : $item['end'];
                $selfInterruption += max(0, (int) $overlapStart->diffInMinutes($overlapEnd, false));
            }
            $event->interruption_minutes = $selfInterruption;
            $event->saveQuietly();

            // ② 重複相手側の再計算
            foreach ($overlapsWithCarbon as $item) {
                $this->recalcSingleStoredInterruption($item['event']);
            }

            // ③ update 時: 旧時間帯で重複していたイベントも再計算（時間変更で重複解除された可能性）
            if ($oldStart && $oldEnd) {
                $oldS = Carbon::parse($oldStart);
                $oldE = Carbon::parse($oldEnd);
                $oldWindowStart = $oldS->copy()->subDay()->toDateTimeString();
                $oldWindowEnd   = $oldE->copy()->addDay()->toDateTimeString();

                $oldCandidates = Event::where('user_id', $event->user_id)
                    ->where('id', '!=', $event->id)
                    ->whereNotNull('starts_at')
                    ->whereNotNull('ends_at')
                    ->where('starts_at', '<', $oldWindowEnd)
                    ->where('ends_at', '>', $oldWindowStart)
                    ->with('projectJobAssignment:id,job_type')
                    ->get(['id', 'starts_at', 'ends_at', 'project_job_assignment_id']);

                $oldOverlaps = $oldCandidates->filter(function ($ov) use ($oldS, $oldE) {
                    $ovS = $this->resolveJstCarbon($ov, 'starts_at');
                    $ovE = $this->resolveJstCarbon($ov, 'ends_at');
                    if (!$ovS || !$ovE) return false;
                    return $ovS->lt($oldE) && $ovE->gt($oldS);
                })->values();

                foreach ($oldOverlaps as $ov) {
                    $this->recalcSingleStoredInterruption($ov);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CalculatesEventTime: recalcInterruptionMinutes failed', [
                'event_id' => $event->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * イベント1件の stored interruption_minutes を再計算して保存する（他への波及なし）。
     * destroy() 後の波及更新（Q-02）や recalcInterruptionMinutes 内の相手側更新に使用。
     */
    protected function recalcSingleStoredInterruption(?Event $event): void
    {
        if (!$event) return;
        try {
            if (!$event->relationLoaded('projectJobAssignment')) {
                $event->load('projectJobAssignment:id,job_type');
            }

            $myStart = $this->resolveJstCarbon($event, 'starts_at');
            $myEnd   = $this->resolveJstCarbon($event, 'ends_at');
            if (!$myStart || !$myEnd) return;

            $myDuration = abs((int) $myEnd->diffInMinutes($myStart));

            $windowStart = $myStart->copy()->subDay()->toDateTimeString();
            $windowEnd   = $myEnd->copy()->addDay()->toDateTimeString();

            $candidates = Event::where('user_id', $event->user_id)
                ->where('id', '!=', $event->id)
                ->whereNotNull('starts_at')
                ->whereNotNull('ends_at')
                ->where('starts_at', '<', $windowEnd)
                ->where('ends_at', '>', $windowStart)
                ->with('projectJobAssignment:id,job_type')
                ->get(['id', 'starts_at', 'ends_at', 'project_job_assignment_id']);

            $total = 0;
            foreach ($candidates as $ov) {
                $ovStart = $this->resolveJstCarbon($ov, 'starts_at');
                $ovEnd   = $this->resolveJstCarbon($ov, 'ends_at');
                if (!$ovStart || !$ovEnd) continue;
                if (!$ovStart->lt($myEnd) || !$ovEnd->gt($myStart)) continue;
                $ovDuration = abs((int) $ovEnd->diffInMinutes($ovStart));
                if ($myDuration < $ovDuration) continue;
                if ($myDuration === $ovDuration && $event->id < $ov->id) continue;
                $overlapStart = $myStart->gt($ovStart) ? $myStart : $ovStart;
                $overlapEnd   = $myEnd->lt($ovEnd)    ? $myEnd   : $ovEnd;
                $total += max(0, (int) $overlapStart->diffInMinutes($overlapEnd, false));
            }
            $event->interruption_minutes = $total;
            $event->saveQuietly();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CalculatesEventTime: recalcSingleStoredInterruption failed', [
                'event_id' => $event->id ?? null,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}

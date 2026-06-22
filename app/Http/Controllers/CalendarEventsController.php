<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Diary;
use App\Models\Event;
use App\Models\ProgressCell;
use App\Models\ProjectJobAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CalendarEventsController extends Controller
{
    use CalculatesEventTime;

    /**
     * 個人カレンダー専用イベント一覧
     * - is_company_event が NULL または false のイベント（Schedule と重複しない）
     * - 日報マーカー
     */
    public function range(Request $request)
    {
        $request->validate([
            'start' => 'required|date',
            'end'   => 'required|date|after_or_equal:start',
        ]);

        $user  = Auth::user();
        $start = Carbon::createFromFormat('Y-m-d', $request->start, 'Asia/Tokyo')->startOfDay();
        $end   = Carbon::createFromFormat('Y-m-d', $request->end,   'Asia/Tokyo')->endOfDay();

        // proof イベントは UTC 保存のため ±9時間のバッファで広域取得し、
        // PHP 側で JST 変換後に正確な範囲フィルタリングを行う
        $events = Event::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('is_company_event')
                  ->orWhere('is_company_event', false);
            })
            ->where('starts_at', '<=', $end->copy()->addHours(9))
            ->where(function ($q) use ($start) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', $start->copy()->subHours(9));
            })
            ->with(['eventItemType:id,slug', 'projectJobAssignment:id,job_type'])
            ->get();

        $assignmentIds = $events->pluck('project_job_assignment_id')->filter()->unique()->values()->all();
        $progressIds = [];
        $senderMap = [];
        $flagMap = [];

        if (!empty($assignmentIds)) {
            try {
                $progressIds = ProgressCell::whereIn('assignment_id', $assignmentIds)
                    ->pluck('assignment_id')->map(fn($v) => (int)$v)->all();
            } catch (\Throwable $e) {
                Log::error('CalendarEventsController progressIds: ' . $e->getMessage());
            }

            try {
                $rows = ProjectJobAssignment::whereIn('id', $assignmentIds)
                    ->get(['id', 'sender_id', 'source_assignment_id', 'supersedes_assignment_id', 'completed', 'project_job_id']);
                foreach ($rows as $r) {
                    $senderMap[(int)$r->id] = $r->sender_id === null ? null : (int)$r->sender_id;
                    $flagMap[(int)$r->id] = [
                        'has_source'     => !empty($r->source_assignment_id),
                        'has_supersedes' => !empty($r->supersedes_assignment_id),
                        'completed'      => (bool) ($r->completed ?? false),
                        'project_job_id' => (int) $r->project_job_id,
                    ];
                }
            } catch (\Throwable $e) {
                Log::error('CalendarEventsController senderMap: ' . $e->getMessage());
            }
        }

        $formatted = $events->map(function ($e) use ($user, $start, $end, $progressIds, $senderMap, $flagMap) {
            $pjId    = $e->project_job_assignment_id;
            $isProof = $e->projectJobAssignment?->job_type === 'proof';

            // resolveJstCarbon() で UTC/JST 混在を統一処理（CalculatesEventTime トレイト）
            $jstStart = $this->resolveJstCarbon($e, 'starts_at');
            $jstEnd   = $this->resolveJstCarbon($e, 'ends_at');

            // JST 変換後に正確な範囲フィルタリング
            if ($jstStart && $jstStart->gt($end))  return null;
            if ($jstEnd   && $jstEnd->lt($start))  return null;

            $startsAt = $jstStart?->format('Y-m-d H:i:s');
            $endsAt   = $jstEnd?->format('Y-m-d H:i:s');

            $hasProgress = false;
            if ($pjId) {
                $hasProgress = in_array((int)$pjId, $progressIds, true);
                if (!$hasProgress && isset($flagMap[(int)$pjId])) {
                    $hasProgress = !empty($flagMap[(int)$pjId]['has_source'])
                                || !empty($flagMap[(int)$pjId]['has_supersedes']);
                }
            }

            $isSelf = false;
            if ($pjId && isset($senderMap[(int)$pjId])) {
                $isSelf = $senderMap[(int)$pjId] !== null
                       && $senderMap[(int)$pjId] === $user->id;
            }

            // 割り当てが削除済みの孤立イベント（senderMap に存在しない）
            $isOrphaned = $pjId && !isset($senderMap[(int)$pjId]);

            if ($pjId && !$isOrphaned) {
                if ($hasProgress) $color = '#7C3AED';
                elseif ($isProof) $color = '#DB2777';
                elseif ($isSelf)  $color = '#4F46E5';
                else               $color = '#059669';
            } else {
                $color = '#1fb6b3';
            }

            $internalSlugs = ['meeting_internal', 'conference', 'other'];
            $clientSlugs   = ['client_visit', 'customer_visit', 'outing'];
            $slug = $e->eventItemType?->slug;
            if ($slug && in_array($slug, $internalSlugs, true)) {
                $eventRoute = 'internal';
            } elseif ($slug && in_array($slug, $clientSlugs, true)) {
                $eventRoute = 'client';
            } else {
                $eventRoute = 'generic';
            }

            $completed = $pjId && isset($flagMap[(int)$pjId])
                ? $flagMap[(int)$pjId]['completed']
                : false;

            return [
                'id'                        => $e->id,
                'title'                     => $e->title,
                'starts_at'                 => $startsAt,
                'ends_at'                   => $endsAt,
                'body'                      => $e->body,
                'is_own'                    => true,
                '_source'                   => 'personal',
                '_custom_color'             => $color,
                '_event_route'              => $eventRoute,
                'completed'                 => $completed,
                'project_job_assignment_id' => $isOrphaned ? null : $pjId,
                '_is_self_assigned'         => $isSelf,
                '_project_job_id'           => $pjId && isset($flagMap[(int)$pjId]) ? $flagMap[(int)$pjId]['project_job_id'] : null,
            ];
        })->filter()->values();

        $diaries = [];
        try {
            $diaries = Diary::where('user_id', $user->id)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get(['id', 'date'])
                ->map(fn($d) => ['id' => $d->id, 'date' => $d->date->format('Y-m-d')])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::error('CalendarEventsController diaries: ' . $e->getMessage());
        }

        return response()->json([
            'events'  => $formatted,
            'diaries' => $diaries,
        ]);
    }
}

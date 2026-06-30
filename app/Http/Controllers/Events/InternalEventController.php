<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Event;
use App\Models\EventItemType;
use App\Models\MeetingDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class InternalEventController extends Controller
{
    use CalculatesEventTime;

    /** 社内予定フォーム表示 */
    public function create(Request $request)
    {
        $date        = $request->query('date', now()->toDateString());
        $startHour   = $request->query('startHour', '09');
        $startMinute = $request->query('startMinute', '00');
        $endHour     = $request->query('endHour', '10');
        $endMinute   = $request->query('endMinute', '00');

        $slugs = ['meeting_internal', 'conference', 'other'];
        $eventItemTypes = EventItemType::whereIn('slug', $slugs)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        // ログインユーザーが参加メンバーとなっている会議定義を取得（テーブル未作成環境では空配列）
        $meetingDefinitions = Schema::hasTable('meeting_definitions')
            ? MeetingDefinition::whereHas('members', function ($q) {
                $q->where('user_id', Auth::id());
            })->get(['id', 'title', 'description', 'recurrence', 'day_of_week', 'week_of_month', 'custom_dates', 'start_time', 'end_time'])
            : collect();

        return Inertia::render('Events/CreateInternalEvent', [
            'eventItemTypes'     => $eventItemTypes,
            'meetingDefinitions' => $meetingDefinitions,
            'date'               => $date,
            'startHour'          => $startHour,
            'startMinute'        => $startMinute,
            'endHour'            => $endHour,
            'endMinute'          => $endMinute,
        ]);
    }

    /** 社内予定保存 */
    public function store(Request $request)
    {
        $hasMeetingTables = $this->hasMeetingTables();

        $rules = [
            'event_item_type_id'      => 'required|exists:event_item_types,id',
            'title'                   => 'required|string|max:255',
            'date'                    => 'required|date',
            'startHour'               => 'required|string',
            'startMinute'             => 'required|string',
            'endHour'                 => 'required|string',
            'endMinute'               => 'required|string',
            'description'             => 'nullable|string',
            'interrupted_event_ids'   => 'nullable|array',
            'interrupted_event_ids.*' => 'integer',
            'own_interruption_minutes'=> 'nullable|integer',
        ];
        if ($hasMeetingTables) {
            $rules['meeting_definition_id'] = 'nullable|exists:meeting_definitions,id';
        }

        $validated = $request->validate($rules);

        $start = $validated['date'] . ' ' . $validated['startHour'] . ':' . $validated['startMinute'] . ':00';
        $end   = $validated['date'] . ' ' . $validated['endHour']   . ':' . $validated['endMinute']   . ':00';

        $event = new Event();
        $event->user_id               = Auth::id();
        $event->event_item_type_id    = $validated['event_item_type_id'];
        $event->title                 = $validated['title'];
        $event->body                  = $validated['description'] ?? null;
        $event->starts_at             = $start;
        $event->ends_at               = $end;
        $event->interruption_minutes  = (int) ($validated['own_interruption_minutes'] ?? 0);
        if ($hasMeetingTables) {
            $event->meeting_definition_id = $validated['meeting_definition_id'] ?? null;
        }
        $event->save();

        // interrupted_event_ids
        if (!empty($validated['interrupted_event_ids'])) {
            $newStart = \Carbon\Carbon::parse($start);
            $newEnd   = \Carbon\Carbon::parse($end);
            foreach ($validated['interrupted_event_ids'] as $interruptedId) {
                try {
                    $interruptedEvent = Event::where('id', (int) $interruptedId)
                        ->where('user_id', Auth::id())
                        ->first();
                    if ($interruptedEvent && $interruptedEvent->starts_at && $interruptedEvent->ends_at) {
                        $evStart      = \Carbon\Carbon::parse($interruptedEvent->starts_at);
                        $evEnd        = \Carbon\Carbon::parse($interruptedEvent->ends_at);
                        $overlapStart = $newStart->gt($evStart) ? $newStart : $evStart;
                        $overlapEnd   = $newEnd->lt($evEnd)     ? $newEnd   : $evEnd;
                        $overlapMins  = max(0, (int) (($overlapEnd->timestamp - $overlapStart->timestamp) / 60));
                        if ($overlapMins > 0) {
                            $interruptedEvent->increment('interruption_minutes', $overlapMins);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('InternalEventController: failed to update interruption_minutes', ['id' => $interruptedId, 'error' => $e->getMessage()]);
                }
            }
        }

        // Q-03: サーバー側で重複分を正確に再計算
        $this->recalcInterruptionMinutes($event);

        return redirect()->route('calendar.index')->with('success', '予定を登録しました。');
    }

    /** 編集フォーム表示 */
    public function edit(Event $event)
    {
        $this->authorizeEvent($event);

        $slugs = ['meeting_internal', 'conference', 'other'];
        $eventItemTypes = EventItemType::whereIn('slug', $slugs)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $meetingDefinitions = Schema::hasTable('meeting_definitions')
            ? MeetingDefinition::whereHas('members', function ($q) {
                $q->where('user_id', Auth::id());
            })->get(['id', 'title', 'description', 'recurrence', 'day_of_week', 'week_of_month', 'custom_dates', 'start_time', 'end_time'])
            : collect();

        return Inertia::render('Events/CreateInternalEvent', [
            'event'              => $event,
            'eventItemTypes'     => $eventItemTypes,
            'meetingDefinitions' => $meetingDefinitions,
            'selectedMeetingId'  => $event->meeting_definition_id,
            'date'               => $event->starts_at?->toDateString() ?? '',
            'startHour'          => $event->starts_at?->format('H') ?? '09',
            'startMinute'        => $event->starts_at?->format('i') ?? '00',
            'endHour'            => $event->ends_at?->format('H') ?? '10',
            'endMinute'          => $event->ends_at?->format('i') ?? '00',
        ]);
    }

    /** 更新 */
    public function update(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $hasMeetingTables = $this->hasMeetingTables();

        $rules = [
            'event_item_type_id'     => 'required|exists:event_item_types,id',
            'title'                  => 'required|string|max:255',
            'date'                   => 'required|date',
            'startHour'              => 'required|string',
            'startMinute'            => 'required|string',
            'endHour'                => 'required|string',
            'endMinute'              => 'required|string',
            'description'            => 'nullable|string',
        ];
        if ($hasMeetingTables) {
            $rules['meeting_definition_id'] = 'nullable|exists:meeting_definitions,id';
        }

        $validated = $request->validate($rules);

        $oldStart = $event->getRawOriginal('starts_at');
        $oldEnd   = $event->getRawOriginal('ends_at');

        $event->event_item_type_id = $validated['event_item_type_id'];
        $event->title              = $validated['title'];
        $event->body               = $validated['description'] ?? null;
        $event->starts_at          = $validated['date'] . ' ' . $validated['startHour'] . ':' . $validated['startMinute'] . ':00';
        $event->ends_at            = $validated['date'] . ' ' . $validated['endHour']   . ':' . $validated['endMinute']   . ':00';
        if ($hasMeetingTables) {
            $event->meeting_definition_id = $validated['meeting_definition_id'] ?? null;
        }
        $event->save();

        // Q-01: 重複分の再計算
        $this->recalcInterruptionMinutes($event, $oldStart, $oldEnd);

        return redirect()->route('calendar.index')->with('success', '予定を更新しました。');
    }

    private function authorizeEvent(Event $event): void
    {
        if ($event->user_id !== Auth::id()) {
            abort(403);
        }
    }

    private function hasMeetingTables(): bool
    {
        return Schema::hasTable('meeting_definitions')
            && Schema::hasColumn('events', 'meeting_definition_id');
    }
}

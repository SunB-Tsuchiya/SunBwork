<?php

namespace App\Http\Controllers\Events;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\CalculatesEventTime;
use App\Models\Event;
use App\Models\EventItemType;
use App\Models\ProjectJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ClientEventController extends Controller
{
    use CalculatesEventTime;

    /** 案件打合せ・外出フォーム表示 */
    public function create(Request $request)
    {
        $date        = $request->query('date', now()->toDateString());
        $startHour   = $request->query('startHour', '09');
        $startMinute = $request->query('startMinute', '00');
        $endHour     = $request->query('endHour', '10');
        $endMinute   = $request->query('endMinute', '00');

        $slugs = ['client_visit', 'customer_visit', 'outing'];
        $eventItemTypes = EventItemType::whereIn('slug', $slugs)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        // ログインユーザーが参加するプロジェクトのクライアントと案件を取得
        $user = Auth::user();
        $userClients = collect();
        $userProjects = collect();

        try {
            $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                ->where('user_id', $user->id)
                ->get();
            $jobsFromTeam = $ptms->map(fn($ptm) => $ptm->projectJob)->filter();

            // 案件リーダーとして紐づいている案件も含める
            $jobsAsLeader = \App\Models\ProjectJob::with('client')
                ->where('user_id', $user->id)
                ->where('completed', false)
                ->get();

            // 副リーダー（project_job_coordinators）として紐づいている案件も含める
            $jobsAsSubLeader = \App\Models\ProjectJob::with('client')
                ->whereHas('coordinators', fn($q) => $q->where('users.id', $user->id))
                ->where('completed', false)
                ->get();

            $jobs = $jobsFromTeam->merge($jobsAsLeader)->merge($jobsAsSubLeader)->unique('id');

            $userProjects = $jobs->map(fn($job) => [
                'id'        => $job->id,
                'title'     => $job->title ?? ($job->name ?? ''),
                'client_id' => $job->client?->id,
            ])->values();

            $userClients = $jobs->map(fn($job) => $job->client)
                ->filter()
                ->unique('id')
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name ?? ($c->client_name ?? '')])
                ->values();
        } catch (\Throwable $e) {
            Log::warning('ClientEventController::create: failed to load projects/clients', ['error' => $e->getMessage()]);
        }

        return Inertia::render('Events/CreateClientEvent', [
            'eventItemTypes' => $eventItemTypes,
            'clients'        => $userClients,
            'projects'       => $userProjects,
            'date'           => $date,
            'startHour'      => $startHour,
            'startMinute'    => $startMinute,
            'endHour'        => $endHour,
            'endMinute'      => $endMinute,
        ]);
    }

    /** 案件打合せ・外出イベント保存 */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_item_type_id'      => 'required|exists:event_item_types,id',
            'title'                   => 'required|string|max:255',
            'date'                    => 'required|date',
            'startHour'               => 'required|string',
            'startMinute'             => 'required|string',
            'endHour'                 => 'required|string',
            'endMinute'               => 'required|string',
            'description'             => 'nullable|string',
            'project_job_id'          => 'nullable|exists:project_jobs,id',
            'destination'             => 'nullable|string|max:255',
            'interrupted_event_ids'   => 'nullable|array',
            'interrupted_event_ids.*' => 'integer',
            'own_interruption_minutes'=> 'nullable|integer',
        ]);

        $start = $validated['date'] . ' ' . $validated['startHour'] . ':' . $validated['startMinute'] . ':00';
        $end   = $validated['date'] . ' ' . $validated['endHour']   . ':' . $validated['endMinute']   . ':00';

        $event = new Event();
        $event->user_id            = Auth::id();
        $event->event_item_type_id = $validated['event_item_type_id'];
        $event->title              = $validated['title'];
        $event->body               = $validated['description'] ?? null;
        $event->starts_at          = $start;
        $event->ends_at            = $end;
        $event->project_job_id     = $validated['project_job_id'] ?? null;
        $event->destination        = $validated['destination'] ?? null;
        $event->save();

        // own_interruption_minutes
        $ownMins = (int) ($validated['own_interruption_minutes'] ?? 0);
        if ($ownMins > 0) {
            $event->interruption_minutes = $ownMins;
            $event->save();
        }

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
                    Log::warning('ClientEventController: failed to update interruption_minutes', ['id' => $interruptedId, 'error' => $e->getMessage()]);
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
        $event->load('projectJob'); // client_id 取得のため

        $slugs = ['client_visit', 'customer_visit', 'outing'];
        $eventItemTypes = EventItemType::whereIn('slug', $slugs)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug']);

        $user = Auth::user();
        $userClients = collect();
        $userProjects = collect();

        try {
            $ptms = \App\Models\ProjectTeamMember::with(['projectJob.client'])
                ->where('user_id', $user->id)
                ->get();
            $jobsFromTeam = $ptms->map(fn($ptm) => $ptm->projectJob)->filter();

            // 案件リーダーとして紐づいている案件も含める
            $jobsAsLeader = \App\Models\ProjectJob::with('client')
                ->where('user_id', $user->id)
                ->where('completed', false)
                ->get();

            // 副リーダー（project_job_coordinators）として紐づいている案件も含める
            $jobsAsSubLeader = \App\Models\ProjectJob::with('client')
                ->whereHas('coordinators', fn($q) => $q->where('users.id', $user->id))
                ->where('completed', false)
                ->get();

            $jobs = $jobsFromTeam->merge($jobsAsLeader)->merge($jobsAsSubLeader)->unique('id');

            $userProjects = $jobs->map(fn($job) => [
                'id'        => $job->id,
                'title'     => $job->title ?? ($job->name ?? ''),
                'client_id' => $job->client?->id,
            ])->values();
            $userClients = $jobs->map(fn($job) => $job->client)
                ->filter()->unique('id')
                ->map(fn($c) => ['id' => $c->id, 'name' => $c->name ?? ($c->client_name ?? '')])
                ->values();
        } catch (\Throwable $e) {
            // ignore
        }

        return Inertia::render('Events/CreateClientEvent', [
            'event'          => $event,
            'eventItemTypes' => $eventItemTypes,
            'clients'        => $userClients,
            'projects'       => $userProjects,
            'selectedClientId'  => $event->projectJob?->client_id,
            'selectedProjectId' => $event->project_job_id,
            'date'           => $event->starts_at?->toDateString() ?? '',
            'startHour'      => $event->starts_at?->format('H') ?? '09',
            'startMinute'    => $event->starts_at?->format('i') ?? '00',
            'endHour'        => $event->ends_at?->format('H') ?? '10',
            'endMinute'      => $event->ends_at?->format('i') ?? '00',
        ]);
    }

    /** 更新 */
    public function update(Request $request, Event $event)
    {
        $this->authorizeEvent($event);

        $validated = $request->validate([
            'event_item_type_id' => 'required|exists:event_item_types,id',
            'title'              => 'required|string|max:255',
            'date'               => 'required|date',
            'startHour'          => 'required|string',
            'startMinute'        => 'required|string',
            'endHour'            => 'required|string',
            'endMinute'          => 'required|string',
            'description'        => 'nullable|string',
            'project_job_id'     => 'nullable|exists:project_jobs,id',
            'destination'        => 'nullable|string|max:255',
        ]);

        $oldStart = $event->getRawOriginal('starts_at');
        $oldEnd   = $event->getRawOriginal('ends_at');

        $event->event_item_type_id = $validated['event_item_type_id'];
        $event->title              = $validated['title'];
        $event->body               = $validated['description'] ?? null;
        $event->starts_at          = $validated['date'] . ' ' . $validated['startHour'] . ':' . $validated['startMinute'] . ':00';
        $event->ends_at            = $validated['date'] . ' ' . $validated['endHour']   . ':' . $validated['endMinute']   . ':00';
        $event->project_job_id     = $validated['project_job_id'] ?? null;
        $event->destination        = $validated['destination'] ?? null;
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
}

<?php

namespace App\Services;

use App\Models\Diary;
use App\Models\Event;
use App\Models\PersonalWorkEntry;
use App\Models\User;
use App\Support\PersonalWorkActivities;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiaryWorkEntries
{
    public function __construct(private PersonalWorkSchedule $schedule) {}

    public function candidates(User $user, string $date): array
    {
        if (! $this->schedule->available($user)) return [];
        $candidates = $this->schedule->ownCandidates($user, $date);
        $saved = PersonalWorkEntry::where('user_id', $user->id)->whereDate('work_date', $date)
            ->whereNotNull('diary_id')->get()->keyBy(fn ($row) => $row->source_event_id ? 'event-'.$row->source_event_id : 'entry-'.$row->id);
        $rows = [];
        foreach ($candidates['entries'] as $entry) {
            $row = $saved->get('entry-'.$entry['id']);
            $rows[] = [
                'key' => 'entry-'.$entry['id'], 'entry_id' => $entry['id'], 'source_event_id' => null,
                'title' => $entry['title'] ?: ($entry['project_name_text'] ?: ($entry['project_job']['title'] ?? '作業予定')),
                'project_name' => $entry['project_job']['title'] ?? $entry['project_name_text'], 'work_category' => $entry['work_category'],
                'selected' => (bool) $row, 'result_note' => $entry['result_note'] ?? '',
                'actual_quantity' => $entry['actual_quantity'], 'actual_unit' => $entry['actual_unit'] ?? '',
                'activity_type' => $row?->activity_type ?? $entry['activity_type'] ?? '',
                'actual_minutes' => $entry['suggested_minutes'],
                'ready' => filled($entry['result_note']) && filled($entry['activity_type']) && ! empty($entry['suggested_minutes']),
            ];
        }
        foreach ($candidates['events'] as $event) {
            $row = $saved->get('event-'.$event['id']);
            $rows[] = [
                'key' => 'event-'.$event['id'], 'entry_id' => null, 'source_event_id' => $event['id'],
                'title' => $event['title'], 'project_name' => $event['project_name'],
                'work_category' => $event['work_category'], 'selected' => (bool) $row,
                'result_note' => $row?->result_note ?? '', 'actual_quantity' => $row?->actual_quantity,
                'actual_unit' => $row?->actual_unit ?? '',
                'activity_type' => $row?->activity_type ?? '',
                'actual_minutes' => $row?->actual_minutes ?? $event['suggested_minutes'],
            ];
        }
        return $rows;
    }

    public function validate(Request $request): array
    {
        return $request->validate([
            'work_entries' => ['sometimes', 'array'],
            'work_entries.*.entry_id' => ['nullable', 'integer'],
            'work_entries.*.source_event_id' => ['nullable', 'integer'],
            'work_entries.*.title' => ['nullable', 'string', 'max:255'],
            'work_entries.*.work_category' => ['required', Rule::in(['shinko', 'eigyo', 'dtp', 'kousei', 'jimu', 'other'])],
            'work_entries.*.activity_type' => ['required', Rule::in(PersonalWorkActivities::codes())],
            'work_entries.*.actual_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'work_entries.*.result_note' => ['required', 'string', 'max:2000'],
            'work_entries.*.actual_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'work_entries.*.actual_unit' => ['nullable', 'string', 'max:30', 'required_with:work_entries.*.actual_quantity'],
        ])['work_entries'] ?? [];
    }

    public function sync(Diary $diary, User $user, array $rows): void
    {
        if (! $this->schedule->available($user) && $rows) abort(403);
        $date = $diary->date->toDateString();
        $validEvents = $rows ? $this->schedule->eventsForUsers([$user->id], $date)
            ->filter(fn (Event $event) => (int) $event->user_id === (int) $user->id && $event->project_job_assignment_id)
            ->keyBy('id') : collect();
        $seen = [];
        foreach ($rows as $row) {
            if (! PersonalWorkActivities::allows($row['work_category'], $row['activity_type'])) {
                throw ValidationException::withMessages(['work_entries' => '作業区分に合う作業種類を選んでください。']);
            }
            $entryId = $row['entry_id'] ?? null;
            $eventId = $row['source_event_id'] ?? null;
            if ($entryId && $eventId) {
                throw ValidationException::withMessages(['work_entries' => '予定を正しく選択してください。']);
            }
            if (! $entryId && ! $eventId && ! trim($row['title'] ?? '')) {
                throw ValidationException::withMessages(['work_entries' => '予定外作業の名前を入力してください。']);
            }
            $key = $entryId ? 'entry-'.$entryId : ($eventId ? 'event-'.$eventId : 'manual-'.count($seen));
            if (isset($seen[$key])) throw ValidationException::withMessages(['work_entries' => '同じ予定を重複して選択できません。']);
            $seen[$key] = true;
            $note = trim($row['result_note']);
            if ($note === '') throw ValidationException::withMessages(['work_entries' => '選択した予定の結果を入力してください。']);
            $entryWasReady = false;
            $plannedMinutes = null;
            if ($entryId) {
                $entry = PersonalWorkEntry::whereKey($entryId)->where('user_id', $user->id)
                    ->whereDate('work_date', $date)->whereNull('source_event_id')->lockForUpdate()->first();
                if (! $entry || ($entry->diary_id && (int) $entry->diary_id !== (int) $diary->id)) abort(403);
                $plannedMinutes = $this->schedule->plannedMinutes(
                    $entry, $this->schedule->workWindow($user, $date)
                );
                if (! $plannedMinutes) {
                    throw ValidationException::withMessages(['work_entries' => '簡易予定の時間をカレンダーで確認してください。']);
                }
                $entryWasReady = filled($entry->result_note) && filled($entry->activity_type);
            } elseif ($eventId) {
                $event = $validEvents->get($eventId);
                if (! $event) abort(403);
                $entry = PersonalWorkEntry::where('user_id', $user->id)->where('source_event_id', $eventId)
                    ->lockForUpdate()->first();
                if ($entry && $entry->diary_id && (int) $entry->diary_id !== (int) $diary->id) abort(403);
                if (! $entry) {
                    $entry = new PersonalWorkEntry([
                        'user_id' => $user->id, 'company_id' => $user->company_id,
                        'department_id' => $user->department_id, 'work_date' => $date,
                        'project_job_id' => $event->projectJobAssignment?->project_job_id,
                        'project_job_assignment_id' => $event->project_job_assignment_id,
                        'source_event_id' => $eventId, 'title' => $event->title,
                        'entry_visibility' => 'department',
                    ]);
                }
            } else {
                $entry = new PersonalWorkEntry([
                    'user_id' => $user->id, 'company_id' => $user->company_id,
                    'department_id' => $user->department_id, 'work_date' => $date,
                    'title' => trim($row['title']), 'entry_visibility' => 'department',
                ]);
            }
            $entry->fill([
                'diary_id' => $diary->id, 'work_category' => $entryWasReady ? $entry->work_category : $row['work_category'],
                'activity_type' => $entryWasReady ? $entry->activity_type : $row['activity_type'],
                'actual_minutes' => $entryId ? $plannedMinutes : $row['actual_minutes'],
                'result_note' => $entryWasReady ? $entry->result_note : $note,
                'actual_quantity' => $entryWasReady ? $entry->actual_quantity : ($row['actual_quantity'] ?? null),
                'actual_unit' => $entryWasReady ? $entry->actual_unit : ($row['actual_unit'] ?? null),
            ])->save();
            if (! $entryId && ! $eventId) $seen['entry-'.$entry->id] = true;
        }
        // Removing a row from the diary leaves the morning plan intact.
        PersonalWorkEntry::where('diary_id', $diary->id)->get()->each(function ($entry) use ($seen) {
            $key = $entry->source_event_id ? 'event-'.$entry->source_event_id : 'entry-'.$entry->id;
            if (isset($seen[$key])) return;
            if ($entry->source_event_id) $entry->delete();
            else $entry->update(['diary_id' => null]);
        });
    }
}

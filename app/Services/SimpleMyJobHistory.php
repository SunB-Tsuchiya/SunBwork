<?php

namespace App\Services;

use App\Models\PersonalWorkEntry;
use App\Models\Event;
use App\Models\ProjectJobAssignmentByMyself;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Adds owner-scoped simple work to the existing reuse picker, without copying records. */
class SimpleMyJobHistory
{
    public function merge(Request $request, $records, $clients, $projects): array
    {
        $user = $request->user();
        $entries = PersonalWorkEntry::where('user_id', $user->id)->where('company_id', $user->company_id)
            ->where('department_id', $user->department_id)->whereNull('source_event_id')
            ->with(['projectJob.client', 'broadProject.client', 'broadProject.parent'])->get()->filter(function ($entry) use ($user) {
                if ($entry->projectJob && $entry->projectJob->company_id !== null && (int) $entry->projectJob->company_id !== (int) $user->company_id) return false;
                foreach ([$entry->broadProject, $entry->broadProject?->parent] as $broad) {
                    if ($broad && ((int) $broad->company_id !== (int) $user->company_id || (int) $broad->department_id !== (int) $user->department_id)) return false;
                }
                return true;
            });
        $broadClients = new BroadProjectClients;
        $broadClients->load($entries->map(fn ($entry) => $entry->broadProject)->filter()->unique('id')->values(), (int) $user->company_id);
        $clientId = $request->query('client_id'); $projectId = $request->query('project_job_id');
        $related = fn ($entry) => $entry->project_job_id
            ? ($entry->projectJob?->client ? [['id' => (int) $entry->projectJob->client->id, 'name' => $entry->projectJob->client->name]] : [])
            : ($entry->broadProject ? $broadClients->direct($entry->broadProject) : []);
        $entryClients = $entries->flatMap($related);
        $entryProjects = $clientId ? $entries->filter(fn ($entry) => (int) $entry->projectJob?->client_id === (int) $clientId)
            ->map(fn ($entry) => $entry->projectJob)->filter()->map(fn ($project) => ['id' => $project->id, 'title' => $project->title]) : collect();
        if ($request->query('mode', 'date') === 'date') {
            $days = match ($request->query('date_range', 'yesterday')) { 'yesterday' => 1, '7days' => 7, default => 30 };
            $from = Carbon::now('Asia/Tokyo')->subDays($days)->startOfDay(); $to = Carbon::now('Asia/Tokyo')->endOfDay();
            $entries = $entries->filter(fn ($entry) => $entry->work_date->betweenIncluded($from, $to)
                || $entry->created_at?->betweenIncluded($from, $to));
        } else {
            $entries = $entries->filter(fn ($entry) => $projectId ? (int) $entry->project_job_id === (int) $projectId
                : (! $clientId || in_array((int) $clientId, array_column($related($entry), 'id'), true)));
        }
        if ($request->boolean('hide_completed')) $entries = $entries->filter(fn ($entry) => ! $entry->completed_at);
        $simple = $entries->map(function ($entry) use ($related) {
            $work = $entry->only(['id', 'work_category', 'activity_type', 'project_job_id', 'broad_project_id', 'project_name_text', 'title', 'entry_visibility', 'actual_unit']);
            $work['project_label'] = $entry->projectJob?->title;
            return ['id' => (int) $entry->id, 'key' => 'entry:'.$entry->id, 'source_type' => 'entry',
                'title' => $entry->title ?: app(PersonalWorkSchedule::class)->entryCalendarTitle($entry),
                'project_job_id' => $entry->project_job_id,
                'project_job_name' => $entry->projectJob?->title ?? $entry->broadProject?->pathLabel() ?? $entry->project_name_text ?? '-',
                'client_id' => $entry->projectJob?->client_id,
                'client_name' => $entry->project_job_id ? ($entry->projectJob?->client?->name ?? '-') : (implode('・', array_column($related($entry), 'name')) ?: '-'),
                'completed' => (bool) $entry->completed_at, 'created_at' => $entry->work_date->format('Y-m-d'),
                'history_sort' => $entry->created_at?->format('Y-m-d H:i:s') ?? $entry->work_date->format('Y-m-d'),
                'work_item_type' => '簡易入力', 'estimated_hours' => null, 'work_entry' => $work];
        });
        $oldModels = ProjectJobAssignmentByMyself::where('user_id', $user->id)->whereIn('id', $records->pluck('id'))
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $user->company_id))
            ->where(fn ($query) => $query->whereNull('department_id')->orWhere('department_id', $user->department_id))
            ->with('projectJob')->get()->filter(fn ($job) => ! $job->projectJob || $job->projectJob->company_id === null
                || (int) $job->projectJob->company_id === (int) $user->company_id)->keyBy('id');
        $privateIds = Event::whereIn('project_job_assignment_id', $oldModels->keys())->where('visibility', 'private')
            ->pluck('project_job_assignment_id')->flip();
        $reader = app(WorkJobReader::class);
        $old = $records->filter(fn ($row) => $oldModels->has($row['id']))->map(function ($row) use ($oldModels, $privateIds, $reader, $user) {
            $job = $oldModels->get($row['id']);
            return $row + ['source_type' => 'assignment', 'key' => 'assignment:'.$row['id'],
                'history_sort' => $job->created_at?->format('Y-m-d H:i:s') ?? $row['created_at'] ?? '',
                'simple_prefill' => $reader->assignmentPrefill($user, $job, $privateIds->has($job->id))];
        });
        $all = $old->concat($simple)->sortByDesc('history_sort')->take(100)->map(function ($row) { unset($row['history_sort']); return $row; })->values();
        return ['records' => $all, 'clients' => $clients->concat($entryClients)->unique('id')->values(),
            'projects' => $projects->concat($entryProjects)->unique('id')->values()];
    }
}

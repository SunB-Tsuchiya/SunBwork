<?php
namespace App\Services;

use App\Models\Event;
use App\Models\ProjectJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Creates a separate legacy continuation, never converts or copies the source work. */
class LegacyWorkJobContinuation
{
    use \App\Http\Controllers\Concerns\CalculatesEventTime;
    public function __construct(private WorkJobReader $reader, private WorkJobMutation $mutation) {}

    public function prefill(User $user, int $id, ?int $context, ?int $otherId): array
    {
        $entry = $this->reader->resolve($user, 'entry', $id, $context);
        $project = $entry->projectJob ?: ProjectJob::findOrFail($otherId);
        $project->loadMissing('client');
        abort_unless($project->company_id === null || (int) $project->company_id === (int) $user->company_id, 403);
        return ['source' => ['id' => $id, 'title' => $entry->title, 'classification' => $entry->broadProject?->pathLabel()],
            'assignment' => ['id' => null, 'project_job_id' => $project->id,
            '_client_id' => $project->client_id, 'title' => mb_substr($entry->title ?: ($entry->broadProject?->pathLabel() ?: $entry->project_name_text ?: '続きの作業'), 0, 255),
            'detail' => $entry->detail_note, 'amounts' => null, 'amounts_unit' => in_array($entry->actual_unit, ['ページ', 'page']) ? 'page' : (in_array($entry->actual_unit, ['ファイル', 'file']) ? 'file' : null),
            'source_personal_work_entry_id' => $id, 'legacy_request_token' => (string) Str::uuid(),
            'estimated_hours' => null, 'file_info' => null],
            'project' => ['id' => $project->id, 'title' => $project->title, 'client_id' => $project->client_id],
            'client' => $project->client ? ['id' => $project->client->id, 'name' => $project->client->name] : null];
    }

    public function store(Request $request, ProjectJob $project, callable $legacyStore)
    {
        $context = $request->session()->get('superadmin_context.company_id');
        $context = $context === null ? null : (int) $context;
        $data = $request->validate(['assignments' => ['required', 'array', 'size:1'],
            'assignments.0.source_personal_work_entry_id' => ['required', 'integer', 'min:1'],
            'assignments.0.legacy_request_token' => ['required', 'uuid'],
            'assignments.0.source_assignment_id' => ['prohibited'], 'assignments.0.supersedes_assignment_id' => ['prohibited'],
            'reuse_assignment_id' => ['prohibited'], 'reuse_entry_id' => ['prohibited'],
            'assignments.0.id' => ['prohibited'], 'assignments.0.coordinator_assignment_id' => ['prohibited'],
            'assignments.0.source_event_id' => ['prohibited']]);
        $source = (int) $data['assignments'][0]['source_personal_work_entry_id'];
        $user = $request->user();
        $this->reader->resolve($user, 'entry', $source, $context);
        abort_unless($project->company_id === null || (int) $project->company_id === (int) $user->company_id, 403);
        if ((int) $request->input('assignments.0.project_job_id') !== (int) $project->id) {
            throw ValidationException::withMessages(['assignments.0.project_job_id' => '選択した案件を確認してください。']);
        }
        $modal = $request->boolean('myjob_modal');
        $slot = Arr::only($request->input('assignments.0'), ['title','detail','project_job_id','desired_start_date','desired_end_date','start_time','desired_time',
            'difficulty_id','estimated_hours','work_item_type_id','size_id','stage_id','amounts','amounts_unit','file_info',
            '_progress_sheet_id','_workflow_sheet_id','_row_id','_col_key']);
        $slot += array_fill_keys(['work_item_type_id','size_id','stage_id','difficulty_id','estimated_hours','amounts','amounts_unit','detail','file_info'], null);
        $slot['source_personal_work_entry_id'] = $source;
        $slot['legacy_request_token'] = $data['assignments'][0]['legacy_request_token'];
        $slot['status_id'] = DB::table('statuses')->where('key', 'in_progress')->orWhere('slug', 'in_progress')->value('id');
        ksort($slot);
        $hash = hash('sha256', json_encode($slot, JSON_THROW_ON_ERROR));
        $key = hash('sha256', $user->id.':'.$slot['legacy_request_token']);
        $result = DB::transaction(function () use ($request, $legacyStore, $user, $source, $context, $slot, $hash, $key) {
            $this->mutation->lockContinuationSource($user, $source, $context);
            $parent = $this->reader->resolve($user, 'entry', $source, $context);
            $inserted = DB::table('work_job_continuations')->insertOrIgnore(['request_key' => $key, 'user_id' => $user->id,
                'source_entry_id' => $source, 'source_assignment_id' => null, 'payload_hash' => $hash,
                'created_at' => now(), 'updated_at' => now()]);
            $receipt = DB::table('work_job_continuations')->where('request_key', $key)->lockForUpdate()->firstOrFail();
            abort_unless((int) $receipt->user_id === (int) $user->id && (int) $receipt->source_entry_id === $source
                && $receipt->source_assignment_id === null && $receipt->entry_id === null && hash_equals($receipt->payload_hash, $hash), 422, '同じ送信キーの内容が変わっています。画面を開き直してください。');
            if (! $inserted) {
                abort_unless($receipt->assignment_id, 410, '作成済みの続きは削除されています。');
                $this->reader->resolve($user, 'assignment', (int) $receipt->assignment_id, $context);
                return ['assignment_id' => (int) $receipt->assignment_id, 'replayed' => true, 'date' => $slot['desired_start_date'] ?? null];
            }
            if (count($this->reader->series($user, $source, $context, false)['items']) >= 100) {
                throw ValidationException::withMessages(['source' => '系列が多いため、これ以上の続きは登録できません。']);
            }
            // The existing modal validation fixes owner/company and excludes progress/request linkage.
            $request->merge(['myjob_modal' => 1, 'assignments' => [$slot]]);
            $response = $legacyStore();
            $id = $response->getData(true)['assignment_id'] ?? null;
            abort_unless($id, 422, '続きの登録結果を確認できませんでした。');
            $assignment = $this->reader->resolve($user, 'assignment', (int) $id, $context);
            $events = Event::where('project_job_assignment_id', $id)->where('user_id', $user->id)->get();
            if ($events->count() !== 1 || ! $events[0]->starts_at || ! $events[0]->ends_at) {
                throw ValidationException::withMessages(['source' => '続きの予定を保存できなかったため、登録を取り消しました。']);
            }
            $this->recalcInterruptionMinutes($events[0]);
            $assignment->source_personal_work_entry_id = $source;
            $assignment->completed = false;
            $assignment->save();
            if ($parent->entry_visibility === 'private' || ($parent->projectJob && ! $parent->projectJob->show_on_department_schedule)) {
                $events[0]->visibility = 'private'; $events[0]->save();
            }
            DB::table('work_job_continuations')->where('request_key', $key)->update(['assignment_id' => $id, 'updated_at' => now()]);
            return ['assignment_id' => (int) $id, 'replayed' => false, 'date' => $slot['desired_start_date']];
        }, 3);
        return $modal ? response()->json($result, $result['replayed'] ? 200 : 201)
            : redirect()->route('user.myjobbox.show', ['assignment' => $result['assignment_id']]);
    }
}

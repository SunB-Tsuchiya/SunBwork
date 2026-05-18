<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ProofRequest;
use App\Models\ProofSchedule;
use App\Models\ProjectJobAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProofJobController extends Controller
{
    // ──────────────────────────────────────────────────────
    //  一覧
    // ──────────────────────────────────────────────────────
    public function index(Request $request): Response
    {
        $user          = Auth::user();
        $q             = $request->input('q');
        $hideCompleted = $request->boolean('hide_completed', true);
        $clientId      = $request->input('client_id');

        // 年月フィルター（deadline 基準）
        $periodParam     = $request->input('period');
        $usePeriodFilter = true;
        $periodModel     = $periodParam;
        if ($periodParam === null) {
            $periodModel = now()->format('Y-m');
        } elseif ($periodParam === '' || $periodParam === 'all') {
            $usePeriodFilter = false;
        }
        $periodStart = null;
        $periodEnd   = null;
        if ($usePeriodFilter && $periodModel) {
            try {
                $periodStart = \Carbon\Carbon::createFromFormat('Y-m', $periodModel)->startOfMonth()->setTimezone('UTC');
                $periodEnd   = \Carbon\Carbon::createFromFormat('Y-m', $periodModel)->endOfMonth()->setTimezone('UTC');
            } catch (\Throwable $e) {
                $periodModel = now()->format('Y-m');
                $periodStart = now()->startOfMonth()->setTimezone('UTC');
                $periodEnd   = now()->endOfMonth()->setTimezone('UTC');
            }
        }

        // 月選択肢（前後6か月）
        $monthOptions = [];
        for ($i = -6; $i <= 6; $i++) {
            $m              = now()->addMonths($i)->format('Y-m');
            $monthOptions[] = [
                'value' => $m,
                'label' => now()->addMonths($i)->format('Y年n月'),
            ];
        }

        $query = ProofRequest::with(['requester', 'projectJob.client'])
            ->where('proofreader_id', $user->id)
            ->whereIn('status', ['assigned', 'in_progress', 'completed']);

        if ($q) {
            $query->where('title', 'like', "%{$q}%");
        }

        if ($hideCompleted) {
            $query->where('status', '!=', 'completed');
        }

        if ($usePeriodFilter && $periodStart && $periodEnd) {
            $query->whereBetween('deadline', [
                $periodStart->format('Y-m-d H:i:s'),
                $periodEnd->format('Y-m-d H:i:s'),
            ]);
        }

        if ($clientId) {
            $query->whereHas('projectJob', fn ($sub) => $sub->where('client_id', $clientId));
        }

        $proofRequests = $query
            ->orderByRaw("FIELD(status, 'in_progress', 'assigned', 'completed')")
            ->orderBy('deadline')
            ->get()
            ->map(function ($pr) {
                $pja100 = ProjectJobAssignment::where('project_job_id', $pr->project_job_id)
                    ->where('user_id', $pr->proofreader_id)
                    ->where('sender_id', $pr->proof_coordinator_id)
                    ->latest()->first();

                $pja101    = null;
                $workSlots = [];
                if ($pja100) {
                    $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                        ->where(function ($q) use ($pja100) {
                            $q->where('coordinator_assignment_id', $pja100->id)
                              ->orWhere('supersedes_assignment_id', $pja100->id);
                        })->latest()->first();

                    if ($pja101) {
                        $workSlots = Event::where('project_job_assignment_id', $pja101->id)
                            ->orderBy('starts_at')
                            ->get()
                            ->map(fn ($ev) => [
                                'date'      => $ev->starts_at->timezone('Asia/Tokyo')->toDateString(),
                                'startTime' => $ev->starts_at->timezone('Asia/Tokyo')->format('H:i'),
                                'endTime'   => $ev->ends_at->timezone('Asia/Tokyo')->format('H:i'),
                            ])->toArray();
                    }
                }

                return [
                    'id'             => $pr->id,
                    'title'          => $pr->title,
                    'status'         => $pr->status,
                    'deadline'       => $pr->deadline?->toIso8601String(),
                    'requester_name' => $pr->requester?->name,
                    'job_title'      => $pr->projectJob?->title,
                    'client_name'    => $pr->projectJob?->client?->name,
                    'is_set'         => $pja101 !== null,
                    'work_slots'     => $workSlots,
                ];
            })->values()->toArray();

        // クライアント絞り込み用リスト
        $clients = \App\Models\Client::whereIn('id',
            ProofRequest::where('proofreader_id', $user->id)
                ->whereNotNull('project_job_id')
                ->join('project_jobs', 'project_jobs.id', '=', 'proof_requests.project_job_id')
                ->pluck('project_jobs.client_id')
                ->unique()
                ->filter()
        )->orderBy('name')->get(['id', 'name']);

        return Inertia::render('User/ProofJobs/Index', [
            'proofRequests' => $proofRequests,
            'q'             => $q,
            'hideCompleted' => $hideCompleted,
            'period'        => $periodModel ?? '',
            'clientId'      => $clientId ? (int) $clientId : null,
            'clients'       => $clients,
            'monthOptions'  => $monthOptions,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  詳細
    // ──────────────────────────────────────────────────────
    public function show(ProofRequest $proofRequest): Response
    {
        $user = Auth::user();
        abort_if($proofRequest->proofreader_id !== $user->id, 403);

        $proofRequest->load(['requester', 'proofCoordinator', 'projectJob.client']);

        // pja100 取得
        $pja100 = ProjectJobAssignment::with([
            'user', 'sender', 'projectJob.client',
            'statusModel', 'workItemType', 'size', 'stage', 'difficultyModel',
        ])
            ->where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        // pja101 取得
        $pja101 = null;
        if ($pja100) {
            $pja101 = ProjectJobAssignment::with([
                'user', 'sender', 'projectJob.client',
                'statusModel', 'workItemType', 'size', 'stage', 'difficultyModel',
            ])
                ->whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($pja100) {
                    $q->where('coordinator_assignment_id', $pja100->id)
                      ->orWhere('supersedes_assignment_id', $pja100->id);
                })->latest()->first();
        }

        // 作業イベント取得（pja101 のイベント）
        $events = [];
        if ($pja101) {
            $events = Event::where('project_job_assignment_id', $pja101->id)
                ->orderBy('starts_at')
                ->get()
                ->toArray();
        }

        // ProofRequest が完了済みか
        $isCompleted = $proofRequest->status === 'completed';

        return Inertia::render('User/ProofJobs/Show', [
            'proofRequest' => [
                'id'              => $proofRequest->id,
                'title'           => $proofRequest->title,
                'status'          => $proofRequest->status,
                'deadline'        => $proofRequest->deadline?->toIso8601String(),
                'note'            => $proofRequest->note,
                'requester_name'  => $proofRequest->requester?->name,
                'coordinator_name'=> $proofRequest->proofCoordinator?->name,
                'job_title'       => $proofRequest->projectJob?->title,
                'is_completed'    => $isCompleted,
            ],
            'pja100'     => $pja100,
            'assignment' => $pja101,   // MyJobBox/Show.vue と同じ prop 名
            'projectJob' => $pja100?->projectJob,
            'events'     => $events,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  完了
    // ──────────────────────────────────────────────────────
    public function complete(ProofRequest $proofRequest)
    {
        $user = Auth::user();
        abort_if($proofRequest->proofreader_id !== $user->id, 403);

        if ($proofRequest->status === 'completed') {
            return back()->with('error', 'この校正依頼はすでに完了済みです。');
        }

        $proofRequest->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // 元ジョブ（pja_operator）に校正済みマークを付与
        if ($proofRequest->project_job_assignment_id) {
            ProjectJobAssignment::where('id', $proofRequest->project_job_assignment_id)
                ->whereNull('proof_completed_at')
                ->update(['proof_completed_at' => now()]);
        }

        // pja100（校正割当ジョブ）を完了にする → 進行表の proof_user セルに反映
        if ($proofRequest->proofreader_id && $proofRequest->proof_coordinator_id) {
            ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
                ->where('user_id', $proofRequest->proofreader_id)
                ->where('sender_id', $proofRequest->proof_coordinator_id)
                ->whereColumn('sender_id', '!=', 'user_id')
                ->update(['completed' => true]);
        }

        // 依頼者（requester）に完了通知
        \App\Services\JobNotificationService::notifyProofCompleted($user, $proofRequest->fresh());

        return redirect()->route('user.proof_jobs.index')
            ->with('success', '校正が完了しました。依頼者に通知しました。');
    }

    // ──────────────────────────────────────────────────────
    //  セットページ（フォーム表示）
    // ──────────────────────────────────────────────────────
    public function setPage(ProofRequest $proofRequest): Response
    {
        $user = Auth::user();
        abort_if($proofRequest->proofreader_id !== $user->id, 403);

        // pja100 を取得
        $pja100 = ProjectJobAssignment::with(['projectJob.client', 'user', 'statusModel'])
            ->where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        // 既存の作業スロット取得
        $existingSlots = [];
        if ($pja100) {
            $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($pja100) {
                    $q->where('coordinator_assignment_id', $pja100->id)
                      ->orWhere('supersedes_assignment_id', $pja100->id);
                })->latest()->first();

            if ($pja101) {
                $existingSlots = Event::where('project_job_assignment_id', $pja101->id)
                    ->orderBy('starts_at')
                    ->get()
                    ->map(fn ($ev) => [
                        'date'        => $ev->starts_at->timezone('Asia/Tokyo')->toDateString(),
                        'startHour'   => $ev->starts_at->timezone('Asia/Tokyo')->format('H'),
                        'startMinute' => $ev->starts_at->timezone('Asia/Tokyo')->format('i'),
                        'endHour'     => $ev->ends_at->timezone('Asia/Tokyo')->format('H'),
                        'endMinute'   => $ev->ends_at->timezone('Asia/Tokyo')->format('i'),
                    ])->toArray();
            }
        }

        $types        = \App\Models\WorkItemType::orderBy('sort_order')->get(['id', 'name', 'group']);
        $sizes        = \App\Models\Size::orderBy('sort_order')->get(['id', 'name', 'group']);
        $stages       = \App\Models\Stage::orderBy('sort_order')->get(['id', 'name']);
        $statuses     = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'key']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);
        $companies    = \App\Models\Company::orderBy('name')->get(['id', 'name']);

        $assignmentData = null;
        if ($pja100) {
            $arr = $pja100->toArray();
            $arr['_client_id'] = (string) ($pja100->projectJob?->client_id ?? '');
            $assignmentData = $arr;
        }

        return Inertia::render('User/ProofJobs/Set', [
            'proofRequest' => [
                'id'          => $proofRequest->id,
                'title'       => $proofRequest->title,
                'deadline'    => $proofRequest->deadline?->toIso8601String(),
                'status'      => $proofRequest->status,
                'note'        => $proofRequest->note,
                'requester'   => $proofRequest->requester,
                'project_job' => $proofRequest->projectJob,
            ],
            'assignment'        => $pja100,
            'projectJob'        => $pja100?->projectJob,
            'members'           => [['id' => $user->id, 'name' => $user->name]],
            'assignments_data'  => $assignmentData ? [$assignmentData] : [],
            'existingSlots'     => $existingSlots,
            'types'             => $types,
            'sizes'             => $sizes,
            'stages'            => $stages,
            'statuses'          => $statuses,
            'difficulties'      => $difficulties,
            'companies'         => $companies,
            'user_role'         => $user->user_role,
            'user_company_id'   => $user->company_id,
            'user_department_id' => $user->department_id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  セット保存
    // ──────────────────────────────────────────────────────
    public function set(Request $request, ProofRequest $proofRequest)
    {
        $user = Auth::user();
        abort_if($proofRequest->proofreader_id !== $user->id, 403);

        $slots = $request->input('work_slots', []);

        $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        if (! $pja100) {
            return back()->with('error', '割り当て情報が見つかりません。');
        }

        // pja101 取得または作成
        $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
            ->where(function ($q) use ($pja100) {
                $q->where('coordinator_assignment_id', $pja100->id)
                  ->orWhere('supersedes_assignment_id', $pja100->id);
            })->latest()->first();

        if (! $pja101) {
            $pja101 = ProjectJobAssignment::create([
                'project_job_id'            => $proofRequest->project_job_id,
                'user_id'                   => $user->id,
                'sender_id'                 => $user->id,
                'coordinator_assignment_id' => $pja100->id,
                'job_type'                  => 'proof',
                'title'                => $proofRequest->title,
                'scheduled'            => ! empty($slots),
                'scheduled_at'         => ! empty($slots) ? now() : null,
            ]);
        } else {
            $pja101->update([
                'scheduled'    => ! empty($slots),
                'scheduled_at' => ! empty($slots) ? now() : $pja101->scheduled_at,
            ]);
        }

        // 既存イベント・ProofSchedule 削除 → 再作成
        Event::where('project_job_assignment_id', $pja101->id)->delete();
        ProofSchedule::where('proof_request_id', $proofRequest->id)
            ->where('user_id', $user->id)
            ->delete();

        foreach ($slots as $slot) {
            if (empty($slot['date'])) continue;

            $date = $slot['date'];
            $sH   = str_pad($slot['startHour'],   2, '0', STR_PAD_LEFT);
            $sM   = str_pad($slot['startMinute'], 2, '0', STR_PAD_LEFT);
            $eH   = str_pad($slot['endHour'],     2, '0', STR_PAD_LEFT);
            $eM   = str_pad($slot['endMinute'],   2, '0', STR_PAD_LEFT);

            $startsAt = \Carbon\Carbon::parse("{$date} {$sH}:{$sM}:00", 'Asia/Tokyo')->utc();
            $endsAt   = \Carbon\Carbon::parse("{$date} {$eH}:{$eM}:00", 'Asia/Tokyo')->utc();

            $ev = Event::create([
                'user_id'                   => $user->id,
                'project_job_assignment_id' => $pja101->id,
                'date'                      => $date,
                'start'                     => "{$date} {$sH}:{$sM}:00",
                'end'                       => "{$date} {$eH}:{$eM}:00",
                'starts_at'                 => $startsAt,
                'ends_at'                   => $endsAt,
                'title'                     => $proofRequest->title,
            ]);

            ProofSchedule::create([
                'proof_request_id' => $proofRequest->id,
                'user_id'          => $user->id,
                'starts_at'        => $startsAt,
                'ends_at'          => $endsAt,
                'event_id'         => $ev->id,
            ]);
        }

        if ($proofRequest->status === 'assigned' && ! empty($slots)) {
            $proofRequest->update(['status' => 'in_progress']);
        }

        return redirect()->route('user.proof_jobs.index')
            ->with('success', '校正をセットしました。');
    }
}

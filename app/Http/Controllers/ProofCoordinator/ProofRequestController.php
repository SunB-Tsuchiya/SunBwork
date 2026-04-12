<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ProofRequest;
use App\Models\ProofSchedule;
use App\Models\ProofTeamMember;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use App\Services\JobNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProofRequestController extends Controller
{
    // =====================================================
    //  管理者向け（ProofCoordinator / Admin / SuperAdmin / 部署Leader）
    // =====================================================

    /**
     * GET /proof-coordinator/inbox
     * 未受理（pending）の校正依頼一覧
     */
    public function inbox(): Response
    {
        $requests = ProofRequest::with(['requester', 'projectJob'])
            ->pending()
            ->orderBy('deadline')
            ->orderBy('created_at')
            ->get();

        return Inertia::render('ProofCoordinator/Inbox/Index', [
            'proofRequests' => $requests,
        ]);
    }

    /**
     * GET /proof-coordinator/inbox/{proofRequest}/assign
     * 受理＋割り当てフォームページ（AssignmentForm.vue を流用）
     */
    public function assignPage(ProofRequest $proofRequest): Response
    {
        $proofRequest->load(['requester', 'projectJob.client', 'projectJobAssignment']);

        // 校正員候補: 校正チームに登録されているメンバーのみ
        $teamUserIds = ProofTeamMember::pluck('user_id');
        $members = User::whereIn('id', $teamUserIds)
            ->with('assignment:id,name,code')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'                    => $u->id,
                'name'                  => $u->name,
                'assignment_name'       => $u->assignment?->name,
                'employment_type'       => $u->employment_type ?? 'regular',
                'employment_type_label' => $u->employmentTypeLabel(),
            ]);

        // ルックアップリスト
        $types       = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes       = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages      = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses    = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);

        $user = Auth::user();

        // ソース割当（依頼者のマイジョブ）から prefill を構築
        // 担当者（user_id）と作業開始時刻（desired_time）以外はすべて引き継ぐ
        // ジョブ名はソースタイトル + "_校正"
        $src = $proofRequest->projectJobAssignment;
        $baseTitle = $src?->title ?? $proofRequest->title;
        $prefill = [
            'project_job_id'    => $proofRequest->project_job_id,
            '_client_id'        => $proofRequest->projectJob?->client?->id ?? '',
            'title'             => $baseTitle . '_校正',
            'detail'            => $src?->detail ?? '',
            'work_item_type_id' => $src?->work_item_type_id ?? null,
            'size_id'           => $src?->size_id ?? null,
            'stage_id'          => $src?->stage_id ?? null,
            'difficulty_id'     => $src?->difficulty_id ?? null,
            'estimated_hours'   => $src?->estimated_hours ?? null,
            'amounts'           => $src?->amounts ?? null,
            'amounts_unit'      => $src?->amounts_unit ?? 'page',
            'desired_end_date'  => $src?->desired_end_date
                ? (is_string($src->desired_end_date) ? $src->desired_end_date : $src->desired_end_date->format('Y-m-d'))
                : null,
            // department_id をセットすることで hasDepartment() が true になりドロップダウンが有効になる
            'company_id'        => $user->company_id,
            'department_id'     => $user->department_id,
            // 作業開始時刻は引き継がない（校正員が自分でセット）
            'desired_time'      => null,
            'status_id'         => null,
            'user_id'           => null,
        ];

        return Inertia::render('ProofCoordinator/Inbox/Assign', [
            'proofRequest'   => $proofRequest,
            'projectJob'     => $proofRequest->projectJob,
            'members'        => $members,
            'assignments'    => [$prefill],
            'types'          => $types,
            'sizes'          => $sizes,
            'stages'         => $stages,
            'statuses'       => $statuses,
            'difficulties'   => $difficulties,
            'companies'      => [],
            'user_role'      => $user->user_role,
            'user_company_id'    => $user->company_id,
            'user_department_id' => $user->department_id,
        ]);
    }

    /**
     * POST /proof-coordinator/inbox/{proofRequest}/assign
     * 受理＋校正員割り当て＋ジョブ登録を一括実行
     */
    public function assignStore(Request $request, ProofRequest $proofRequest)
    {
        if (! $proofRequest->isPending()) {
            return redirect()->route('proof_coordinator.inbox')
                ->with('error', 'この依頼はすでに受理済みです。');
        }

        $data = $request->validate([
            'assignments'                     => 'required|array|min:1',
            'assignments.*.title'             => 'required|string|max:255',
            'assignments.*.detail'            => 'nullable|string',
            'assignments.*.user_id'           => 'required|exists:users,id',
            'assignments.*.project_job_id'    => 'nullable|exists:project_jobs,id',
            'assignments.*.difficulty_id'     => 'nullable|exists:difficulties,id',
            'assignments.*.desired_end_date'  => 'nullable|date',
            'assignments.*.desired_time'      => 'nullable|date_format:H:i',
            'assignments.*.estimated_hours'   => 'nullable|numeric|min:0',
            'assignments.*.work_item_type_id' => 'nullable|exists:work_item_types,id',
            'assignments.*.size_id'           => 'nullable|exists:sizes,id',
            'assignments.*.stage_id'          => 'nullable|exists:stages,id',
            'assignments.*.company_id'        => 'nullable|exists:companies,id',
            'assignments.*.department_id'     => 'nullable|exists:departments,id',
            'assignments.*.amounts'           => 'nullable|integer|min:0',
            'assignments.*.amounts_unit'      => 'nullable|string|in:page,file',
            'assignments.*.sender_id'         => 'nullable|exists:users,id',
        ]);

        $a = $data['assignments'][0];
        $senderUser = Auth::user();

        DB::transaction(function () use ($a, $proofRequest, $senderUser) {
            // ジョブ割当を作成（校正管理者 = sender）
            $assignment = \App\Models\ProjectJobAssignment::create([
                'project_job_id'    => $proofRequest->project_job_id,
                'user_id'           => $a['user_id'],
                'sender_id'         => $senderUser->id,
                'title'             => $a['title'],
                'detail'            => $a['detail'] ?? null,
                'difficulty_id'     => $a['difficulty_id'] ?? null,
                'desired_end_date'  => $a['desired_end_date'] ?? null,
                'desired_time'      => $a['desired_time'] ?? null,
                'estimated_hours'   => $a['estimated_hours'] ?? null,
                'work_item_type_id' => $a['work_item_type_id'] ?? null,
                'size_id'           => $a['size_id'] ?? null,
                'stage_id'          => $a['stage_id'] ?? null,
                'company_id'        => $a['company_id'] ?? null,
                'department_id'     => $a['department_id'] ?? null,
                'amounts'           => $a['amounts'] ?? null,
                'amounts_unit'      => $a['amounts_unit'] ?? null,
                'status_id'         => 1,
            ]);

            // 校正依頼を受理済みに更新
            $proofRequest->update([
                'proof_coordinator_id' => $senderUser->id,
                'proofreader_id'       => $a['user_id'],
                'status'               => 'assigned',
            ]);

            // 校正員への通知（既存の notifyProofAssigned を流用）
            JobNotificationService::notifyProofAssigned($senderUser, $proofRequest->fresh());
        });

        // work_slots 処理
        $rawSlots = $request->input('work_slots', []);
        if (is_array($rawSlots) && count($rawSlots) > 0) {
            $this->saveWorkSlots($proofRequest->fresh(), $rawSlots, false);
        }

        return redirect()->route('proof_coordinator.inbox')
            ->with('success', '校正依頼を受理し、ジョブを割り当てました。');
    }

    /**
     * POST /proof-coordinator/inbox/{proofRequest}/accept
     * 依頼を受理（proof_coordinator_id に自分をセット）
     * @deprecated assignStore に統合。後方互換のため残存。
     */
    public function accept(ProofRequest $proofRequest)
    {
        if (! $proofRequest->isPending()) {
            return back()->with('error', 'この依頼はすでに受理済みです。');
        }

        $proofRequest->update([
            'proof_coordinator_id' => Auth::id(),
        ]);

        return back()->with('success', '校正依頼を受理しました。');
    }

    /**
     * GET /proof-coordinator/assignments/{proofRequest}/edit
     * アサインフォームを使った編集ページ（assignPage と同構造）
     */
    public function edit(ProofRequest $proofRequest): Response
    {
        $proofRequest->load(['requester', 'proofCoordinator', 'projectJob.client', 'projectJobAssignment']);

        // 受理時に作成したコーディネーター側の割当を取得
        $assignment = null;
        if ($proofRequest->proofreader_id && $proofRequest->proof_coordinator_id) {
            $assignment = ProjectJobAssignment::with([
                'workItemType', 'size', 'stage', 'difficultyModel', 'statusModel', 'user',
            ])
                ->where('project_job_id', $proofRequest->project_job_id)
                ->where('user_id', $proofRequest->proofreader_id)
                ->where('sender_id', $proofRequest->proof_coordinator_id)
                ->latest()
                ->first();
        }

        $teamUserIds = ProofTeamMember::pluck('user_id');
        $members = User::where(function ($q) use ($teamUserIds) {
            $q->whereHas('assignment', fn($q2) => $q2->where('code', 'kousei'))
              ->orWhereIn('id', $teamUserIds);
        })
            ->with('assignment:id,name,code')
            ->orderBy('name')
            ->get()
            ->map(fn($u) => [
                'id'                    => $u->id,
                'name'                  => $u->name,
                'assignment_name'       => $u->assignment?->name,
                'employment_type'       => $u->employment_type ?? 'regular',
                'employment_type_label' => $u->employmentTypeLabel(),
            ]);

        $types        = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes        = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages       = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses     = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);

        $user = Auth::user();

        // 既存割当をフォーム用の形式に変換
        $assignmentData = $assignment ? [
            'id'                => $assignment->id,
            'project_job_id'    => $assignment->project_job_id,
            '_client_id'        => $proofRequest->projectJob?->client?->id ?? '',
            'title'             => $assignment->title,
            'detail'            => $assignment->detail ?? '',
            'work_item_type_id' => $assignment->work_item_type_id,
            'size_id'           => $assignment->size_id,
            'stage_id'          => $assignment->stage_id,
            'difficulty_id'     => $assignment->difficulty_id,
            'estimated_hours'   => $assignment->estimated_hours,
            'amounts'           => $assignment->amounts,
            'amounts_unit'      => $assignment->amounts_unit ?? 'page',
            'desired_end_date'  => $assignment->desired_end_date
                ? (is_string($assignment->desired_end_date) ? $assignment->desired_end_date : $assignment->desired_end_date->format('Y-m-d'))
                : null,
            'desired_time'      => $assignment->desired_time,
            'status_id'         => $assignment->status_id,
            'user_id'           => $assignment->user_id,
            'company_id'        => $user->company_id,
            'department_id'     => $user->department_id,
        ] : null;

        // 割当ジョブに紐づくイベント（作業時間）を取得
        // 校正員は pja100 を直接使わず source_assignment_id=pja100.id のマイジョブ（pja101）を作って
        // そちらに events を登録するため、連動するジョブの assignment_id も対象に含める
        $events = [];
        if ($assignment) {
            // pja100 自体、または pja100 を source とする自己割当ジョブの ID を収集
            $linkedIds = collect([$assignment->id]);

            $linked = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($assignment) {
                    $q->where('source_assignment_id', $assignment->id)
                      ->orWhere('supersedes_assignment_id', $assignment->id);
                })
                ->pluck('id');

            $linkedIds = $linkedIds->merge($linked)->unique()->values();

            $events = Event::whereIn('project_job_assignment_id', $linkedIds)
                ->orderBy('starts_at')
                ->get()
                ->map(fn($e) => [
                    'id'                   => $e->id,
                    'date'                 => $e->starts_at ? $e->starts_at->setTimezone('Asia/Tokyo')->toDateString() : null,
                    'start_hour'           => $e->starts_at ? $e->starts_at->setTimezone('Asia/Tokyo')->format('H') : '00',
                    'start_minute'         => $e->starts_at ? $e->starts_at->setTimezone('Asia/Tokyo')->format('i') : '00',
                    'end_hour'             => $e->ends_at   ? $e->ends_at->setTimezone('Asia/Tokyo')->format('H')   : '00',
                    'end_minute'           => $e->ends_at   ? $e->ends_at->setTimezone('Asia/Tokyo')->format('i')   : '00',
                    'interruption_minutes' => $e->interruption_minutes ?? 0,
                ])
                ->all();
        }

        // ユーザーが自分でスケジュールをセット済みか確認（pja101 の存在チェック）
        $userHasSetSchedule = false;
        if ($assignment) {
            $userHasSetSchedule = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($assignment) {
                    $q->where('source_assignment_id', $assignment->id)
                      ->orWhere('supersedes_assignment_id', $assignment->id);
                })
                ->exists();
        }

        return Inertia::render('ProofCoordinator/Assignments/Edit', [
            'proofRequest'       => $proofRequest,
            'projectJob'         => $proofRequest->projectJob,
            'members'            => $members,
            'assignments'        => $assignmentData ? [$assignmentData] : [],
            'types'              => $types,
            'sizes'              => $sizes,
            'stages'             => $stages,
            'statuses'           => $statuses,
            'difficulties'       => $difficulties,
            'companies'          => [],
            'user_role'          => $user->user_role,
            'user_company_id'    => $user->company_id,
            'user_department_id' => $user->department_id,
            'workEvents'         => $events,
            'userHasSetSchedule' => $userHasSetSchedule,
        ]);
    }

    /**
     * PUT /proof-coordinator/assignments/{proofRequest}/assignment
     * アサインフォームからの更新（coordinator.project_jobs.assignments.update の代替）
     * 保存後に proof_coordinator.assignments.show へリダイレクト
     */
    public function assignmentUpdate(Request $request, ProofRequest $proofRequest)
    {
        // 対象の ProjectJobAssignment を特定
        $assignment = null;
        if ($proofRequest->proofreader_id && $proofRequest->proof_coordinator_id) {
            $assignment = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
                ->where('user_id', $proofRequest->proofreader_id)
                ->where('sender_id', $proofRequest->proof_coordinator_id)
                ->latest()
                ->first();
        }

        if (! $assignment) {
            return back()->with('error', '割り当てジョブが見つかりません。');
        }

        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'detail'            => 'nullable|string',
            'user_id'           => 'nullable|exists:users,id',
            'difficulty_id'     => 'nullable|exists:difficulties,id',
            'desired_end_date'  => 'nullable|date',
            'desired_time'      => 'nullable|date_format:H:i',
            'estimated_hours'   => 'nullable|numeric|min:0',
            'work_item_type_id' => 'nullable|exists:work_item_types,id',
            'size_id'           => 'nullable|exists:sizes,id',
            'stage_id'          => 'nullable|exists:stages,id',
            'status_id'         => 'nullable|exists:statuses,id',
            'company_id'        => 'nullable|exists:companies,id',
            'department_id'     => 'nullable|exists:departments,id',
            'amounts'           => 'nullable|integer|min:0',
            'amounts_unit'      => 'nullable|string|in:page,file',
            'send_immediately'  => 'nullable|boolean',
        ]);

        $assignment->update([
            'title'             => $data['title'],
            'detail'            => $data['detail'] ?? null,
            'user_id'           => $data['user_id'] ?? $assignment->user_id,
            'difficulty_id'     => $data['difficulty_id'] ?? null,
            'desired_end_date'  => $data['desired_end_date'] ?? null,
            'desired_time'      => $data['desired_time'] ?? null,
            'estimated_hours'   => $data['estimated_hours'] ?? null,
            'work_item_type_id' => $data['work_item_type_id'] ?? null,
            'size_id'           => $data['size_id'] ?? null,
            'stage_id'          => $data['stage_id'] ?? null,
            'status_id'         => $data['status_id'] ?? null,
            'company_id'        => $data['company_id'] ?? null,
            'department_id'     => $data['department_id'] ?? null,
            'amounts'           => $data['amounts'] ?? null,
            'amounts_unit'      => $data['amounts_unit'] ?? null,
        ]);

        // work_slots があれば既存を削除して再作成
        $rawSlots = $request->input('work_slots', []);
        if (is_array($rawSlots) && count($rawSlots) > 0) {
            $this->saveWorkSlots($proofRequest, $rawSlots, true);
        }

        return redirect()->route('proof_coordinator.assignments.show', $proofRequest)
            ->with('success', 'ジョブ割り当てを更新しました。');
    }

    /**
     * GET /proof-coordinator/assignments/{proofRequest}
     * 校正依頼の詳細（マイジョブ Show と同等）
     */
    public function show(ProofRequest $proofRequest): Response
    {
        $proofRequest->load([
            'requester',
            'proofCoordinator',
            'proofreader',
            'projectJob.client',
            'projectJobAssignment', // 依頼元の割当（ソース）
        ]);

        // 受理時に作成したコーディネーター側の割当を取得
        $assignment = null;
        if ($proofRequest->proofreader_id && $proofRequest->proof_coordinator_id) {
            $assignment = ProjectJobAssignment::with([
                'user',
                'sender',
                'projectJob.client',
                'statusModel',
                'workItemType',
                'size',
                'stage',
                'difficultyModel',
            ])
                ->where('project_job_id', $proofRequest->project_job_id)
                ->where('user_id', $proofRequest->proofreader_id)
                ->where('sender_id', $proofRequest->proof_coordinator_id)
                ->latest()
                ->first();
        }

        // 校正員が自分でセットしたマイジョブ（pja101）からイベント（実作業時間）と設定日時を取得
        $proofreaderSchedule = null;
        if ($assignment) {
            $selfJob = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($assignment) {
                    $q->where('source_assignment_id', $assignment->id)
                      ->orWhere('supersedes_assignment_id', $assignment->id);
                })
                ->latest()
                ->first();

            if ($selfJob) {
                $tz = 'Asia/Tokyo';

                $workSlots = Event::where('project_job_assignment_id', $selfJob->id)
                    ->orderBy('starts_at')
                    ->get()
                    ->map(fn($e) => [
                        'date'  => $e->starts_at ? $e->starts_at->setTimezone($tz)->format('Y年n月j日') : null,
                        'start' => $e->starts_at ? $e->starts_at->setTimezone($tz)->format('H:i') : null,
                        'end'   => $e->ends_at   ? $e->ends_at->setTimezone($tz)->format('H:i')   : null,
                    ])
                    ->all();

                $proofreaderSchedule = [
                    'work_slots'   => $workSlots,
                    'scheduled_at' => $selfJob->scheduled_at
                        ? \Carbon\Carbon::parse($selfJob->scheduled_at)->setTimezone($tz)->format('Y年n月j日 H:i')
                        : \Carbon\Carbon::parse($selfJob->created_at)->setTimezone($tz)->format('Y年n月j日 H:i'),
                ];
            }
        }

        return Inertia::render('ProofCoordinator/Assignments/Show', [
            'proofRequest'       => $proofRequest,
            'assignment'         => $assignment,
            'proofreaderSchedule'=> $proofreaderSchedule,
        ]);
    }

    /**
     * GET /proof-coordinator/assignments
     * 割り振り管理（accepted / assigned / in_progress）
     */
    public function assignments(): Response
    {
        $requests = ProofRequest::with(['requester', 'proofCoordinator', 'proofreader', 'projectJob'])
            ->active()
            ->orderBy('deadline')
            ->get();

        // 校正員候補（担当コードが kousei OR 校正チームメンバー）
        $teamUserIds = ProofTeamMember::pluck('user_id');
        $proofreaders = User::where(function ($q) use ($teamUserIds) {
            $q->whereHas('assignment', fn($q2) => $q2->where('code', 'kousei'))
              ->orWhereIn('id', $teamUserIds);
        })
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ProofCoordinator/Assignments/Index', [
            'proofRequests' => $requests,
            'proofreaders'  => $proofreaders,
        ]);
    }

    /**
     * PUT /proof-coordinator/assignments/{proofRequest}/assign
     * 校正員を割り当て → status = assigned
     */
    public function assign(Request $request, ProofRequest $proofRequest)
    {
        $data = $request->validate([
            'proofreader_id' => ['required', 'exists:users,id'],
        ]);

        $proofRequest->update([
            'proofreader_id' => $data['proofreader_id'],
            'status'         => 'assigned',
        ]);

        // PCあり校正員への通知
        $proofreader = User::find($data['proofreader_id']);
        if ($proofreader) {
            JobNotificationService::notifyProofAssigned(Auth::user(), $proofRequest->fresh());
        }

        return back()->with('success', '校正員を割り当てました。');
    }

    /**
     * PUT /proof-coordinator/assignments/{proofRequest}/start
     * 作業開始 → status = in_progress
     */
    public function start(ProofRequest $proofRequest)
    {
        $proofRequest->update(['status' => 'in_progress']);

        return back()->with('success', '校正を開始しました。');
    }

    /**
     * PUT /proof-coordinator/assignments/{proofRequest}/complete
     * 完了（PCなし校正員の代理完了も含む）→ status = completed
     */
    public function complete(Request $request, ProofRequest $proofRequest)
    {
        $proofRequest->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        // 依頼者への完了通知
        JobNotificationService::notifyProofCompleted(Auth::user(), $proofRequest->fresh());

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'status' => 'completed']);
        }

        return back()->with('success', '校正が完了しました。依頼者に通知しました。');
    }

    /**
     * GET /proof-coordinator/calendar
     * 管理者向け詳細カレンダー（担当者情報含む）
     */
    public function calendar(): Response
    {
        $requests = ProofRequest::with(['requester', 'proofreader', 'projectJob'])
            ->whereNotNull('deadline')
            ->whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->get()
            ->map(fn($r) => [
                'id'             => $r->id,
                'title'          => $r->title,
                'start'          => $r->deadline->toDateString(),
                'status'         => $r->status,
                'requester'      => $r->requester?->name,
                'proofreader'    => $r->proofreader?->name,
                'project_job'    => $r->projectJob?->title,
            ]);

        return Inertia::render('ProofCoordinator/Calendar/Index', [
            'events' => $requests,
        ]);
    }

    /**
     * GET /proof-coordinator/workload
     * 校正員ごとの作業量（月別）
     */
    public function workload(Request $request): Response
    {
        $yearMonth = $request->input('year_month', now()->format('Y-m'));

        [$year, $month] = explode('-', $yearMonth);

        $teamUserIds2 = ProofTeamMember::pluck('user_id');
        $proofreaders = User::where(function ($q) use ($teamUserIds2) {
            $q->whereHas('assignment', fn($q2) => $q2->where('code', 'kousei'))
              ->orWhereIn('id', $teamUserIds2);
        })
            ->withCount([
                'proofRequestsAsProofreader as total_count' => fn($q) => $q
                    ->whereYear('deadline', $year)->whereMonth('deadline', $month),
                'proofRequestsAsProofreader as completed_count' => fn($q) => $q
                    ->where('status', 'completed')
                    ->whereYear('deadline', $year)->whereMonth('deadline', $month),
                'proofRequestsAsProofreader as active_count' => fn($q) => $q
                    ->whereIn('status', ['assigned', 'in_progress'])
                    ->whereYear('deadline', $year)->whereMonth('deadline', $month),
            ])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ProofCoordinator/Workload/Index', [
            'proofreaders' => $proofreaders,
            'yearMonth'    => $yearMonth,
        ]);
    }

    /**
     * GET /proof-coordinator/history
     * 案件ごとの校正履歴（全件）
     */
    public function history(Request $request): Response
    {
        $query = ProofRequest::with(['requester', 'proofreader', 'projectJob'])
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('projectJob', fn($q2) => $q2->where('title', 'like', "%{$search}%"));
            });
        }

        $requests = $query->paginate(30)->withQueryString();

        return Inertia::render('ProofCoordinator/History/Index', [
            'proofRequests' => $requests,
            'search'        => $request->input('search', ''),
        ]);
    }

    // =====================================================
    //  全ロール共通
    // =====================================================

    /**
     * POST /proof-requests
     * 校正依頼作成（全ロール）
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'project_job_assignment_id' => ['nullable', 'exists:project_job_assignments,id'],
            'project_job_id'            => ['nullable', 'exists:project_jobs,id'],
            'title'                     => ['required', 'string', 'max:255'],
            'deadline'                  => ['required', 'date'],
            'note'                      => ['nullable', 'string', 'max:1000'],
        ]);

        $proofRequest = ProofRequest::create([
            ...$data,
            'requester_id' => Auth::id(),
            'status'       => 'pending',
        ]);

        // ProofCoordinator 全員に通知
        JobNotificationService::notifyProofRequested(Auth::user(), $proofRequest);

        return back()->with('success', '校正依頼を送信しました。');
    }

    /**
     * DELETE /proof-requests/{proofRequest}
     * 校正依頼キャンセル（依頼者本人 & pending 時のみ）
     */
    public function destroy(ProofRequest $proofRequest)
    {
        $user = Auth::user();

        if ($proofRequest->requester_id !== $user->id && ! $user->isAdmin() && ! $user->isSuperAdmin()) {
            abort(403);
        }

        if (! $proofRequest->isPending()) {
            return back()->with('error', '受理済みの依頼はキャンセルできません。');
        }

        $proofRequest->delete();

        return back()->with('success', '校正依頼をキャンセルしました。');
    }

    /**
     * GET /proof/calendar
     * 全員向け読み取り専用カレンダー
     */
    public function calendarPublic(): Response
    {
        $events = ProofRequest::with(['proofreader', 'projectJob'])
            ->whereNotNull('deadline')
            ->whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->get()
            ->map(fn($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'start'       => $r->deadline->toDateString(),
                'status'      => $r->status,
                'proofreader' => $r->proofreader?->name,
                'project_job' => $r->projectJob?->title,
            ]);

        return Inertia::render('Proof/Calendar', [
            'events' => $events,
        ]);
    }

    /**
     * GET /proof/status
     * 全員向けステータス一覧
     */
    public function statusPublic(Request $request): Response
    {
        $query = ProofRequest::with(['requester', 'proofreader', 'projectJob'])
            ->orderByDesc('deadline');

        // 自分の依頼のみ絞り込むオプション
        if ($request->boolean('mine')) {
            $query->where('requester_id', Auth::id());
        }

        $requests = $query->paginate(30)->withQueryString();

        return Inertia::render('Proof/Status', [
            'proofRequests' => $requests,
            'mine'          => $request->boolean('mine'),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────

    /**
     * work_slots から ProofSchedule と Event（pja101）を作成・更新する
     *
     * @param ProofRequest $proofRequest
     * @param array        $slots  [['date','startHour','startMinute','endHour','endMinute'], ...]
     * @param bool         $replace true = 既存エントリを削除してから再作成
     */
    private function saveWorkSlots(ProofRequest $proofRequest, array $slots, bool $replace = false): void
    {
        if (empty($slots)) return;

        if ($replace) {
            ProofSchedule::where('proof_request_id', $proofRequest->id)->delete();
        }

        // pja100 を特定
        $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
            ->where('user_id', $proofRequest->proofreader_id)
            ->where('sender_id', $proofRequest->proof_coordinator_id)
            ->latest()->first();

        $pja101 = null;
        if ($pja100) {
            $pja101 = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($pja100) {
                    $q->where('source_assignment_id', $pja100->id)
                      ->orWhere('supersedes_assignment_id', $pja100->id);
                })->latest()->first();

            if (! $pja101 && $proofRequest->proofreader_id) {
                $pja101 = ProjectJobAssignment::create([
                    'project_job_id'       => $proofRequest->project_job_id,
                    'user_id'              => $proofRequest->proofreader_id,
                    'sender_id'            => $proofRequest->proofreader_id,
                    'source_assignment_id' => $pja100->id,
                    'job_type'             => 'proof',
                    'title'                => $proofRequest->title,
                    'scheduled'            => true,
                    'scheduled_at'         => now(),
                ]);
            }

            if ($replace && $pja101) {
                Event::where('project_job_assignment_id', $pja101->id)->delete();
            }
        }

        foreach ($slots as $slot) {
            if (empty($slot['date'])) continue;

            $date = $slot['date'];
            $sH   = str_pad($slot['startHour'],   2, '0', STR_PAD_LEFT);
            $sM   = str_pad($slot['startMinute'], 2, '0', STR_PAD_LEFT);
            $eH   = str_pad($slot['endHour'],     2, '0', STR_PAD_LEFT);
            $eM   = str_pad($slot['endMinute'],   2, '0', STR_PAD_LEFT);

            $startsAt = \Carbon\Carbon::parse("{$date} {$sH}:{$sM}:00", 'Asia/Tokyo')->utc();
            $endsAt   = \Carbon\Carbon::parse("{$date} {$eH}:{$eM}:00", 'Asia/Tokyo')->utc();

            ProofSchedule::create([
                'proof_request_id' => $proofRequest->id,
                'user_id'          => $proofRequest->proofreader_id,
                'starts_at'        => $startsAt,
                'ends_at'          => $endsAt,
            ]);

            if ($pja101) {
                Event::create([
                    'user_id'                   => $proofRequest->proofreader_id,
                    'project_job_assignment_id' => $pja101->id,
                    'date'                      => $date,
                    'start'                     => "{$date} {$sH}:{$sM}:00",
                    'end'                       => "{$date} {$eH}:{$eM}:00",
                    'starts_at'                 => $startsAt,
                    'ends_at'                   => $endsAt,
                    'title'                     => $proofRequest->title,
                ]);
            }
        }
    }
}

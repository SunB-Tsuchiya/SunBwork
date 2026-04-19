<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\ProofRequest;
use App\Models\ProofSchedule;
use App\Models\ProofDispatcher;
use App\Models\ProofTeamMember;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\User;
use App\Services\JobNotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                'is_dispatcher'         => false,
                'dispatcher_id'         => null,
            ]);

        // ルックアップリスト
        $types       = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes       = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages      = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses    = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);

        $user = Auth::user();

        // 単発派遣（is_active=true）を members に追加
        $dispatchers = ProofDispatcher::active()
            ->when($user->user_role !== 'superadmin', fn ($q) => $q->forCompany($user->company_id))
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id'                    => 'dp_' . $d->id,
                'name'                  => $d->name,
                'assignment_name'       => '単発派遣',
                'employment_type'       => 'proof_dispatcher',
                'employment_type_label' => '単発派遣',
                'is_dispatcher'         => true,
                'dispatcher_id'         => $d->id,
            ]);
        $members = $members->concat($dispatchers);

        // ソース割当（依頼者のマイジョブ）から prefill を構築
        // 担当者（user_id）と作業開始時刻（desired_time）以外はすべて引き継ぐ
        // ジョブ名: 末尾が「-組版」なら「-校正」に置換、それ以外は「-校正」をハイフンで付加
        $src = $proofRequest->projectJobAssignment;
        $baseTitle = $src?->title ?? $proofRequest->title;
        $proofTitle = preg_match('/-組版$/u', $baseTitle)
            ? preg_replace('/-組版$/u', '-校正', $baseTitle)
            : $baseTitle . '-校正';
        $prefill = [
            'project_job_id'    => $proofRequest->project_job_id,
            '_client_id'        => $proofRequest->projectJob?->client?->id ?? '',
            'title'             => $proofTitle,
            'detail'            => $src?->detail ?? '',
            'work_item_type_id' => $src?->work_item_type_id ?? null,
            'size_id'           => $src?->size_id ?? null,
            'stage_id'          => $src?->stage_id ?? null,
            'difficulty_id'     => $src?->difficulty_id ?? null,
            'estimated_hours'   => $src?->estimated_hours ?? null,
            'amounts'           => $src?->amounts ?? null,
            'amounts_unit'      => $src?->amounts_unit ?? 'page',
            'desired_end_date'  => $proofRequest->deadline
                ? (is_string($proofRequest->deadline) ? substr($proofRequest->deadline, 0, 10) : $proofRequest->deadline->format('Y-m-d'))
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
            'assignments'                          => 'required|array|min:1',
            'assignments.*.title'                  => 'required|string|max:255',
            'assignments.*.detail'                 => 'nullable|string',
            'assignments.*.user_id'                => 'nullable|exists:users,id',
            'assignments.*.proof_dispatcher_id'    => 'nullable|exists:proof_dispatchers,id',
            'assignments.*.project_job_id'         => 'nullable|exists:project_jobs,id',
            'assignments.*.difficulty_id'          => 'nullable|exists:difficulties,id',
            'assignments.*.desired_end_date'       => 'nullable|date',
            'assignments.*.desired_time'           => 'nullable|date_format:H:i',
            'assignments.*.estimated_hours'        => 'nullable|numeric|min:0',
            'assignments.*.work_item_type_id'      => 'nullable|exists:work_item_types,id',
            'assignments.*.size_id'                => 'nullable|exists:sizes,id',
            'assignments.*.stage_id'               => 'nullable|exists:stages,id',
            'assignments.*.company_id'             => 'nullable|exists:companies,id',
            'assignments.*.department_id'          => 'nullable|exists:departments,id',
            'assignments.*.amounts'                => 'nullable|integer|min:0',
            'assignments.*.amounts_unit'           => 'nullable|string|in:page,file',
            'assignments.*.sender_id'              => 'nullable|exists:users,id',
        ]);

        $a = $data['assignments'][0];
        $senderUser = Auth::user();

        // 単発派遣が選択されている場合: user_id = coordinator 自身、proof_dispatcher_id を保存
        $isDispatcher   = ! empty($a['proof_dispatcher_id']);
        $assigneeUserId = $isDispatcher ? $senderUser->id : ($a['user_id'] ?? $senderUser->id);
        $dispatcherId   = $isDispatcher ? $a['proof_dispatcher_id'] : null;

        DB::transaction(function () use ($a, $proofRequest, $senderUser, $assigneeUserId, $dispatcherId, $isDispatcher) {
            // ジョブ割当を作成（校正管理者 = sender）
            $assignment = \App\Models\ProjectJobAssignment::create([
                'project_job_id'      => $proofRequest->project_job_id,
                'user_id'             => $assigneeUserId,
                'sender_id'           => $senderUser->id,
                'job_type'            => 'proof',
                'proof_dispatcher_id' => $dispatcherId,
                'title'               => $a['title'],
                'detail'              => $a['detail'] ?? null,
                'difficulty_id'       => $a['difficulty_id'] ?? null,
                'desired_end_date'    => $a['desired_end_date'] ?? null,
                'desired_time'        => $a['desired_time'] ?? null,
                'estimated_hours'     => $a['estimated_hours'] ?? null,
                'work_item_type_id'   => $a['work_item_type_id'] ?? null,
                'size_id'             => $a['size_id'] ?? null,
                'stage_id'            => $a['stage_id'] ?? null,
                'company_id'          => $a['company_id'] ?? null,
                'department_id'       => $a['department_id'] ?? null,
                'amounts'             => $a['amounts'] ?? null,
                'amounts_unit'        => $a['amounts_unit'] ?? null,
                'status_id'           => 1,
            ]);

            // 進行表セルとの双方向紐づけ
            if ($proofRequest->proof_cell_id) {
                // proof_cell_id が設定済みの場合（進行表の校正セルから依頼された）
                $assignment->update(['progress_cell_id' => $proofRequest->proof_cell_id]);
                \App\Models\ProgressCell::where('id', $proofRequest->proof_cell_id)
                    ->update([
                        'proof_assignment_id' => $assignment->id,
                        'value_user_id'       => $assigneeUserId,
                    ]);
            } elseif ($proofRequest->project_job_assignment_id) {
                // proof_cell_id が未設定の場合：pja_operatorの組版セルから校正セルを自動検索・作成
                $kumihanCell = \App\Models\ProgressCell::where('assignment_id', $proofRequest->project_job_assignment_id)->first();
                if ($kumihanCell && preg_match('/^(.+)_kumihan_toroku$/', $kumihanCell->col_key, $m)) {
                    $roundPrefix = $m[1];
                    $rowId       = $kumihanCell->row_id;

                    // round{N}_kosei_tanto（proof_user）セルを作成/取得して紐づけ
                    $koseiTantoCell = \App\Models\ProgressCell::firstOrCreate(
                        ['row_id' => $rowId, 'col_key' => $roundPrefix . '_kosei_tanto']
                    );
                    $koseiTantoCell->update([
                        'proof_assignment_id' => $assignment->id,
                        'value_user_id'       => $assigneeUserId,
                    ]);

                    // round{N}_kosei_toroku（joblink）セルを作成/取得して assignment_id を設定
                    $koseiTorokuCell = \App\Models\ProgressCell::firstOrCreate(
                        ['row_id' => $rowId, 'col_key' => $roundPrefix . '_kosei_toroku']
                    );
                    $koseiTorokuCell->update(['assignment_id' => $assignment->id]);

                    // pja100 と校正セルを紐づけ
                    $assignment->update(['progress_cell_id' => $koseiTantoCell->id]);

                    // ProofRequest.proof_cell_id を kosei_tanto セルに更新
                    $proofRequest->proof_cell_id = $koseiTantoCell->id;
                    $proofRequest->save();
                }
            }

            // pja_operator（元の組版ジョブ）に progress_cell_id が未設定の場合、
            // 同一行の kumihan_toroku セルを自動設定する
            if ($proofRequest->project_job_assignment_id) {
                $pjaOperator = ProjectJobAssignment::find($proofRequest->project_job_assignment_id);
                if ($pjaOperator && ! $pjaOperator->progress_cell_id) {
                    $kumihanCellForOp = \App\Models\ProgressCell::where('assignment_id', $pjaOperator->id)
                        ->whereNotNull('col_key')
                        ->first();
                    if ($kumihanCellForOp) {
                        $pjaOperator->update(['progress_cell_id' => $kumihanCellForOp->id]);
                    }
                }
            }

            // 校正依頼を受理済みに更新
            // 単発派遣の場合 proofreader_id は coordinator 自身（通知は不要）
            $proofRequest->update([
                'proof_coordinator_id' => $senderUser->id,
                'proofreader_id'       => $isDispatcher ? null : $assigneeUserId,
                'status'               => 'assigned',
            ]);

            // 校正員への通知（単発派遣の場合はスキップ）
            if (! $isDispatcher) {
                JobNotificationService::notifyProofAssigned($senderUser, $proofRequest->fresh());
            }
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
                'is_dispatcher'         => false,
                'dispatcher_id'         => null,
            ]);

        $types        = \App\Models\WorkItemType::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'company_id', 'department_id']);
        $sizes        = \App\Models\Size::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'group', 'width', 'height', 'unit', 'company_id', 'department_id']);
        $stages       = \App\Models\Stage::orderBy('sort_order')->orderBy('order_index')->get(['id', 'name', 'company_id', 'department_id']);
        $statuses     = \App\Models\Status::orderBy('sort_order')->get(['id', 'name', 'slug', 'company_id', 'department_id']);
        $difficulties = \App\Models\Difficulty::orderBy('sort_order')->get(['id', 'name']);

        $user = Auth::user();

        // 単発派遣（is_active=true）を members に追加
        $dispatchers = ProofDispatcher::active()
            ->when($user->user_role !== 'superadmin', fn ($q) => $q->forCompany($user->company_id))
            ->orderBy('name')
            ->get()
            ->map(fn ($d) => [
                'id'                    => 'dp_' . $d->id,
                'name'                  => $d->name,
                'assignment_name'       => '単発派遣',
                'employment_type'       => 'proof_dispatcher',
                'employment_type_label' => '単発派遣',
                'is_dispatcher'         => true,
                'dispatcher_id'         => $d->id,
            ]);
        $members = $members->concat($dispatchers);

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
        // 校正員は pja100 を直接使わず coordinator_assignment_id=pja100.id のマイジョブ（pja101）を作って
        // そちらに events を登録するため、連動するジョブの assignment_id も対象に含める
        $events = [];
        if ($assignment) {
            // pja100 自体、または pja100 を source とする自己割当ジョブの ID を収集
            $linkedIds = collect([$assignment->id]);

            $linked = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($assignment) {
                    $q->where('coordinator_assignment_id', $assignment->id)
                      ->orWhere('supersedes_assignment_id', $assignment->id);
                })
                ->pluck('id');

            $linkedIds = $linkedIds->merge($linked)->unique()->values();

            $events = Event::whereIn('project_job_assignment_id', $linkedIds)
                ->orderBy('starts_at')
                ->get()
                ->map(function ($e) {
                    $tz       = 'Asia/Tokyo';
                    $rawStart = $e->getRawOriginal('starts_at');
                    $rawEnd   = $e->getRawOriginal('ends_at');
                    $start    = $rawStart ? Carbon::createFromFormat('Y-m-d H:i:s', $rawStart, 'UTC')->setTimezone($tz) : null;
                    $end      = $rawEnd   ? Carbon::createFromFormat('Y-m-d H:i:s', $rawEnd,   'UTC')->setTimezone($tz) : null;
                    return [
                        'id'                   => $e->id,
                        'user_id'              => $e->user_id,
                        'date'                 => $start ? $start->toDateString() : null,
                        'start_hour'           => $start ? $start->format('H') : '00',
                        'start_minute'         => $start ? $start->format('i') : '00',
                        'end_hour'             => $end   ? $end->format('H')   : '00',
                        'end_minute'           => $end   ? $end->format('i')   : '00',
                        'interruption_minutes' => $e->interruption_minutes ?? 0,
                    ];
                })
                ->all();
        }

        // ユーザーが自分でスケジュールをセット済みか確認（pja101 の存在チェック）
        $userHasSetSchedule = false;
        if ($assignment) {
            $userHasSetSchedule = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where(function ($q) use ($assignment) {
                    $q->where('coordinator_assignment_id', $assignment->id)
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
     * DELETE /proof-coordinator/assignments/{proofRequest}/events/{event}
     * 校正コーディネーターが管理する作業時間（Event）を削除する。
     * 当該 ProofRequest のアサインチェーンに属するイベントのみ許可する。
     */
    public function destroyEvent(ProofRequest $proofRequest, Event $event): JsonResponse
    {
        // この ProofRequest に紐づく pja100 を取得
        $pja100 = null;
        if ($proofRequest->proofreader_id && $proofRequest->proof_coordinator_id) {
            $pja100 = ProjectJobAssignment::where('project_job_id', $proofRequest->project_job_id)
                ->where('user_id', $proofRequest->proofreader_id)
                ->where('sender_id', $proofRequest->proof_coordinator_id)
                ->latest()
                ->first();
        }

        if (! $pja100) {
            abort(404);
        }

        // pja100 および pja100 を源とする自己割当ジョブの ID セットを構築
        $linkedIds = collect([$pja100->id]);
        $linked = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
            ->where(function ($q) use ($pja100) {
                $q->where('coordinator_assignment_id', $pja100->id)
                  ->orWhere('supersedes_assignment_id', $pja100->id);
            })->pluck('id');
        $linkedIds = $linkedIds->merge($linked)->unique()->values()->all();

        if (! in_array($event->project_job_assignment_id, $linkedIds, false)) {
            abort(403, 'この作業時間の削除権限がありません。');
        }

        // 紐づく ProofSchedule を削除
        ProofSchedule::where('event_id', $event->id)->delete();
        $rawStart = $event->getRawOriginal('starts_at');
        $rawEnd   = $event->getRawOriginal('ends_at');
        if ($event->user_id && $rawStart && $rawEnd) {
            ProofSchedule::where('user_id', $event->user_id)
                ->where('starts_at', $rawStart)
                ->where('ends_at', $rawEnd)
                ->whereNull('event_id')
                ->delete();
        }

        $deletedAssignmentId = $event->project_job_assignment_id;
        $event->delete();

        // 校正スロット（pja101）の最後のイベント削除時: pja101/pja100 削除 + ProofRequest を pending に戻す
        if ($deletedAssignmentId) {
            \App\Services\ProofJobRollbackService::rollbackIfNoEvents($deletedAssignmentId);
        }

        return response()->json(['ok' => true]);
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
                    $q->where('coordinator_assignment_id', $assignment->id)
                      ->orWhere('supersedes_assignment_id', $assignment->id);
                })
                ->latest()
                ->first();

            if ($selfJob) {
                $tz = 'Asia/Tokyo';

                $workSlots = Event::where('project_job_assignment_id', $selfJob->id)
                    ->orderBy('starts_at')
                    ->get()
                    ->map(function ($e) use ($tz) {
                        $rawStart = $e->getRawOriginal('starts_at');
                        $rawEnd   = $e->getRawOriginal('ends_at');
                        $start    = $rawStart ? Carbon::createFromFormat('Y-m-d H:i:s', $rawStart, 'UTC')->setTimezone($tz) : null;
                        $end      = $rawEnd   ? Carbon::createFromFormat('Y-m-d H:i:s', $rawEnd,   'UTC')->setTimezone($tz) : null;
                        return [
                            'date'  => $start ? $start->format('Y年n月j日') : null,
                            'start' => $start ? $start->format('H:i') : null,
                            'end'   => $end   ? $end->format('H:i')   : null,
                        ];
                    })
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

        // 年月フィルター（YYYY-MM）
        if ($period = $request->input('period')) {
            $query->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$period]);
        }

        // 完了を非表示
        if ($request->boolean('hide_completed')) {
            $query->where('status', '!=', 'completed');
        }

        $requests = $query->paginate(30)->withQueryString();

        // 年月セレクター用オプション
        $monthOptions = ProofRequest::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as value")
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m') DESC")
            ->pluck('value')
            ->map(fn($m) => [
                'value' => $m,
                'label' => sprintf('%d年%d月', (int) explode('-', $m)[0], (int) explode('-', $m)[1]),
            ])
            ->values()
            ->toArray();

        return Inertia::render('ProofCoordinator/History/Index', [
            'proofRequests'  => $requests,
            'search'         => $request->input('search', ''),
            'period'         => $request->input('period', ''),
            'hideCompleted'  => $request->boolean('hide_completed'),
            'monthOptions'   => $monthOptions,
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
            'proof_cell_id'             => ['nullable', 'exists:progress_cells,id'],
        ]);

        // 日付のみ（時間なし）で送信された場合は 17:30 JST をデフォルトにする
        $rawDeadline = $request->input('deadline');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDeadline)) {
            $data['deadline'] = Carbon::createFromFormat('Y-m-d H:i', $rawDeadline . ' 17:30', 'Asia/Tokyo')
                ->utc()
                ->format('Y-m-d H:i:s');
        }

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
        $user = Auth::user();
        $date = now()->setTimezone('Asia/Tokyo')->toDateString();

        $events = ProofRequest::with(['proofreader', 'projectJob'])
            ->whereNotNull('deadline')
            ->whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->get()
            ->map(fn ($r) => [
                'id'          => $r->id,
                'title'       => $r->title,
                'start'       => $r->deadline->toDateString(),
                'status'      => $r->status,
                'proofreader' => $r->proofreader?->name,
                'project_job' => $r->projectJob?->title,
            ]);

        return Inertia::render('Proof/Calendar', [
            'events'         => $events,
            'dailySchedules' => $this->getUserSchedulesForDate($user->id, $date),
            'currentDate'    => $date,
            'currentUser'    => ['id' => $user->id, 'name' => $user->name],
        ]);
    }

    /**
     * GET /proof/calendar/data
     * ユーザー向け日別スケジュール（AJAX・日付切り替え用）
     */
    public function calendarUserData(Request $request): JsonResponse
    {
        $user = Auth::user();
        $date = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());

        return response()->json([
            'schedules' => $this->getUserSchedulesForDate($user->id, $date),
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────

    /** ユーザー自身に割り振られた日別スケジュールを取得 */
    private function getUserSchedulesForDate(int $userId, string $date): array
    {
        $dayStart = Carbon::parse($date . ' 00:00:00', 'Asia/Tokyo')->utc();
        $dayEnd   = Carbon::parse($date . ' 23:59:59', 'Asia/Tokyo')->utc();

        // ① ProofSchedule（手動登録）- 自分のみ
        $manual = ProofSchedule::with(['proofRequest.projectJob'])
            ->where('user_id', $userId)
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                  ->orWhereBetween('ends_at', [$dayStart, $dayEnd])
                  ->orWhere(function ($q2) use ($dayStart, $dayEnd) {
                      $q2->where('starts_at', '<=', $dayStart)
                         ->where('ends_at', '>=', $dayEnd);
                  });
            })
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($s) => [
                'id'               => $s->id,
                'proof_request_id' => $s->proof_request_id,
                'user_id'          => $s->user_id,
                'starts_at'        => $this->toUtcIso($s->getRawOriginal('starts_at')),
                'ends_at'          => $this->toUtcIso($s->getRawOriginal('ends_at')),
                'title'            => $s->proofRequest?->title ?? '—',
                'job_title'        => $s->proofRequest?->projectJob?->title ?? null,
                'status'           => $s->proofRequest?->status ?? null,
                'deadline'         => $s->proofRequest
                    ? $this->toUtcIso($s->proofRequest->getRawOriginal('deadline'))
                    : null,
            ])
            ->toArray();

        // ② Event（pja101経由）- 自分のみ
        $manualIds = collect($manual)->pluck('proof_request_id')->filter()->unique();

        $activeRequests = ProofRequest::with(['projectJob'])
            ->whereIn('status', ['assigned', 'in_progress'])
            ->where('proofreader_id', $userId)
            ->whereNotIn('id', $manualIds)
            ->get();

        $fromEvents = [];
        foreach ($activeRequests as $pr) {
            if (! $pr->proof_coordinator_id) continue;

            $coordAssignment = ProjectJobAssignment::where('project_job_id', $pr->project_job_id)
                ->where('user_id', $userId)
                ->where('sender_id', $pr->proof_coordinator_id)
                ->latest()->first();
            if (! $coordAssignment) continue;

            $selfJob = ProjectJobAssignment::whereColumn('sender_id', 'user_id')
                ->where('user_id', $userId)
                ->where(function ($q) use ($coordAssignment) {
                    $q->where('coordinator_assignment_id', $coordAssignment->id)
                      ->orWhere('supersedes_assignment_id', $coordAssignment->id);
                })
                ->latest()->first();
            if (! $selfJob) continue;

            $events = Event::where('project_job_assignment_id', $selfJob->id)
                ->where(function ($q) use ($dayStart, $dayEnd) {
                    $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                      ->orWhereBetween('ends_at', [$dayStart, $dayEnd]);
                })
                ->orderBy('starts_at')
                ->get();

            foreach ($events as $ev) {
                $fromEvents[] = [
                    'id'               => 'ev_' . $ev->id,
                    'proof_request_id' => $pr->id,
                    'user_id'          => $userId,
                    'starts_at'        => $this->toUtcIso($ev->getRawOriginal('starts_at')),
                    'ends_at'          => $this->toUtcIso($ev->getRawOriginal('ends_at')),
                    'title'            => $pr->title,
                    'job_title'        => $pr->projectJob?->title ?? null,
                    'status'           => $pr->status,
                    'deadline'         => $this->toUtcIso($pr->getRawOriginal('deadline')),
                    'from_event'       => true,
                ];
            }
        }

        return array_merge($manual, $fromEvents);
    }

    private function toUtcIso(?string $raw): ?string
    {
        if (! $raw) return null;

        return Carbon::createFromFormat('Y-m-d H:i:s', $raw, 'UTC')->toIso8601String();
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

        // Debug: log incoming slots for troubleshooting timezone/offset issues
        try {
            Log::info('saveWorkSlots invoked', ['proof_request_id' => $proofRequest->id ?? null, 'replace' => $replace, 'slots' => $slots]);
        } catch (\Throwable $__logE) {
            // ignore logging errors
        }

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
                    $q->where('coordinator_assignment_id', $pja100->id)
                      ->orWhere('supersedes_assignment_id', $pja100->id);
                })->latest()->first();

            if (! $pja101 && $proofRequest->proofreader_id) {
                $pja101 = ProjectJobAssignment::create([
                    'project_job_id'            => $proofRequest->project_job_id,
                    'user_id'                   => $proofRequest->proofreader_id,
                    'sender_id'                 => $proofRequest->proofreader_id,
                    'coordinator_assignment_id' => $pja100->id,
                    'job_type'                  => 'proof',
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

            try {
                Log::info('saveWorkSlots computed times', ['date' => $date, 'start_local' => "{$date} {$sH}:{$sM}:00", 'end_local' => "{$date} {$eH}:{$eM}:00", 'starts_at_utc' => $startsAt->toDateTimeString(), 'ends_at_utc' => $endsAt->toDateTimeString(), 'pja100_id' => $pja100->id ?? null]);
            } catch (\Throwable $__logE) {}

            try {
                $ps = ProofSchedule::create([
                    'proof_request_id' => $proofRequest->id,
                    'user_id'          => $proofRequest->proofreader_id,
                    'starts_at'        => $startsAt,
                    'ends_at'          => $endsAt,
                ]);
                Log::info('saveWorkSlots created ProofSchedule', ['proof_schedule_id' => $ps->id ?? null, 'proof_request_id' => $proofRequest->id, 'user_id' => $proofRequest->proofreader_id]);
            } catch (\Throwable $__e) {
                Log::warning('saveWorkSlots: failed to create ProofSchedule', ['error' => $__e->getMessage(), 'proof_request_id' => $proofRequest->id]);
            }

            if ($pja101) {
                try {
                    $ev = Event::create([
                        'user_id'                   => $proofRequest->proofreader_id,
                        'project_job_assignment_id' => $pja101->id,
                        'date'                      => $date,
                        'start'                     => "{$date} {$sH}:{$sM}:00",
                        'end'                       => "{$date} {$eH}:{$eM}:00",
                        'starts_at'                 => $startsAt,
                        'ends_at'                   => $endsAt,
                        'title'                     => $proofRequest->title,
                    ]);
                    Log::info('saveWorkSlots created Event', ['event_id' => $ev->id ?? null, 'pja101_id' => $pja101->id, 'project_job_assignment_id' => $pja101->id]);
                } catch (\Throwable $__e) {
                    Log::warning('saveWorkSlots: failed to create Event', ['error' => $__e->getMessage(), 'pja101_id' => $pja101->id ?? null]);
                }
            }
        }
    }
}

<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Controllers\Controller;
use App\Models\OperatorCalendarColorAssignment;
use App\Models\OperatorCalendarMember;
use App\Models\OperatorReservation;
use App\Models\OperatorReservationNotification;
use App\Models\OperatorReservationRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OperatorCalendarController extends Controller
{
    use ResolvesContextCompany;

    // ──────────────────────────────────────────────────────
    //  ページ表示
    // ──────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $this->assertAccess();

        $date = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());

        return Inertia::render('Coordinator/OperatorCalendar', [
            'members'                      => $this->getMembers(),
            'candidateUsers'               => $this->getCandidateUsers(),
            'colorAssignments'             => $this->getColorAssignments(),
            'reservations'                 => $this->getReservationsForDate($date),
            'pendingRequests'              => $this->getPendingRequestsForDate($date),
            'assignableUsers'              => $this->getAssignableUsers(),
            'pendingRequestReservationIds' => $this->getPendingRequestConflictIds(),
            'date'                         => $date,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  JSON データ取得（日付切り替え時）
    // ──────────────────────────────────────────────────────

    public function data(Request $request): JsonResponse
    {
        $this->assertAccess();

        $date = $request->query('date', now()->setTimezone('Asia/Tokyo')->toDateString());

        return response()->json([
            'reservations'                 => $this->getReservationsForDate($date),
            'pendingRequests'              => $this->getPendingRequestsForDate($date),
            'pendingRequestReservationIds' => $this->getPendingRequestConflictIds(),
            'date'                         => $date,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  案件一覧トグルテーブル用（全オペレーター・全期間）
    // ──────────────────────────────────────────────────────

    public function all(): JsonResponse
    {
        $this->assertAccess();

        $reservations = OperatorReservation::with(['operatorUser:id,name', 'reservedByUser:id,name'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($r) => [
                'id'                  => $r->id,
                'start_display'       => $r->starts_at->format('Y-m-d H:i'),
                'end_display'         => $r->ends_at->format('Y-m-d H:i'),
                'operator_user_id'    => $r->operator_user_id,
                'operator_name'       => $r->operatorUser?->name ?? '—',
                'reserved_by_user_id' => $r->reserved_by_user_id,
                'reserved_by_name'    => $r->reservedByUser?->name ?? '—',
                'job_name'            => $r->job_name,
                'memo'                => $r->memo,
            ])
            ->values();

        return response()->json(['reservations' => $reservations]);
    }

    // ──────────────────────────────────────────────────────
    //  メンバー管理
    // ──────────────────────────────────────────────────────

    public function storeMember(Request $request): JsonResponse
    {
        $this->assertAccess();

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        // 候補一覧の絞り込みと同じスコープを検証する（直接APIを叩いての範囲外追加を防ぐ）
        [$companyId, $departmentId] = $this->candidateScope();
        $targetUser = User::findOrFail($data['user_id']);
        if ($companyId && (int) $targetUser->company_id !== (int) $companyId) {
            abort(403, '対象ユーザーは操作可能な範囲外です。');
        }
        if ($departmentId && (int) $targetUser->department_id !== (int) $departmentId) {
            abort(403, '対象ユーザーは操作可能な範囲外です。');
        }

        $maxOrder = OperatorCalendarMember::max('sort_order') ?? 0;

        $member = OperatorCalendarMember::firstOrCreate(
            ['user_id' => $data['user_id']],
            ['sort_order' => $maxOrder + 1]
        );
        $member->load('user:id,name');

        return response()->json([
            'id'   => $member->id,
            'name' => $member->user?->name,
            'user_id' => $member->user_id,
        ], 201);
    }

    public function destroyMember(User $user): JsonResponse
    {
        $this->assertAccess();

        OperatorCalendarMember::where('user_id', $user->id)->delete();

        return response()->json(['ok' => true, 'user_id' => $user->id, 'name' => $user->name]);
    }

    /** カレンダー行の並び順を保存する（ドラッグ／上下ボタンでの並べ替え確定時に呼ばれる） */
    public function reorderMembers(Request $request): JsonResponse
    {
        $this->assertAccess();

        $data = $request->validate([
            'order'   => ['required', 'array'],
            'order.*' => ['integer', 'exists:users,id'],
        ]);

        foreach ($data['order'] as $index => $userId) {
            OperatorCalendarMember::where('user_id', $userId)->update(['sort_order' => $index + 1]);
        }

        return response()->json(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────
    //  予約 CRUD
    // ──────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $this->assertAccess();

        $data = $request->validate([
            'operator_user_id'    => ['required', 'exists:users,id'],
            'reserved_by_user_id' => ['required', 'exists:users,id'],
            'job_name'            => ['required', 'string', 'max:255'],
            'memo'                => ['nullable', 'string'],
            'starts_at'           => ['required', 'date'],
            'ends_at'             => ['required', 'date', 'after:starts_at'],
        ]);

        $data['created_by_user_id'] = Auth::id();

        $reservation = OperatorReservation::create($data);

        return response()->json($this->formatReservation($reservation->fresh()), 201);
    }

    public function update(Request $request, OperatorReservation $operatorReservation): JsonResponse
    {
        $this->assertAccess();

        $data = $request->validate([
            'operator_user_id'    => ['sometimes', 'exists:users,id'],
            'reserved_by_user_id' => ['sometimes', 'exists:users,id'],
            'job_name'            => ['sometimes', 'string', 'max:255'],
            'memo'                => ['nullable', 'string'],
            'starts_at'           => ['sometimes', 'date'],
            'ends_at'             => ['sometimes', 'date'],
        ]);

        $newStart = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : $operatorReservation->starts_at;
        $newEnd   = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $operatorReservation->ends_at;
        if ($newEnd->lte($newStart)) {
            return response()->json(['message' => '終了時刻は開始時刻より後にしてください。'], 422);
        }

        $operatorReservation->update($data);

        return response()->json($this->formatReservation($operatorReservation->fresh()));
    }

    public function destroy(OperatorReservation $operatorReservation): JsonResponse
    {
        $this->assertAccess();

        $operatorReservation->delete();

        return response()->json(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────
    //  色割当
    // ──────────────────────────────────────────────────────

    public function updateColorAssignment(Request $request, string $colorKey): JsonResponse
    {
        $this->assertAccess();

        $data = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
        ]);

        $assignment = OperatorCalendarColorAssignment::where('color_key', $colorKey)->firstOrFail();
        $assignment->update(['user_id' => $data['user_id'] ?? null]);
        $assignment->load('user:id,name');

        return response()->json([
            'color_key' => $assignment->color_key,
            'user_id'   => $assignment->user_id,
            'user_name' => $assignment->user?->name,
        ]);
    }

    // ──────────────────────────────────────────────────────
    //  二重予約リクエスト・通知（Phase 2）
    // ──────────────────────────────────────────────────────

    public function notifications(): JsonResponse
    {
        $this->assertAccess();

        $notifications = OperatorReservationNotification::with([
                'request.operatorUser:id,name',
                'request.requestedByUser:id,name',
            ])
            ->where('user_id', Auth::id())
            ->unread()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'type'       => $n->type,
                'created_at' => $n->created_at->toIso8601String(),
                'request'    => [
                    'id'                => $n->request->id,
                    'status'            => $n->request->status,
                    'job_name'          => $n->request->job_name,
                    'memo'              => $n->request->memo,
                    'response_message'  => $n->request->response_message,
                    'operator_name'     => $n->request->operatorUser?->name ?? '—',
                    'requested_by_name' => $n->request->requestedByUser?->name ?? '—',
                    'starts_at'         => $n->request->starts_at->toIso8601String(),
                    'ends_at'           => $n->request->ends_at->toIso8601String(),
                ],
            ]);

        return response()->json(['notifications' => $notifications]);
    }

    public function markNotificationRead(OperatorReservationNotification $operatorReservationNotification): JsonResponse
    {
        $this->assertAccess();

        if ($operatorReservationNotification->user_id !== Auth::id()) {
            abort(403);
        }

        $operatorReservationNotification->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $this->assertAccess();

        $data = $request->validate([
            'conflicting_reservation_id' => ['required', 'exists:operator_reservations,id'],
            'operator_user_id'           => ['required', 'exists:users,id'],
            'job_name'                   => ['required', 'string', 'max:255'],
            'memo'                       => ['nullable', 'string'],
            'starts_at'                  => ['required', 'date'],
            'ends_at'                    => ['required', 'date', 'after:starts_at'],
        ]);

        // conflicting_reservation_id が本当に同じオペレーター・重複時間帯を指しているか検証する
        // （UI からは常に整合するが、API を直接叩かれた場合に無関係な予約が壊れるのを防ぐ）
        $conflicting = OperatorReservation::find($data['conflicting_reservation_id']);
        if (! $conflicting || (int) $conflicting->operator_user_id !== (int) $data['operator_user_id']) {
            return response()->json(['message' => '対象の予約が不正です。'], 422);
        }
        $reqStart = Carbon::parse($data['starts_at']);
        $reqEnd   = Carbon::parse($data['ends_at']);
        if ($reqStart->gte($conflicting->ends_at) || $reqEnd->lte($conflicting->starts_at)) {
            return response()->json(['message' => '指定した時間帯は対象の予約と重なっていません。'], 422);
        }

        $data['requested_by_user_id'] = Auth::id();
        $data['status'] = 'pending';

        $reservationRequest = OperatorReservationRequest::create($data);

        OperatorReservationNotification::create([
            'operator_reservation_request_id' => $reservationRequest->id,
            'user_id'                          => $conflicting->reserved_by_user_id,
            'type'                             => 'request_created',
        ]);

        return response()->json(['id' => $reservationRequest->id], 201);
    }

    public function respondRequest(Request $request, OperatorReservationRequest $operatorReservationRequest): JsonResponse
    {
        $this->assertAccess();

        $data = $request->validate([
            'decision'         => ['required', 'in:approved,rejected'],
            'response_message' => ['nullable', 'string', 'max:255'],
        ]);

        if ($operatorReservationRequest->status !== 'pending') {
            return response()->json(['message' => 'このリクエストは既に処理済みです。'], 409);
        }

        if ($data['decision'] === 'approved') {
            $conflictingId = $operatorReservationRequest->conflicting_reservation_id;

            if ($conflictingId) {
                $conflicting = OperatorReservation::find($conflictingId);
                if ($conflicting) {
                    $this->splitReservationAroundRequest($conflicting, $operatorReservationRequest);
                }

                // 同じ既存予約に対する他の保留中リクエストは自動的に却下する
                $others = OperatorReservationRequest::where('conflicting_reservation_id', $conflictingId)
                    ->where('id', '!=', $operatorReservationRequest->id)
                    ->where('status', 'pending')
                    ->get();

                foreach ($others as $other) {
                    $other->update([
                        'status'                => 'rejected',
                        'responded_by_user_id'  => Auth::id(),
                        'responded_at'          => now(),
                    ]);
                    $this->markRequestCreatedNotificationsRead($other->id);
                    OperatorReservationNotification::create([
                        'operator_reservation_request_id' => $other->id,
                        'user_id'                          => $other->requested_by_user_id,
                        'type'                             => 'request_rejected',
                    ]);
                }
            }

            OperatorReservation::create([
                'operator_user_id'    => $operatorReservationRequest->operator_user_id,
                'reserved_by_user_id' => $operatorReservationRequest->requested_by_user_id,
                'created_by_user_id'  => Auth::id(),
                'job_name'            => $operatorReservationRequest->job_name,
                'memo'                => $operatorReservationRequest->memo,
                'starts_at'           => $operatorReservationRequest->starts_at,
                'ends_at'             => $operatorReservationRequest->ends_at,
            ]);
        }

        $operatorReservationRequest->update([
            'status'               => $data['decision'],
            'responded_by_user_id' => Auth::id(),
            'responded_at'         => now(),
            'response_message'     => $data['response_message'] ?? null,
        ]);

        $this->markRequestCreatedNotificationsRead($operatorReservationRequest->id);

        OperatorReservationNotification::create([
            'operator_reservation_request_id' => $operatorReservationRequest->id,
            'user_id'                          => $operatorReservationRequest->requested_by_user_id,
            'type'                             => $data['decision'] === 'approved' ? 'request_approved' : 'request_rejected',
        ]);

        return response()->json(['ok' => true, 'status' => $data['decision']]);
    }

    // ──────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────

    /** Coordinator/Clerk/Leader/Admin/SuperAdmin のみ許可 */
    private function assertAccess(): void
    {
        $user = Auth::user();
        $allowed = $user && (
            $user->isCoordinator() ||
            $user->isClerk() ||
            $user->isLeader() ||
            $user->isAdmin() ||
            $user->isSuperAdmin()
        );

        if (! $allowed) {
            abort(403, 'Operator calendar access is limited to Coordinator/Clerk/Leader/Admin/SuperAdmin.');
        }
    }

    private function getMembers(): array
    {
        return OperatorCalendarMember::with('user:id,name')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn ($m) => $m->user !== null)
            ->map(fn ($m) => ['id' => $m->user->id, 'name' => $m->user->name])
            ->values()
            ->toArray();
    }

    private function getColorAssignments(): array
    {
        return OperatorCalendarColorAssignment::with('user:id,name')
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($a) => [
                'color_key' => $a->color_key,
                'user_id'   => $a->user_id,
                'user_name' => $a->user?->name,
            ])
            ->toArray();
    }

    /**
     * メンバー追加の対象範囲（会社ID・部署ID）を返す。候補一覧の絞り込みと、
     * 追加API（storeMember）側の検証の両方でこのスコープを使う（片方だけだと直接APIで回避できてしまうため）。
     * SuperAdmin は会社切替コンテキストに応じて絞り込む:
     *   - 自社を選択中（または未切替でコンテキストが自社と一致） → 自分の部署のみ
     *   - 他社を選択中／会社未選択のグローバルモード          → 部署では絞らず、選択中の会社全体（またはグローバルなら無制限）
     *
     * @return array{0: ?int, 1: ?int} [companyId, departmentId]（null は「絞り込みなし」を意味する）
     */
    private function candidateScope(): array
    {
        $user = Auth::user();

        $companyId    = $user->isSuperAdmin() ? $this->contextCompanyId() : $user->company_id;
        $departmentId = null;
        if (! $user->isSuperAdmin()) {
            $departmentId = $user->department_id;
        } elseif ($companyId && (int) $companyId === (int) $user->company_id) {
            $departmentId = $user->department_id;
        }

        return [$companyId, $departmentId];
    }

    /** 「＋メンバー」候補一覧: candidateScope() の範囲内のユーザーのみを sort_order 順で返す。 */
    private function getCandidateUsers(): array
    {
        $memberUserIds = OperatorCalendarMember::pluck('user_id');
        [$companyId, $departmentId] = $this->candidateScope();

        $query = User::whereNotIn('id', $memberUserIds);
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        return $query->ordered()
            ->get(['id', 'name'])
            ->toArray();
    }

    private function getAssignableUsers(): array
    {
        return User::where(function ($q) {
            $q->where('user_role', 'coordinator')
              ->orWhere('user_role', 'clerk')
              ->orWhere('user_role', 'leader')
              ->orWhere('user_role', 'admin')
              ->orWhere('user_role', 'superadmin');
        })
            ->ordered()
            ->get(['id', 'name'])
            ->toArray();
    }

    /**
     * 指定日の予約を全件取得する。
     * 「+メンバー」リストに現在登録されているかどうかに関わらず全予約を返す
     * （メンバーがリストから外れても予約データ自体は消さない・見失わないため）。
     */
    private function getReservationsForDate(string $date): array
    {
        $dayStart = $date . ' 00:00:00';
        $dayEnd   = $date . ' 23:59:59';

        return OperatorReservation::with('operatorUser:id,name')
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                  ->orWhereBetween('ends_at', [$dayStart, $dayEnd])
                  ->orWhere(fn ($q2) => $q2->where('starts_at', '<=', $dayStart)->where('ends_at', '>=', $dayEnd));
            })
            ->orderBy('starts_at')
            ->get()
            ->map(fn ($r) => $this->formatReservation($r))
            ->all();
    }

    /**
     * 承諾時、既存予約からリクエスト時間帯を差し引いた「残り」を再作成してから既存予約を削除する。
     * 完全一致（リクエストが既存予約と同じ範囲）なら残りは無く、丸ごと削除になる。
     * 端だけ・途中だけの場合は前後の残り区間を同じ内容（担当・案件名等）で複製する。
     */
    private function splitReservationAroundRequest(OperatorReservation $conflicting, OperatorReservationRequest $req): void
    {
        $reqStart = $req->starts_at;
        $reqEnd   = $req->ends_at;

        if ($reqStart->gt($conflicting->starts_at)) {
            OperatorReservation::create([
                'operator_user_id'    => $conflicting->operator_user_id,
                'reserved_by_user_id' => $conflicting->reserved_by_user_id,
                'created_by_user_id'  => $conflicting->created_by_user_id,
                'job_name'            => $conflicting->job_name,
                'memo'                => $conflicting->memo,
                'starts_at'           => $conflicting->starts_at,
                'ends_at'             => $reqStart,
            ]);
        }

        if ($reqEnd->lt($conflicting->ends_at)) {
            OperatorReservation::create([
                'operator_user_id'    => $conflicting->operator_user_id,
                'reserved_by_user_id' => $conflicting->reserved_by_user_id,
                'created_by_user_id'  => $conflicting->created_by_user_id,
                'job_name'            => $conflicting->job_name,
                'memo'                => $conflicting->memo,
                'starts_at'           => $reqEnd,
                'ends_at'             => $conflicting->ends_at,
            ]);
        }

        $conflicting->delete();
    }

    /** リクエストが承諾/拒否されたら、対応者に届いていた request_created 通知も既読にする */
    private function markRequestCreatedNotificationsRead(int $requestId): void
    {
        OperatorReservationNotification::where('operator_reservation_request_id', $requestId)
            ->where('type', 'request_created')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function getPendingRequestConflictIds(): array
    {
        return OperatorReservationRequest::where('status', 'pending')
            ->whereNotNull('conflicting_reservation_id')
            ->pluck('conflicting_reservation_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();
    }

    /** 指定日の保留中リクエストを、カレンダー上の薄いオーバーレイ表示用に返す */
    private function getPendingRequestsForDate(string $date): array
    {
        $dayStart = $date . ' 00:00:00';
        $dayEnd   = $date . ' 23:59:59';

        return OperatorReservationRequest::with('requestedByUser:id,name')
            ->where('status', 'pending')
            ->where(function ($q) use ($dayStart, $dayEnd) {
                $q->whereBetween('starts_at', [$dayStart, $dayEnd])
                  ->orWhereBetween('ends_at', [$dayStart, $dayEnd])
                  ->orWhere(fn ($q2) => $q2->where('starts_at', '<=', $dayStart)->where('ends_at', '>=', $dayEnd));
            })
            ->get()
            ->map(fn ($r) => [
                'id'                => $r->id,
                'operator_user_id'  => $r->operator_user_id,
                'job_name'          => $r->job_name,
                'requested_by_name' => $r->requestedByUser?->name ?? '—',
                'starts_at'         => $r->starts_at->toIso8601String(),
                'ends_at'           => $r->ends_at->toIso8601String(),
            ])
            ->all();
    }

    private function formatReservation(OperatorReservation $r): array
    {
        return [
            'id'                  => $r->id,
            'operator_user_id'    => $r->operator_user_id,
            'operator_name'       => $r->operatorUser?->name ?? $r->operator_user_id,
            'reserved_by_user_id' => $r->reserved_by_user_id,
            'job_name'            => $r->job_name,
            'memo'                => $r->memo,
            'starts_at'           => $r->starts_at->toIso8601String(),
            'ends_at'             => $r->ends_at->toIso8601String(),
        ];
    }
}

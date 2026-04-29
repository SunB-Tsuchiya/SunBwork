<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Department;
use App\Models\PrepressTicket;
use App\Models\ProjectJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BoardController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizePrepress($request->user());

        $tickets = PrepressTicket::orderByDesc('updated_at')->get([
            'id', 'title', 'jobcode', 'project_name', 'client_name', 'memo', 'status', 'image_path', 'created_at',
        ])->each->append('image_url');

        return inertia('Prepress/Board', [
            'tickets'  => $tickets,
            'statuses' => PrepressTicket::STATUS_LABELS,
        ]);
    }

    public function updateStatus(Request $request, PrepressTicket $ticket)
    {
        $this->authorizePrepress($request->user());

        $request->validate([
            'status' => ['required', Rule::in(array_keys(PrepressTicket::STATUS_LABELS))],
        ]);

        $ticket->update(['status' => $request->status]);

        return response()->noContent();
    }

    /**
     * 製版部署に紐づくクライアント一覧（JSON）
     */
    public function apiClients(Request $request): JsonResponse
    {
        $this->authorizePrepress($request->user());

        $q    = $request->input('q', '');
        $dept = Department::where('name', '製版')->first();

        $query = Client::query();
        if ($dept) {
            $query->whereHas('departments', fn($q) => $q->where('departments.id', $dept->id));
        }
        if ($q) {
            $query->where('name', 'like', '%' . $q . '%');
        }

        $clients = $query->orderBy('name')->limit(50)->get(['id', 'name', 'is_dormant']);

        return response()->json($clients);
    }

    /**
     * クライアントに紐づく案件一覧（JSON）- ログインユーザーと同じ部署のユーザーが作成した案件のみ
     */
    public function apiProjectJobs(Request $request): JsonResponse
    {
        $this->authorizePrepress($request->user());

        $clientId = $request->integer('client_id');
        if (!$clientId) {
            return response()->json([]);
        }

        $user   = $request->user();
        $deptId = $user->department_id;

        $query = ProjectJob::where('client_id', $clientId)
            ->where('completed', false);

        if ($deptId) {
            $deptUserIds = \App\Models\User::where('department_id', $deptId)->pluck('id');
            $query->whereIn('user_id', $deptUserIds);
        }

        $jobs = $query->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'title', 'jobcode']);

        return response()->json($jobs);
    }

    protected function authorizePrepress($user): void
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return;
        }
        if (!$user->department || $user->department->name !== '製版') {
            abort(403, 'Prepress エリアは製版部署のみアクセスできます。');
        }
    }
}

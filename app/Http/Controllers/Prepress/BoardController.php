<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Department;
use App\Models\PrepresSalesRep;
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

        $tickets = PrepressTicket::with('salesRepEntry:id,name,company')
            ->where('status', '!=', PrepressTicket::STATUS_DELETED)
            ->orderByDesc('updated_at')
            ->get([
                'id', 'title', 'jobcode', 'project_name', 'client_id', 'client_name',
                'sales_rep', 'sales_rep_id', 'memo', 'status', 'image_path', 'card_color', 'created_at',
                'submission_date', 'sb_delivery_date',
            ])->each->append('image_url');

        $salesReps = \App\Models\PrepresSalesRep::orderBy('sort_order')->get(['id', 'name', 'company']);

        return inertia('Prepress/Board', [
            'tickets'   => $tickets,
            'statuses'  => PrepressTicket::STATUS_LABELS,
            'salesReps' => $salesReps,
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

    public function updateColor(Request $request, PrepressTicket $ticket)
    {
        $this->authorizePrepress($request->user());

        $request->validate([
            'card_color' => ['nullable', 'string', 'max:20'],
        ]);

        $ticket->update(['card_color' => $request->card_color ?: null]);

        return response()->noContent();
    }

    /**
     * 完了ボックスから伝票を削除（ステータスを削除にし、画像ファイルを削除）
     */
    public function archiveFromCompleted(Request $request, PrepressTicket $ticket)
    {
        $this->authorizePrepress($request->user());

        if ($ticket->status !== PrepressTicket::STATUS_COMPLETED) {
            return response()->json(['message' => '完了ステータスの伝票のみ削除できます。'], 422);
        }

        // 画像ファイルを削除
        if ($ticket->image_path) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($ticket->image_path);
        }

        $ticket->update([
            'status'     => PrepressTicket::STATUS_DELETED,
            'image_path' => null,
        ]);

        return response()->noContent();
    }

    /**
     * 製版部署に紐づくクライアント一覧（JSON）
     *
     * ?q=    名前での部分一致検索
     * ?code= client_code での部分一致検索（コードオートコンプリート用）
     * ?id=   DB id で単一クライアントを返す（OCR 結果の client_code 解決用）
     */
    public function apiClients(Request $request): JsonResponse
    {
        $this->authorizePrepress($request->user());

        $dept  = Department::where('name', '製版')->first();
        $query = Client::query();
        if ($dept) {
            $query->whereHas('departments', fn($q) => $q->where('departments.id', $dept->id));
        }

        // DB id での単一取得（OCR → client_code 解決用）
        if ($request->filled('id')) {
            $client = (clone $query)->find((int) $request->id, ['id', 'name', 'client_code', 'is_dormant']);
            return response()->json($client);
        }

        // client_code での部分一致（コード入力オートコンプリート）
        if ($request->filled('code')) {
            $query->where('client_code', 'like', '%' . $request->code . '%');
        } elseif ($request->filled('q')) {
            $query->where('name', 'like', '%' . $request->q . '%');
        }

        $clients = $query->orderBy('name')->limit(50)->get(['id', 'name', 'client_code', 'is_dormant']);

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

    /**
     * CSV インライン: クライアント新規登録（製版部署に紐づける）
     */
    public function apiClientCreate(Request $request): JsonResponse
    {
        $this->authorizePrepress($request->user());

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'client_code' => ['nullable', 'string', 'max:50'],
        ]);

        $dept = Department::where('name', '製版')->first();

        // 名前が完全一致する既存クライアントがあれば新規作成せずそれを返す
        $existing = Client::where('name', $validated['name'])->first();
        if ($existing) {
            if ($dept) {
                $existing->departments()->syncWithoutDetaching([$dept->id]);
            }
            return response()->json([
                'client'       => $existing->only(['id', 'name', 'client_code']),
                'was_existing' => true,
            ]);
        }

        $client = Client::create([
            'name'        => $validated['name'],
            'client_code' => $validated['client_code'] ?? null,
            'company_id'  => $dept?->company_id,
        ]);

        if ($dept) {
            $client->departments()->syncWithoutDetaching([$dept->id]);
        }

        return response()->json(['client' => $client->only(['id', 'name', 'client_code'])]);
    }

    protected function authorizePrepress($user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        if ($user->isAdmin()) {
            $prepressCompanyId = \App\Models\Department::where('name', '製版')->value('company_id');
            if (!$prepressCompanyId || $user->company_id == $prepressCompanyId) {
                return;
            }
            abort(403, 'Prepress エリアは同じ会社のAdminのみアクセスできます。');
        }
        // Coordinator・Leader・Clerk は部署問わずアクセス可
        if ($user->isCoordinator() || $user->isLeader() || $user->isClerk()) {
            return;
        }
        if (!$user->department || $user->department->name !== '製版') {
            abort(403, 'Prepress エリアは製版部署のみアクセスできます。');
        }
    }
}

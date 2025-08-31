<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\ProjectMemo;

class ProjectMemosController extends Controller
{
    public function index(Request $request)
    {
        // optionally filter by project_id
        $query = ProjectMemo::query();
        if ($request->filled('project_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('project_id', $request->input('project_id'))->orWhereNull('project_id');
            });
        }
        $memos = $query->with('user:id,name')->get();
        // attach author info for each memo
        $memos->transform(function ($m) {
            $m->author = $m->user ? ['id' => $m->user->id, 'name' => $m->user->name] : null;
            return $m;
        });
        return response()->json(['memos' => $memos]);
    }

    public function store(Request $request)
    {
        Log::info('ProjectMemosController@store payload', $request->all());
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:project_jobs,id'],
            'date' => ['nullable', 'date'],
            'body' => ['required', 'string'],
        ]);

        $memo = ProjectMemo::create([
            'project_id' => $data['project_id'] ?? null,
            'user_id' => Auth::id(),
            'date' => $data['date'] ?? null,
            'body' => $data['body'],
        ]);

        // reload with user relation
        $memo->load('user:id,name');
        $memo->author = $memo->user ? ['id' => $memo->user->id, 'name' => $memo->user->name] : null;

        return response()->json(['status' => 'ok', 'memo' => $memo]);
    }

    public function show($id)
    {
        $memo = ProjectMemo::with('user:id,name')->findOrFail($id);
        // attach author info
        $memo->author = $memo->user ? ['id' => $memo->user->id, 'name' => $memo->user->name] : null;
        return Inertia::render('Coordinator/ProjectSchedules/Memos/Show', ['memo' => $memo]);
    }

    public function update(Request $request, $id)
    {
        $memo = ProjectMemo::findOrFail($id);
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Allow owner or coordinator/admin/leader/superadmin
        $canUpdate = ($user->id === $memo->user_id)
            || (method_exists($user, 'isCoordinator') && $user->isCoordinator())
            || (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || (method_exists($user, 'isLeader') && $user->isLeader());

        if (!$canUpdate) {
            return response()->json(['error' => 'Forbidden'], 403);
        }
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'body' => ['required', 'string'],
        ]);
        $memo->date = $data['date'] ?? $memo->date;
        $memo->body = $data['body'];
        $memo->save();
        $memo->load('user:id,name');
        $memo->author = $memo->user ? ['id' => $memo->user->id, 'name' => $memo->user->name] : null;
        return response()->json(['status' => 'ok', 'memo' => $memo]);
    }

    public function destroy($id)
    {
        $memo = ProjectMemo::findOrFail($id);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Allow owner or coordinator/admin/leader/superadmin
        $canDelete = ($user->id === $memo->user_id)
            || (method_exists($user, 'isCoordinator') && $user->isCoordinator())
            || (method_exists($user, 'isAdmin') && $user->isAdmin())
            || (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || (method_exists($user, 'isLeader') && $user->isLeader());

        if (!$canDelete) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        $memo->delete();
        return response()->json(['status' => 'ok']);
    }
}

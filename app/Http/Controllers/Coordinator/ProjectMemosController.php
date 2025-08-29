<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
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
        $memos = $query->get();
        return response()->json(['memos' => $memos]);
    }

    public function store(Request $request)
    {
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

        return response()->json(['status' => 'ok', 'memo' => $memo]);
    }

    public function show($id)
    {
        $memo = ProjectMemo::findOrFail($id);
        return Inertia::render('Coordinator/ProjectSchedules/Memos/Show', ['memo' => $memo]);
    }

    public function update(Request $request, $id)
    {
        $memo = ProjectMemo::findOrFail($id);
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'body' => ['required', 'string'],
        ]);
        $memo->date = $data['date'] ?? $memo->date;
        $memo->body = $data['body'];
        $memo->save();
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

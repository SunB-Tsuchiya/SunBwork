<?php

namespace App\Http\Controllers\ProofCoordinator;

use App\Http\Controllers\Controller;
use App\Models\ProofDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProofDispatcherController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = ProofDispatcher::withCount('assignments');

        if ($user->user_role !== 'superadmin') {
            $query->forCompany($user->company_id);
        }

        return Inertia::render('ProofCoordinator/Dispatchers/Index', [
            'dispatchers' => $query->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('ProofCoordinator/Dispatchers/Create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        if ($user->user_role !== 'superadmin') {
            $data['company_id'] = $user->company_id;
        }

        // is_active は登録時 OFF
        $data['is_active'] = false;

        $dispatcher = ProofDispatcher::create($data);

        return redirect()->route('proof_coordinator.dispatchers.show', $dispatcher)
            ->with('success', '単発派遣を登録しました。');
    }

    public function show(ProofDispatcher $dispatcher)
    {
        $assignmentCount = $dispatcher->assignments()->count();

        return Inertia::render('ProofCoordinator/Dispatchers/Show', [
            'dispatcher'      => $dispatcher,
            'assignmentCount' => $assignmentCount,
        ]);
    }

    public function edit(ProofDispatcher $dispatcher)
    {
        return Inertia::render('ProofCoordinator/Dispatchers/Edit', [
            'dispatcher' => $dispatcher,
        ]);
    }

    public function update(Request $request, ProofDispatcher $dispatcher)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $dispatcher->update($data);

        return redirect()->route('proof_coordinator.dispatchers.show', $dispatcher)
            ->with('success', '単発派遣情報を更新しました。');
    }

    public function destroy(ProofDispatcher $dispatcher)
    {
        $assignmentCount = $dispatcher->assignments()->count();
        if ($assignmentCount > 0) {
            return back()->with('dispatcherDeleteError', ['count' => $assignmentCount]);
        }

        $dispatcher->delete();

        return redirect()->route('proof_coordinator.dispatchers.index')
            ->with('success', '単発派遣を削除しました。');
    }

    public function toggle(Request $request, ProofDispatcher $dispatcher)
    {
        $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $dispatcher->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'ステータスを更新しました。');
    }

    public function checkDuplicate(Request $request)
    {
        $user = Auth::user();
        $name = $request->input('name', '');

        $query = ProofDispatcher::query();
        if ($user->user_role !== 'superadmin') {
            $query->forCompany($user->company_id);
        }

        $normalized = $this->normalizeName($name);
        $existing   = $query->get(['id', 'name']);
        $duplicates = $existing->filter(
            fn ($d) => $this->normalizeName($d->name) === $normalized && $normalized !== ''
        )->values();

        return response()->json(['duplicates' => $duplicates]);
    }

    private function normalizeName(string $name): string
    {
        $name = mb_convert_kana($name, 'as');
        $name = preg_replace('/[\s　・\-_]+/u', '', $name);
        return mb_strtolower($name);
    }
}

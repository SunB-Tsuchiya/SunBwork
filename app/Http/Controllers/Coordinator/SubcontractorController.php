<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\Subcontractor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SubcontractorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Subcontractor::with('coordinators:id,name')
            ->withCount('assignments');

        if ($user->user_role === 'superadmin') {
            $subcontractors = $query->get();
        } else {
            $subcontractors = $query->forCompany($user->company_id)->get();
        }

        return Inertia::render('Coordinator/Subcontractors/Index', [
            'subcontractors' => $subcontractors,
        ]);
    }

    public function create()
    {
        $coordinators = $this->coordinatorCandidates();
        return Inertia::render('Coordinator/Subcontractors/Create', [
            'coordinators' => $coordinators,
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'notes'        => 'nullable|string',
            'coordinator_ids' => 'nullable|array',
            'coordinator_ids.*' => 'exists:users,id',
        ]);

        $coordinatorIds = $data['coordinator_ids'] ?? [];
        unset($data['coordinator_ids']);

        if ($user->user_role !== 'superadmin') {
            $data['company_id'] = $user->company_id;
        }

        $subcontractor = DB::transaction(function () use ($data, $coordinatorIds) {
            $sub = Subcontractor::create($data);
            if ($coordinatorIds) {
                $sub->coordinators()->sync($coordinatorIds);
            }
            return $sub;
        });

        return redirect()->route('coordinator.subcontractors.show', $subcontractor);
    }

    public function show(Subcontractor $subcontractor)
    {
        $subcontractor->load('coordinators:id,name');
        $coordinators = $this->coordinatorCandidates();
        $assignmentCount = $subcontractor->assignments()->count();

        return Inertia::render('Coordinator/Subcontractors/Show', [
            'subcontractor'   => $subcontractor,
            'coordinators'    => $coordinators,
            'assignmentCount' => $assignmentCount,
        ]);
    }

    public function edit(Subcontractor $subcontractor)
    {
        $subcontractor->load('coordinators:id,name');
        $coordinators = $this->coordinatorCandidates();

        return Inertia::render('Coordinator/Subcontractors/Edit', [
            'subcontractor' => $subcontractor,
            'coordinators'  => $coordinators,
        ]);
    }

    public function update(Request $request, Subcontractor $subcontractor)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'phone'        => 'nullable|string|max:50',
            'notes'        => 'nullable|string',
            'coordinator_ids' => 'nullable|array',
            'coordinator_ids.*' => 'exists:users,id',
        ]);

        $coordinatorIds = $data['coordinator_ids'] ?? [];
        unset($data['coordinator_ids']);

        DB::transaction(function () use ($subcontractor, $data, $coordinatorIds) {
            $subcontractor->update($data);
            $subcontractor->coordinators()->sync($coordinatorIds);
        });

        return redirect()->route('coordinator.subcontractors.show', $subcontractor);
    }

    public function destroy(Subcontractor $subcontractor)
    {
        $assignmentCount = $subcontractor->assignments()->count();
        if ($assignmentCount > 0) {
            return back()->with('subcontractorDeleteError', ['count' => $assignmentCount]);
        }

        $subcontractor->delete();
        return redirect()->route('coordinator.subcontractors.index');
    }

    public function checkDuplicate(Request $request)
    {
        $user = Auth::user();
        $name = $request->input('name', '');

        $query = Subcontractor::query();
        if ($user->user_role !== 'superadmin') {
            $query->forCompany($user->company_id);
        }

        $normalized = $this->normalizeName($name);
        $existing = $query->get(['id', 'name']);
        $duplicates = $existing->filter(fn ($s) => $this->normalizeName($s->name) === $normalized && $normalized !== '')->values();

        return response()->json(['duplicates' => $duplicates]);
    }

    private function normalizeName(string $name): string
    {
        $name = mb_convert_kana($name, 'as');
        $name = preg_replace('/[\s　・\-_]+/u', '', $name);
        return mb_strtolower($name);
    }

    private function coordinatorCandidates(): \Illuminate\Support\Collection
    {
        $user = Auth::user();
        $query = User::where(function ($q) {
            $q->where('user_role', 'coordinator')
              ->orWhereHas('assignment', fn ($a) => $a->where('code', 'shinko'));
        })->orderBy('name');

        if ($user->user_role !== 'superadmin') {
            $query->where('company_id', $user->company_id);
        }

        return $query->get(['id', 'name']);
    }
}

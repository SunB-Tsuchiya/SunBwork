<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\PrepresSalesRep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesRepController extends Controller
{
    private function departments()
    {
        return Department::whereIn('id', [1, 2, 3])->orderBy('id')->get(['id', 'name']);
    }

    public function index()
    {
        $salesReps = PrepresSalesRep::with('departments:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Prepress/SalesReps/Index', [
            'salesReps'   => $salesReps,
            'departments' => $this->departments(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'company'        => ['nullable', 'string', 'max:200'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $name = $this->normalizeName($validated['name']);

        if (PrepresSalesRep::where('name', $name)->exists()) {
            return back()->withErrors(['name' => '同じ名前の営業担当が既に登録されています。'])->withInput();
        }

        $rep = PrepresSalesRep::create([
            'name'    => $name,
            'company' => $validated['company'] ?? null,
        ]);

        if (!empty($validated['department_ids'])) {
            $rep->departments()->sync($validated['department_ids']);
        }

        return back()->with('success', '営業担当を登録しました。');
    }

    public function update(Request $request, PrepresSalesRep $salesRep)
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'company'        => ['nullable', 'string', 'max:200'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $name = $this->normalizeName($validated['name']);

        if (PrepresSalesRep::where('name', $name)->where('id', '!=', $salesRep->id)->exists()) {
            return back()->withErrors(['name' => '同じ名前の営業担当が既に登録されています。'])->withInput();
        }

        $salesRep->update([
            'name'    => $name,
            'company' => $validated['company'] ?? null,
        ]);

        $salesRep->departments()->sync($validated['department_ids'] ?? []);

        return back()->with('success', '営業担当を更新しました。');
    }

    public function destroy(PrepresSalesRep $salesRep)
    {
        $salesRep->delete();
        return back()->with('success', '営業担当を削除しました。');
    }

    public function apiList()
    {
        return response()->json(
            PrepresSalesRep::orderBy('name')->get(['id', 'name', 'company'])
        );
    }

    public function apiCreate(Request $request)
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:200'],
        ]);

        $name = $this->normalizeName($validated['name']);

        if (PrepresSalesRep::where('name', $name)->exists()) {
            return response()->json(['error' => '同じ名前の営業担当が既に登録されています。'], 422);
        }

        $rep = PrepresSalesRep::create(['name' => $name, 'company' => $validated['company'] ?? null]);

        return response()->json(['rep' => $rep->only(['id', 'name', 'company'])]);
    }

    public function bulkStore(Request $request): JsonResponse
    {
        $this->authorizePrepress($request->user());

        $validated = $request->validate([
            'names'            => ['required', 'array', 'min:1'],
            'names.*'          => ['required', 'string', 'max:100'],
            'company'          => ['nullable', 'string', 'max:200'],
            'department_ids'   => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $names   = array_values(array_unique(array_filter(array_map(
            fn($n) => $this->normalizeName($n),
            $validated['names']
        ))));
        $company = $validated['company'] ?? null;
        $deptIds = $validated['department_ids'] ?? [];

        $existingNames = PrepresSalesRep::whereIn('name', $names)->pluck('name')->flip()->all();

        $created = 0;
        $skipped = 0;
        foreach ($names as $name) {
            if (!$name || array_key_exists($name, $existingNames)) {
                $skipped++;
                continue;
            }
            $rep = PrepresSalesRep::create(['name' => $name, 'company' => $company]);
            if ($deptIds) {
                $rep->departments()->syncWithoutDetaching($deptIds);
            }
            $existingNames[$name] = true;
            $created++;
        }

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    /** 全角スペース → 半角、連続スペース → 1つ、前後トリム */
    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/[\x{3000}\s]+/u', ' ', $name));
    }

    private function authorizePrepress($user): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }
        if ($user->isAdmin()) {
            $prepressCompanyId = \App\Models\Department::where('name', '製版')->value('company_id');
            if (!$prepressCompanyId || $user->company_id == $prepressCompanyId) {
                return;
            }
            abort(403);
        }
        if (!$user->department || $user->department->name !== '製版') {
            abort(403);
        }
    }
}

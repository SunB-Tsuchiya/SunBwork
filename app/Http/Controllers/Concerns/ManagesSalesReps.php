<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Department;
use App\Models\PrepresSalesRep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 営業担当管理の共通ロジック。
 * Prepress / Leader / Coordinator 各コントローラーで use して使用する。
 *
 * 使用するコントローラーは salesRepsViewName() を実装すること。
 */
trait ManagesSalesReps
{
    abstract protected function salesRepsViewName(): string;

    /** ルートミドルウェアで権限不足の場合に追加チェックを行いたいコントローラーでオーバーライドする */
    protected function authorizeRoleAction($user): void {}

    private function salesRepDepartments()
    {
        return Department::whereIn('id', [1, 2, 3])->orderBy('id')->get(['id', 'name']);
    }

    public function index()
    {
        $salesReps = PrepresSalesRep::with('departments:id,name')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render($this->salesRepsViewName(), [
            'salesReps'   => $salesReps,
            'departments' => $this->salesRepDepartments(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'company'          => ['nullable', 'string', 'max:200'],
            'department_ids'   => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $name = $this->normalizeRepName($validated['name']);

        if (PrepresSalesRep::where('name', $name)->exists()) {
            return back()->withErrors(['name' => '同じ名前の営業担当が既に登録されています。'])->withInput();
        }

        $maxOrder = PrepresSalesRep::max('sort_order') ?? 0;
        $rep = PrepresSalesRep::create([
            'name'       => $name,
            'company'    => $validated['company'] ?? null,
            'sort_order' => $maxOrder + 1,
        ]);

        if (!empty($validated['department_ids'])) {
            $rep->departments()->sync($validated['department_ids']);
        }

        return back()->with('success', '営業担当を登録しました。');
    }

    public function update(Request $request, PrepresSalesRep $salesRep)
    {
        $validated = $request->validate([
            'name'             => ['required', 'string', 'max:100'],
            'company'          => ['nullable', 'string', 'max:200'],
            'department_ids'   => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $name = $this->normalizeRepName($validated['name']);

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

    public function bulkStore(Request $request): JsonResponse
    {
        $this->authorizeRoleAction($request->user());

        $validated = $request->validate([
            'names'            => ['required', 'array', 'min:1'],
            'names.*'          => ['required', 'string', 'max:100'],
            'company'          => ['nullable', 'string', 'max:200'],
            'department_ids'   => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $names   = array_values(array_unique(array_filter(array_map(
            fn($n) => $this->normalizeRepName($n),
            $validated['names']
        ))));
        $company = $validated['company'] ?? null;
        $deptIds = $validated['department_ids'] ?? [];

        $existingNames = PrepresSalesRep::whereIn('name', $names)->pluck('name')->flip()->all();
        $maxOrder = PrepresSalesRep::max('sort_order') ?? 0;

        $created = 0;
        $skipped = 0;
        foreach ($names as $name) {
            if (!$name || array_key_exists($name, $existingNames)) {
                $skipped++;
                continue;
            }
            $maxOrder++;
            $rep = PrepresSalesRep::create(['name' => $name, 'company' => $company, 'sort_order' => $maxOrder]);
            if ($deptIds) {
                $rep->departments()->syncWithoutDetaching($deptIds);
            }
            $existingNames[$name] = true;
            $created++;
        }

        return response()->json(['created' => $created, 'skipped' => $skipped]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $this->authorizeRoleAction($request->user());

        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        foreach ($validated['ids'] as $order => $id) {
            PrepresSalesRep::where('id', $id)->update(['sort_order' => $order + 1]);
        }

        return response()->json(['ok' => true]);
    }

    protected function normalizeRepName(string $name): string
    {
        return trim(preg_replace('/[\x{3000}\s]+/u', ' ', $name));
    }
}

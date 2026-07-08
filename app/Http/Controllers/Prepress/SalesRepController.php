<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ManagesSalesReps;
use App\Http\Controllers\Prepress\Concerns\AuthorizesPrepress;
use App\Models\PrepresSalesRep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SalesRepController extends Controller
{
    use AuthorizesPrepress;
    use ManagesSalesReps;

    protected function salesRepsViewName(): string
    {
        return 'Prepress/SalesReps/Index';
    }

    /** 製版部署専用ルートのため追加で権限チェックを行う */
    protected function authorizeRoleAction($user): void
    {
        $this->authorizePrepress($user);
    }

    // ── API（ボード・伝票モーダルから呼ばれる） ────────────────────────
    public function apiList()
    {
        return response()->json(
            PrepresSalesRep::orderBy('sort_order')->get(['id', 'name', 'company'])
        );
    }

    public function apiCreate(Request $request)
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'company' => ['nullable', 'string', 'max:200'],
        ]);

        $name = $this->normalizeRepName($validated['name']);

        if (PrepresSalesRep::where('name', $name)->exists()) {
            return response()->json(['error' => '同じ名前の営業担当が既に登録されています。'], 422);
        }

        $maxOrder = PrepresSalesRep::max('sort_order') ?? 0;
        $rep = PrepresSalesRep::create([
            'name'       => $name,
            'company'    => $validated['company'] ?? null,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['rep' => $rep->only(['id', 'name', 'company'])]);
    }

}

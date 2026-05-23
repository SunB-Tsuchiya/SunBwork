<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ManagesSalesReps;
use App\Models\PrepresSalesRep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesRepController extends Controller
{
    use ManagesSalesReps;

    protected function salesRepsViewName(): string
    {
        return 'Coordinator/SalesReps/Index';
    }

    public function apiList(): JsonResponse
    {
        return response()->json(
            PrepresSalesRep::orderBy('sort_order')->get(['id', 'name', 'company'])
        );
    }

    public function apiCreate(Request $request): JsonResponse
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

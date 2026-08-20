<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\ClerkCalendarColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClerkCalendarColorController extends Controller
{
    use ResolvesContextCompany;

    private const COLOR_KEYS = [
        'indigo', 'blue', 'cyan', 'teal', 'green',
        'yellow', 'orange', 'red', 'pink', 'purple', 'gray',
    ];

    public function index()
    {
        $companyId = $this->companyId();

        foreach (self::COLOR_KEYS as $i => $key) {
            ClerkCalendarColor::firstOrCreate(
                ['company_id' => $companyId, 'color_key' => $key],
                ['sort_order' => $i]
            );
        }

        $colors = ClerkCalendarColor::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get(['color_key', 'label', 'sort_order']);

        return response()->json($colors);
    }

    public function update(Request $request, string $colorKey)
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:20',
        ]);

        ClerkCalendarColor::where('company_id', $this->companyId())
            ->where('color_key', $colorKey)
            ->update(['label' => $validated['label'] ?: null]);

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'orders'              => 'required|array',
            'orders.*.color_key'  => 'required|string|max:20',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        $companyId = $this->companyId();

        DB::transaction(function () use ($request, $companyId) {
            foreach ($request->orders as $item) {
                ClerkCalendarColor::where('company_id', $companyId)
                    ->where('color_key', $item['color_key'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function companyId(): int
    {
        return $this->contextCompanyId() ?? Auth::user()->company_id;
    }
}

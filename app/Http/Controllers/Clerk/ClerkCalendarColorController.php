<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesClerkCalendar;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\ClerkCalendarColor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ClerkCalendarColorController extends Controller
{
    use ResolvesClerkCalendar, ResolvesContextCompany;

    private const COLOR_KEYS = [
        'indigo', 'blue', 'cyan', 'teal', 'green',
        'yellow', 'orange', 'red', 'pink', 'purple', 'gray',
    ];

    public function index()
    {
        $companyId = $this->companyId();

        foreach (self::COLOR_KEYS as $i => $key) {
            ClerkCalendarColor::firstOrCreate(
                ['company_id' => $companyId, 'calendar_kind' => $this->calendarKind(), 'color_key' => $key],
                ['sort_order' => $i]
            );
        }

        $colors = ClerkCalendarColor::inCalendar($companyId, $this->calendarKind())
            ->orderBy('sort_order')
            ->get(['color_key', 'label', 'sort_order']);

        return response()->json($colors);
    }

    public function update(Request $request, string $colorKey)
    {
        $validated = $request->validate([
            'label' => 'nullable|string|max:20',
        ]);

        ClerkCalendarColor::inCalendar($this->companyId(), $this->calendarKind())
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
        $kind = $this->calendarKind();

        DB::transaction(function () use ($request, $companyId, $kind) {
            foreach ($request->orders as $item) {
                ClerkCalendarColor::inCalendar($companyId, $kind)
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

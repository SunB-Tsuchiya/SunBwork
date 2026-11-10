<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\Analysis\ClientAnalysisService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** 売上レポートの得意先分析（サンエー印刷）。権限・会社のコンテキストは AnnualAnalysisController と同じ（suna_sales_report:view）。 */
class ClientAnalysisController extends Controller
{
    public function __construct(private ClientAnalysisService $service)
    {
    }

    public function index(Request $request)
    {
        return Inertia::render('SunA/Sales/Client', [
            // 年次分析のランキングなどから開くとき、得意先を指定できる（?client=キー）
            'initialClientKey' => $request->query('client') ? (string) $request->query('client') : null,
        ]);
    }

    public function search(Request $request)
    {
        $data = $request->validate(['keyword' => ['nullable', 'string', 'max:100'], 'limit' => ['nullable', 'integer', 'min:1', 'max:50']]);

        return response()->json(['clients' => $this->service->search((string) ($data['keyword'] ?? ''), (int) ($data['limit'] ?? 20))]);
    }

    public function detail(Request $request)
    {
        $data = $request->validate(['client_key' => ['required', 'string', 'max:255']]);
        $detail = $this->service->detail($data['client_key']);
        abort_if($detail === null, 404, '得意先が見つかりません。');

        return response()->json($detail);
    }
}

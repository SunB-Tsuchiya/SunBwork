<?php

namespace App\Http\Controllers\SunA\Sales;

use App\Http\Controllers\Controller;
use App\Services\SunA\Sales\RegistrationStatusService;
use Inertia\Inertia;

/**
 * 売上レポートのトップ「データ登録状況」（営業メニューの入口）。独立レイアウト（ReportShell）。
 * ルートは suna_sales_report:view で保護（許可された人・サンエー印刷のみ。会社のコンテキストはミドルウェアが設定）。
 */
class RegistrationStatusController extends Controller
{
    public function __construct(private RegistrationStatusService $service)
    {
    }

    public function index()
    {
        return Inertia::render('SunA/Sales/RegistrationStatus', [
            'status' => $this->service->status(),
        ]);
    }

    /** 画面の再読み込み用（取込のあとなど） */
    public function data()
    {
        return response()->json($this->service->status());
    }
}

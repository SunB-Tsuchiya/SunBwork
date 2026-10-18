<?php

namespace App\Http\Controllers\SunA\GeneralAffairs\Nencho;

use App\Http\Controllers\Controller;
use App\Support\SunA\GeneralAffairs\MarkdownGuide;
use App\Support\SunA\GeneralAffairs\StaffAccess;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * サンエー印刷 総務: 年末調整（トップ・設計ガイド）
 */
class NenchoController extends Controller
{
    public function index()
    {
        return Inertia::render('SunA/GeneralAffairs/Nencho/Index', [
            'abilities' => StaffAccess::for(Auth::user())->toArray(),
        ]);
    }

    public function guide()
    {
        // 設計ガイドの元データ: resources/docs/suna/general_affairs/nencho_overview.md（z_instructions/NENCHO_OVERVIEW_FOR_GA.md 参照）
        return Inertia::render('SunA/GeneralAffairs/Nencho/Guide', MarkdownGuide::render('nencho_overview.md'));
    }
}

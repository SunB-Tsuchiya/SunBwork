<?php

namespace App\Http\Controllers\SalesAnalysis;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class DashboardController extends Controller
{
    // Phase 6 で集計データを渡す本実装に置き換える暫定プレースホルダ（Phase 2時点はアクセス制御の疎通確認用）
    public function index()
    {
        return Inertia::render('SalesAnalysis/Dashboard');
    }
}

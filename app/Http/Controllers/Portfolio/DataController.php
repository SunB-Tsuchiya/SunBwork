<?php

namespace App\Http\Controllers\Portfolio;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Portfolio\PortfolioAccess;
use App\Services\Portfolio\PortfolioData;
use Illuminate\Http\Request;

/** 対象メンバー（未指定なら本人）のデータ一式を返す（変更操作のあとの再取得・オーナー切り替え用） */
class DataController extends Controller
{
    public function show(Request $request)
    {
        $owner = User::findOrFail(PortfolioAccess::viewOwnerId($request));

        return response()->json(PortfolioData::for($owner));
    }
}

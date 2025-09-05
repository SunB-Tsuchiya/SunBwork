<?php

use Illuminate\Support\Facades\Route;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast as BroadcastFacade;

// デバッグ用：登録ページのデータ構造を確認
Route::get('/debug/register-data', function () {
    $companies = Company::with([
        'departments' => function ($query) {
            $query->where('active', 1)
                ->with(['assignments' => function ($assignmentQuery) {
                    $assignmentQuery->where('active', 1)->orderBy('sort_order');
                }])
                ->orderBy('sort_order');
        }
    ])->where('active', 1)->get();

    return response()->json([
        'companies' => $companies,
        'debug_info' => [
            'companies_count' => $companies->count(),
            'first_company_name' => $companies->first() ? $companies->first()->name : null,
            'first_company_departments_count' => $companies->first() ? $companies->first()->departments->count() : 0,
            'first_department_name' => $companies->first() && $companies->first()->departments->first()
                ? $companies->first()->departments->first()->name : null,
            'first_department_assignments_count' => $companies->first() && $companies->first()->departments->first()
                ? $companies->first()->departments->first()->assignments->count() : 0,
            'sample_department_structure' => $companies->first() && $companies->first()->departments->first()
                ? array_keys($companies->first()->departments->first()->toArray()) : [],
        ]
    ]);
});

// デバッグ用: セッションCookieの生値と復号後、サーバ側セッションファイルを照合する
Route::get('/debug/session-check', function (Request $request) {
    $cookieName = config('session.cookie');
    $rawCookieHeader = $request->headers->get('cookie');

    // ミドルウェア適用後は $request->cookie() が復号済みを返す
    $decryptedCookie = $request->cookie($cookieName);

    // 現在のセッションID と対応するセッションファイルの確認
    $sessionId = session()->getId();
    $sessionFile = storage_path('framework/sessions/' . $sessionId);
    $exists = file_exists($sessionFile);
    $contents = null;
    if ($exists) {
        // セッションファイルはシリアライズされた配列などが直接入っているため
        // バイナリ混入を避けるため base64 にして返す
        $contents = base64_encode(file_get_contents($sessionFile));
    }

    return response()->json([
        'raw_cookie_header' => $rawCookieHeader,
        'session_cookie_name' => $cookieName,
        'decrypted_cookie_value' => $decryptedCookie,
        'session_id' => $sessionId,
        'session_file_path' => $sessionFile,
        'session_file_exists' => $exists,
        'session_file_base64' => $contents,
    ]);
});

// デバッグ: 現在のリクエストに対する auth 状態を返す
Route::get('/debug/whoami', function (Request $request) {
    return response()->json([
        'auth_check' => auth()->check(),
        'auth_id' => auth()->id(),
        'user' => auth()->user(),
        'session_id' => session()->getId(),
    ]);
});

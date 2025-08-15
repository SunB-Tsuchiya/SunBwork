<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Models\Client;
// クライアント一覧API（id, nameのみ返す）
Route::get('/clients', function () {
    return Client::select('id', 'name')->get();
});

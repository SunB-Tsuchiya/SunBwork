<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Services\DepartmentWorkRecords;
use Illuminate\Http\Request;

class DepartmentWorkRecordsController extends Controller
{
    public function __invoke(Request $request, DepartmentWorkRecords $records)
    {
        $params = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json($records->records($request->user(), $params['date'] ?? null))
            ->header('Cache-Control', 'private, no-store');
    }
}

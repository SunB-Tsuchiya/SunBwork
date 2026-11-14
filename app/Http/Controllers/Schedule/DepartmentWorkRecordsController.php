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

        $context = $request->session()->get('superadmin_context.company_id');
        return response()->json($records->records($request->user(), $params['date'] ?? null, $context === null ? null : (int) $context))
            ->header('Cache-Control', 'private, no-store');
    }
}

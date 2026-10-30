<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Schedule\DepartmentWorkController;
use App\Services\WorkloadAnalysisAccess;
use Illuminate\Http\Request;

/** Only GET routes are registered for this controller. Trial writes keep their guard. */
class WorkloadAnalysisController extends DepartmentWorkController
{
    protected function readContext(Request $request): array
    {
        return app(WorkloadAnalysisAccess::class)->resolve($request);
    }
}

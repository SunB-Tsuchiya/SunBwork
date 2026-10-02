<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\MGinbon\MGinbonProject;
use App\Services\MGinbon\MGinbonFilenameBulkService;
use App\Services\MGinbon\MGinbonProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MGinbonFilenameBulkController extends Controller
{
    public function options(MGinbonFilenameBulkService $service): JsonResponse
    {
        return response()->json(['milestones' => $service->milestoneOptions()]);
    }

    public function preview(Request $request, MGinbonFilenameBulkService $service, MGinbonProjectAccess $access): JsonResponse
    {
        [$project, $data] = $this->validated($request, $access);
        return response()->json($service->preview($project, $data['filenames'], $data['milestone_code'], $data['date'], (bool) ($data['overwrite'] ?? false)));
    }

    public function store(Request $request, MGinbonFilenameBulkService $service, MGinbonProjectAccess $access): JsonResponse
    {
        [$project, $data] = $this->validated($request, $access);
        $result = $service->commit($project, $data['filenames'], $data['milestone_code'], $data['date'], (bool) ($data['overwrite'] ?? false), (int) $request->user()->id);
        return response()->json($result);
    }

    private function validated(Request $request, MGinbonProjectAccess $access): array
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'milestone_code' => ['required', 'string', Rule::in(array_keys(MGinbonFilenameBulkService::MILESTONES))],
            'date' => ['required', 'date_format:Y-m-d'],
            'filenames' => ['required', 'string', 'max:60000'],
            'overwrite' => ['nullable', 'boolean'],
        ]);
        $project = MGinbonProject::findOrFail((int) $data['project_id']);
        $access->requireLinked($project);
        return [$project, $data];
    }
}

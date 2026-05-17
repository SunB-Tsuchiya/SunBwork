<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\CoordinatorWorkflowSheetFavorite;
use App\Models\CoordinatorSetting;
use App\Models\WorkflowSheet;
use App\Models\ProjectJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkflowSheetListController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $allowedJobIds = ProjectJob::where('user_id', $user->id)
            ->orWhereHas('coordinators', fn ($c) => $c->where('users.id', $user->id))
            ->orWhereHas('teamMembers', fn ($t) => $t->where('user_id', $user->id))
            ->pluck('id')
            ->values()
            ->all();

        $search       = $request->input('search', '');
        $yearMonth    = $request->input('month', '');
        $showComplete = $request->boolean('show_complete', false);
        $groupMode    = $request->input('group_mode', '');

        $setting = CoordinatorSetting::firstOrCreate(
            ['user_id' => $user->id],
            ['jobbox_group_mode' => 'date', 'progress_sheet_list_group_mode' => 'date']
        );
        if ($groupMode && in_array($groupMode, ['date', 'client', 'project'])) {
            $setting->update(['progress_sheet_list_group_mode' => $groupMode]);
        }
        $currentGroupMode = $setting->fresh()->progress_sheet_list_group_mode ?? 'date';

        $query = WorkflowSheet::with(['projectJob:id,title,client_id,completed', 'projectJob.client:id,name'])
            ->select('workflow_sheets.*')
            ->whereIn('project_job_id', $allowedJobIds);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('workflow_sheets.name', 'like', "%{$search}%")
                    ->orWhereHas('projectJob', fn ($j) => $j->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('projectJob.client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($yearMonth) {
            $query->whereYear('workflow_sheets.created_at', substr($yearMonth, 0, 4))
                  ->whereMonth('workflow_sheets.created_at', substr($yearMonth, 5, 2));
        }

        if (!$showComplete) {
            $query->whereHas('projectJob', fn ($q) => $q->where('completed', false));
        }

        $sheets = $query->orderBy('workflow_sheets.created_at', 'desc')->get();

        $favoriteIds = CoordinatorWorkflowSheetFavorite::where('user_id', $user->id)
            ->pluck('workflow_sheet_id')
            ->all();

        $favoriteSheets = WorkflowSheet::with(['projectJob:id,title,client_id,completed', 'projectJob.client:id,name'])
            ->whereIn('id', $favoriteIds)
            ->whereIn('project_job_id', $allowedJobIds)
            ->orderBy('created_at', 'desc')->get();

        $mapSheet = function (WorkflowSheet $sheet) use ($favoriteIds) {
            return [
                'id'                    => $sheet->id,
                'name'                  => $sheet->name,
                'created_at'            => $sheet->created_at?->format('Y-m-d'),
                'project_job_id'        => $sheet->project_job_id,
                'project_job_title'     => $sheet->projectJob?->title ?? '-',
                'project_job_completed' => (bool) ($sheet->projectJob?->completed ?? false),
                'client_name'           => $sheet->projectJob?->client?->name ?? '-',
                'is_favorite'           => in_array($sheet->id, $favoriteIds),
            ];
        };

        return Inertia::render('Coordinator/WorkflowSheetList/Index', [
            'sheets'         => $sheets->map($mapSheet)->values(),
            'favoriteSheets' => $favoriteSheets->map($mapSheet)->values(),
            'groupMode'      => $currentGroupMode,
            'filters'        => [
                'search'        => $search,
                'month'         => $yearMonth,
                'show_complete' => $showComplete,
            ],
        ]);
    }

    public function createProjectsJson(Request $request)
    {
        $user = $request->user();

        $jobs = ProjectJob::where('completed', false)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhereHas('coordinators', fn ($c) => $c->where('users.id', $user->id));
            })
            ->with('client')
            ->orderBy('title')
            ->get(['id', 'title', 'client_id']);

        $clientsMap = [];
        foreach ($jobs as $job) {
            if ($job->client) {
                $clientsMap[$job->client->id] = [
                    'id'   => $job->client->id,
                    'name' => $job->client->name ?? '-',
                ];
            }
        }

        return response()->json([
            'clients'  => array_values($clientsMap),
            'projects' => $jobs->map(fn ($j) => [
                'id'        => $j->id,
                'title'     => $j->title ?? '-',
                'client_id' => $j->client_id,
            ])->values(),
        ]);
    }

    public function toggleFavorite(Request $request, WorkflowSheet $sheet)
    {
        $user = $request->user();

        $existing = CoordinatorWorkflowSheetFavorite::where('user_id', $user->id)
            ->where('workflow_sheet_id', $sheet->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $isFavorite = false;
        } else {
            CoordinatorWorkflowSheetFavorite::create([
                'user_id'           => $user->id,
                'workflow_sheet_id' => $sheet->id,
            ]);
            $isFavorite = true;
        }

        return response()->json(['is_favorite' => $isFavorite]);
    }
}

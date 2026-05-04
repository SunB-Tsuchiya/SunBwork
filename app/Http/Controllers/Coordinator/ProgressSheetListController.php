<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\CoordinatorProgressSheetFavorite;
use App\Models\CoordinatorSetting;
use App\Models\ProgressSheet;
use App\Models\ProjectTeamMember;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgressSheetListController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // スコープ: Admin/SuperAdmin/Clerk は全件、それ以外は自分が team_member に含まれる案件のみ
        if ($user->isAdmin() || $user->isSuperAdmin() || $user->isClerk()) {
            $allowedJobIds = null;
        } else {
            $allowedJobIds = ProjectTeamMember::where('user_id', $user->id)
                ->pluck('project_job_id')
                ->unique()
                ->all();
        }

        $search       = $request->input('search', '');
        $yearMonth    = $request->input('month', '');
        $showComplete = $request->boolean('show_complete', false);
        $groupMode    = $request->input('group_mode', '');

        // ソートモードを DB に保存
        $setting = CoordinatorSetting::firstOrCreate(
            ['user_id' => $user->id],
            ['jobbox_group_mode' => 'date', 'progress_sheet_list_group_mode' => 'date']
        );
        if ($groupMode && in_array($groupMode, ['date', 'client', 'project'])) {
            $setting->update(['progress_sheet_list_group_mode' => $groupMode]);
        }
        $currentGroupMode = $setting->fresh()->progress_sheet_list_group_mode ?? 'date';

        // 進行表クエリ
        $query = ProgressSheet::with(['projectJob:id,title,client_id,completed', 'projectJob.client:id,name'])
            ->select('progress_sheets.*');

        if ($allowedJobIds !== null) {
            $query->whereIn('project_job_id', $allowedJobIds);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('progress_sheets.name', 'like', "%{$search}%")
                    ->orWhereHas('projectJob', fn ($j) => $j->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('projectJob.client', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($yearMonth) {
            $query->whereYear('progress_sheets.created_at', substr($yearMonth, 0, 4))
                  ->whereMonth('progress_sheets.created_at', substr($yearMonth, 5, 2));
        }

        if (!$showComplete) {
            $query->whereHas('projectJob', fn ($q) => $q->where('completed', false));
        }

        $sheets = $query->orderBy('progress_sheets.created_at', 'desc')->get();

        // お気に入り ID 一覧（フィルターに関係なく全件）
        $favoriteIds = CoordinatorProgressSheetFavorite::where('user_id', $user->id)
            ->pluck('progress_sheet_id')
            ->all();

        // お気に入りシート（完了案件も含む・フィルター対象外で別途取得）
        $favQuery = ProgressSheet::with(['projectJob:id,title,client_id,completed', 'projectJob.client:id,name'])
            ->whereIn('id', $favoriteIds);
        if ($allowedJobIds !== null) {
            $favQuery->whereIn('project_job_id', $allowedJobIds);
        }
        $favoriteSheets = $favQuery->orderBy('created_at', 'desc')->get();

        $mapSheet = function (ProgressSheet $sheet) use ($favoriteIds) {
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

        return Inertia::render('Coordinator/ProgressSheetList/Index', [
            'sheets'          => $sheets->map($mapSheet)->values(),
            'favoriteSheets'  => $favoriteSheets->map($mapSheet)->values(),
            'groupMode'       => $currentGroupMode,
            'filters'         => [
                'search'        => $search,
                'month'         => $yearMonth,
                'show_complete' => $showComplete,
            ],
        ]);
    }

    public function toggleFavorite(Request $request, ProgressSheet $sheet)
    {
        $user = $request->user();

        $existing = CoordinatorProgressSheetFavorite::where('user_id', $user->id)
            ->where('progress_sheet_id', $sheet->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $isFavorite = false;
        } else {
            CoordinatorProgressSheetFavorite::create([
                'user_id'           => $user->id,
                'progress_sheet_id' => $sheet->id,
            ]);
            $isFavorite = true;
        }

        return response()->json(['is_favorite' => $isFavorite]);
    }
}

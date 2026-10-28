<?php

namespace App\Http\Controllers;

use App\Models\ProjectCalendarColor;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\ProjectTeamMember;
use App\Services\ClerkCalendarHolidays;
use App\Support\CalendarColors;
use Illuminate\Http\Request;

/**
 * 案件のカレンダー: 色設定（案件ごと）と、祝日（会社の休日カレンダーの読み取り）。
 *  - 色設定を変更できるのは、その案件を編集できる人（コーディネーター・Clerk・Leader・管理者。同じ会社。SuperAdmin は全社）
 *  - 見られるのは、上の人と、その案件に関わる人（アサイン・チームメンバー。ユーザー用の案件詳細と同じ条件）
 */
class ProjectCalendarController extends Controller
{
    public function colors(Request $request, ProjectJob $projectJob)
    {
        $level = $this->access($request, $projectJob);

        return response()->json(CalendarColors::listFor(ProjectCalendarColor::class, 'project_job_id', $projectJob->id) + ['can_edit' => $level === 'edit']);
    }

    public function updateColor(Request $request, ProjectJob $projectJob, string $colorKey)
    {
        abort_unless($this->access($request, $projectJob) === 'edit', 403, 'この案件の色設定を変更する権限がありません。');
        $validated = $request->validate(['label' => 'nullable|string|max:20']);

        return response()->json(CalendarColors::updateLabel(ProjectCalendarColor::class, 'project_job_id', $projectJob->id, $colorKey, $validated['label'] ?? null, $request->user()->id));
    }

    public function reorderColors(Request $request, ProjectJob $projectJob)
    {
        abort_unless($this->access($request, $projectJob) === 'edit', 403, 'この案件の色設定を変更する権限がありません。');
        $request->validate([
            'orders'              => 'required|array',
            'orders.*.color_key'  => 'required|string|max:20',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        return response()->json(CalendarColors::reorder(ProjectCalendarColor::class, 'project_job_id', $projectJob->id, $request->orders, $request->user()->id));
    }

    /** 案件の会社の祝日（年の前後を含む）。会社が無い案件は空。DB は書き換えない */
    public function holidays(Request $request, ProjectJob $projectJob, ClerkCalendarHolidays $holidays)
    {
        $this->access($request, $projectJob);
        $request->validate(['year' => 'sometimes|required|integer|min:1900|max:2100']);
        $year = (int) $request->input('year', now('Asia/Tokyo')->year);

        return response()->json($projectJob->company_id ? $holidays->forCompany((int) $projectJob->company_id, $year) : []);
    }

    /** 'edit'（色設定を変更できる）／ 'view'（見るだけ）／ それ以外は 403 */
    private function access(Request $request, ProjectJob $job): string
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            return 'edit';
        }
        $sameCompany = ! $job->company_id || (int) $job->company_id === (int) $user->company_id;
        if ($sameCompany && ($user->isCoordinator() || $user->isClerk() || $user->isLeader() || $user->isAdmin())) {
            return 'edit';
        }

        $related = ProjectJobAssignment::where('project_job_id', $job->id)
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('sender_id', $user->id))->exists()
            || ProjectTeamMember::where('project_job_id', $job->id)->where('user_id', $user->id)->exists();
        abort_unless($related, 403);

        return 'view';
    }
}

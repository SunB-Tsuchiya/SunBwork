<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProgressCell;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class ProgressCellController extends Controller
{
    /**
     * workerセル / schedlinkセルを完了にする（User：自分が担当するセルのみ）
     * POST /user/progress-cells/{cell}/complete
     */
    public function complete(Request $request, ProgressCell $cell)
    {
        $user = $request->user();
        abort_unless($user, 401);

        // 自分が担当するセル（value_user_id）のみ完了可能
        abort_unless((int)$cell->value_user_id === (int)$user->id, 403);

        $now = Carbon::now();
        $cell->completed_at = $now;
        $cell->save();

        // schedlink型の場合、紐づいたスケジュールも完了にする
        if ($cell->cell_type === 'schedlink' && $cell->schedule_id) {
            \App\Models\ProjectSchedule::where('id', $cell->schedule_id)
                ->update(['completed_at' => $now]);
        }

        return response()->json(['success' => true, 'completed_at' => $cell->completed_at->toDateTimeString()]);
    }

    public function myAssignments(Request $request)
    {
        $user = $request->user();

        $cells = ProgressCell::where('value_user_id', $user->id)
            ->where(function ($q) {
                $q->whereIn('cell_type', ['worker', 'user'])->orWhereNull('cell_type');
            })
            ->with([
                'row:id,label,sheet_id',
                'row.sheet:id,name,project_job_id,column_config',
                'row.sheet.projectJob:id,title,client_id',
                'row.sheet.projectJob.client:id,name',
                'schedule:id,name,end_date',
                'assignment:id,desired_end_date,completed',
            ])
            ->get()
            ->map(function ($cell) {
                $deadline = $cell->cell_deadline?->format('Y-m-d')
                    ?? $cell->schedule?->end_date?->format('Y-m-d')
                    ?? $cell->assignment?->desired_end_date?->format('Y-m-d');

                $colLabel = $this->findColLabel(
                    $cell->row?->sheet?->column_config ?? [],
                    $cell->col_key
                );

                return [
                    'id'               => $cell->id,
                    'project_job_id'   => $cell->row?->sheet?->projectJob?->id,
                    'project_job_title'=> $cell->row?->sheet?->projectJob?->title ?? '-',
                    'client_name'      => $cell->row?->sheet?->projectJob?->client?->name ?? '-',
                    'sheet_id'         => $cell->row?->sheet?->id,
                    'sheet_name'       => $cell->row?->sheet?->name ?? '-',
                    'row_label'        => $cell->row?->label ?? '-',
                    'col_label'        => $colLabel,
                    'deadline'         => $deadline,
                    'completed_at'     => $cell->completed_at?->format('Y-m-d H:i'),
                ];
            })
            ->sortBy(fn($c) => $c['deadline'] ?? '9999-99-99')
            ->values();

        return Inertia::render('User/ProgressCells/Index', [
            'cells' => $cells,
        ]);
    }

    private function findColLabel(array $nodes, string $key): string
    {
        foreach ($nodes as $node) {
            if (($node['key'] ?? '') === $key) {
                return $node['label'] ?? $key;
            }
            if (!empty($node['children'])) {
                $found = $this->findColLabel($node['children'], $key);
                if ($found !== $key) {
                    return $found;
                }
            }
        }
        return $key;
    }
}

<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\WorkflowSheet;
use App\Models\WorkflowCell;
use Inertia\Inertia;

class WorkflowSheetController extends Controller
{
    /**
     * 共有トークンで管理シートを読み取り専用表示（認証不要）
     */
    public function show(string $token)
    {
        $sheet = WorkflowSheet::where('share_token', $token)->firstOrFail();
        $sheet->load(['projectJob.client']);
        $projectJob = $sheet->projectJob;

        $rows = $sheet->rows()->orderBy('sort_order')
            ->get(['id', 'parent_id', 'label', 'sort_order', 'stage_id']);

        $cells = WorkflowCell::whereIn('row_id', $rows->pluck('id'))
            ->with([
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'schedule:id,name,end_date,completed_at',
                'noteUser:id,name,user_role',
            ])
            ->get()
            ->map(fn($c) => [
                'id'                       => $c->id,
                'row_id'                   => $c->row_id,
                'stage_key'                => $c->stage_key,
                'cell_type'                => $c->cell_type ?? 'worker',
                'value_text'               => $c->value_text,
                'value_date'               => $c->value_date?->format('Y-m-d'),
                'value_bool'               => $c->value_bool,
                'value_user_id'            => null,
                'value_user_name'          => $c->valueUser?->name ?? $c->assignedUser?->name,
                'value_subcontractor_id'   => null,
                'value_subcontractor_name' => $c->valueSubcontractor?->name,
                'assignment_id'            => null,
                'assignment_completed'     => $c->completed_at !== null,
                'schedule_id'              => $c->schedule_id,
                'schedule_name'            => $c->schedule?->name,
                'schedule_end_date'        => $c->schedule?->end_date?->format('Y-m-d'),
                'schedule_completed_at'    => $c->schedule?->completed_at?->format('Y-m-d H:i:s'),
                'cell_deadline'            => $c->cell_deadline?->format('Y-m-d'),
                'cell_note'                => $c->cell_note,
                'cell_note_user_name'      => $c->noteUser?->name,
                'cell_note_user_role'      => $c->noteUser?->user_role,
                'completed_at'             => $c->completed_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Shared/WorkflowSheets/Show', [
            'sheet' => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $sheet->getEffectiveColumnConfig(),
            ],
            'token'      => $token,
            'rows'       => $rows,
            'cells'      => $cells,
            'projectJob' => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
            ],
        ]);
    }
}

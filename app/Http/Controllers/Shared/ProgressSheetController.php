<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\ProgressSheet;
use App\Models\ProgressCell;
use App\Models\ProgressRow;
use Inertia\Inertia;

class ProgressSheetController extends Controller
{
    /**
     * 共有トークンで進行管理表を読み取り専用表示（認証不要）
     */
    public function show(string $token)
    {
        $sheet = ProgressSheet::where('share_token', $token)->firstOrFail();
        $sheet->load(['projectJob.client', 'projectJob.size']);
        $projectJob = $sheet->projectJob;

        $rows = $sheet->rows()->orderBy('order')->get(['id', 'label', 'order', 'parent_id']);

        $cells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->with([
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'assignment:id,desired_end_date,completed',
                'schedule:id,name,end_date,completed_at',
                'noteUser:id,name,user_role',
            ])
            ->get()
            ->map(fn($c) => [
                'id'                       => $c->id,
                'row_id'                   => $c->row_id,
                'col_key'                  => $c->col_key,
                'cell_type'                => $c->cell_type,
                'value_text'               => $c->value_text,
                'value_date'               => $c->value_date?->format('Y-m-d'),
                'value_bool'               => $c->value_bool,
                'value_user_id'            => $c->value_user_id,
                'value_user_name'          => $c->valueUser?->name,
                'value_subcontractor_id'   => $c->value_subcontractor_id,
                'value_subcontractor_name' => $c->valueSubcontractor?->name,
                // 内部ID非公開・完了状況のみ公開
                'assignment_id'            => null,
                'assignment_completed'     => $c->completed_at !== null || ($c->assignment?->completed ?? false),
                'assignment_end_date'      => $c->assignment?->desired_end_date?->format('Y-m-d'),
                'proof_assignment_id'      => null,
                'proof_assignment_completed' => false,
                // schedlink
                'schedule_id'              => $c->schedule_id,
                'schedule_name'            => $c->schedule?->name,
                'schedule_end_date'        => $c->schedule?->end_date?->format('Y-m-d'),
                'schedule_completed_at'    => $c->schedule?->completed_at?->format('Y-m-d H:i:s'),
                // V2共通
                'cell_deadline'            => $c->cell_deadline?->format('Y-m-d'),
                'cell_note'                => $c->cell_note,
                'cell_note_user_name'      => $c->noteUser?->name,
                'cell_note_user_role'      => $c->noteUser?->user_role,
                'completed_at'             => $c->completed_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Shared/ProgressSheets/Show', [
            'sheet' => [
                'id'            => $sheet->id,
                'name'          => $sheet->name,
                'column_config' => $sheet->column_config,
            ],
            'token'      => $token,
            'rows'       => $rows,
            'cells'      => $cells,
            'projectJob' => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
                'size_name'   => $projectJob->size?->name,
                'page_count'  => $projectJob->page_count,
            ],
        ]);
    }

    /**
     * 共有トークンで印刷専用ページを表示（認証不要）
     */
    public function printView(string $token)
    {
        $sheet = ProgressSheet::where('share_token', $token)->firstOrFail();
        $sheet->load(['projectJob.client', 'projectJob.size']);
        $projectJob = $sheet->projectJob;

        $rows  = $sheet->rows()->orderBy('order')->get(['id', 'label', 'order', 'parent_id']);
        $cells = ProgressCell::whereIn('row_id', $rows->pluck('id'))
            ->with([
                'valueUser:id,name',
                'valueSubcontractor:id,name',
                'assignment:id,desired_end_date,completed',
                'schedule:id,name,end_date,completed_at',
                'noteUser:id,name,user_role',
            ])
            ->get()
            ->map(fn($c) => [
                'id'                       => $c->id,
                'row_id'                   => $c->row_id,
                'col_key'                  => $c->col_key,
                'cell_type'                => $c->cell_type,
                'value_text'               => $c->value_text,
                'value_date'               => $c->value_date?->format('Y-m-d'),
                'value_bool'               => $c->value_bool,
                'value_user_id'            => $c->value_user_id,
                'value_user_name'          => $c->valueUser?->name,
                'value_subcontractor_id'   => $c->value_subcontractor_id,
                'value_subcontractor_name' => $c->valueSubcontractor?->name,
                'assignment_id'            => null,
                'assignment_completed'     => $c->completed_at !== null || ($c->assignment?->completed ?? false),
                'assignment_end_date'      => $c->assignment?->desired_end_date?->format('Y-m-d'),
                'proof_assignment_id'      => null,
                'proof_assignment_completed' => false,
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

        return Inertia::render('Shared/ProgressSheets/Print', [
            'sheet'      => ['id' => $sheet->id, 'name' => $sheet->name, 'column_config' => $sheet->column_config],
            'rows'       => $rows,
            'cells'      => $cells,
            'projectJob' => [
                'id'          => $projectJob->id,
                'title'       => $projectJob->title,
                'client_name' => $projectJob->client?->name,
                'size_name'   => $projectJob->size?->name,
                'page_count'  => $projectJob->page_count,
            ],
        ]);
    }
}

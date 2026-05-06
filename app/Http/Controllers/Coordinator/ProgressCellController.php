<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressCell;
use App\Models\ProgressRow;
use App\Models\ProgressSheet;
use App\Models\User;
use App\Services\ProgressLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ProgressCellController extends Controller
{
    /**
     * セルを一括更新（upsert）
     * リクエスト: { cells: [{ row_id, col_key, value_type, value }] }
     * value_type: 'text' | 'date' | 'bool' | 'user'
     */
    public function bulkUpdate(Request $request, ProgressSheet $sheet)
    {
        $user = $request->user();
        $this->authorizeAccess($user, $sheet);

        $validated = $request->validate([
            'cells'                    => 'required|array',
            'cells.*.row_id'           => 'required|integer',
            'cells.*.col_key'          => 'required|string',
            'cells.*.value_type'       => 'required|in:text,date,bool,user,subcontractor,worker,schedlink,proof_user',
            'cells.*.value'            => 'nullable',
            'cells.*.schedule_id'      => 'nullable|integer',
            'cells.*.cell_deadline'    => 'nullable|date',
            'cells.*.subcontractor_id' => 'nullable|integer',
        ]);

        // シートに属する row_id のみ許可
        $allowedRowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id')->toArray();

        DB::transaction(function () use ($validated, $allowedRowIds) {
            foreach ($validated['cells'] as $item) {
                if (!in_array($item['row_id'], $allowedRowIds)) {
                    continue;
                }

                $data = [
                    'value_text'             => null,
                    'value_date'             => null,
                    'value_bool'             => null,
                    'value_user_id'          => null,
                    'value_subcontractor_id' => null,
                ];

                switch ($item['value_type']) {
                    case 'text':
                        $data['value_text'] = $item['value'];
                        break;
                    case 'date':
                        $data['value_date'] = $item['value'] ?: null;
                        break;
                    case 'bool':
                        $data['value_bool'] = (bool) $item['value'];
                        break;
                    case 'user':
                        $data['value_user_id'] = $item['value'] ?: null;
                        break;
                    case 'subcontractor':
                        $data['value_subcontractor_id'] = $item['value'] ?: null;
                        break;
                    case 'worker':
                        // value は user_id（社内）/ subcontractor_id は別フィールドで来る
                        $data['cell_type'] = 'worker';
                        if (!empty($item['subcontractor_id'])) {
                            $data['value_subcontractor_id'] = $item['subcontractor_id'];
                            $data['value_user_id'] = null;
                        } else {
                            $data['value_user_id'] = $item['value'] ?: null;
                            $data['value_subcontractor_id'] = null;
                        }
                        if (isset($item['schedule_id'])) {
                            $data['schedule_id'] = $item['schedule_id'] ?: null;
                        }
                        if (isset($item['cell_deadline'])) {
                            $data['cell_deadline'] = $item['cell_deadline'] ?: null;
                        }
                        break;
                    case 'proof_user':
                        $data['cell_type'] = 'proof_user';
                        $data['value_user_id'] = $item['value'] ?: null;
                        break;
                    case 'schedlink':
                        // value は schedule_id
                        $data['schedule_id'] = $item['value'] ?: null;
                        $data['cell_type'] = 'schedlink';
                        break;
                }

                ProgressCell::updateOrCreate(
                    ['row_id' => $item['row_id'], 'col_key' => $item['col_key']],
                    $data
                );
            }
        });

        return back()->with('success', 'セルを保存しました。');
    }

    /**
     * workerセル / schedlinkセルを完了にする
     * POST /coordinator/progress-cells/{cell}/complete
     */
    public function complete(Request $request, ProgressCell $cell)
    {
        $user = $request->user();
        $sheet = $cell->row->sheet;
        $this->authorizeAccess($user, $sheet);

        $now = Carbon::now();
        $cell->completed_at = $now;
        $cell->save();

        // schedlink型の場合、紐づいたスケジュールも完了にする
        if ($cell->cell_type === 'schedlink' && $cell->schedule_id) {
            \App\Models\ProjectSchedule::where('id', $cell->schedule_id)
                ->update(['completed_at' => $now]);
        }

        ProgressLinkService::recalculate($cell);

        return response()->json(['success' => true, 'completed_at' => $cell->completed_at->toDateTimeString()]);
    }

    /**
     * 締め切りを手動上書き
     * PATCH /coordinator/progress-cells/{cell}/deadline
     */
    public function deadline(Request $request, ProgressCell $cell)
    {
        $user = $request->user();
        $sheet = $cell->row->sheet;
        $this->authorizeAccess($user, $sheet);

        $validated = $request->validate([
            'cell_deadline' => 'nullable|date',
        ]);

        $cell->cell_deadline = $validated['cell_deadline'] ?: null;
        $cell->save();

        return response()->json(['success' => true, 'cell_deadline' => $cell->cell_deadline?->toDateString()]);
    }

    /**
     * セルメモを行/列位置で保存（セルが未作成でも upsert）
     * POST /coordinator/progress-sheets/{sheet}/cell-note
     */
    public function noteByPosition(Request $request, ProgressSheet $sheet)
    {
        $user = $request->user();
        $this->authorizeAccess($user, $sheet);

        $validated = $request->validate([
            'row_id'    => 'required|integer',
            'col_key'   => 'required|string',
            'cell_note' => 'nullable|string|max:2000',
        ]);

        $allowedRowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id')->toArray();
        abort_unless(in_array((int)$validated['row_id'], $allowedRowIds), 422);

        $note = $validated['cell_note'] ?: null;
        $cell = ProgressCell::updateOrCreate(
            ['row_id' => $validated['row_id'], 'col_key' => $validated['col_key']],
            [
                'cell_note'         => $note,
                'cell_note_user_id' => $note ? $user->id : null,
            ]
        );

        return response()->json(['success' => true, 'cell_id' => $cell->id]);
    }

    /**
     * セルメモを保存
     * PATCH /coordinator/progress-cells/{cell}/note
     */
    public function note(Request $request, ProgressCell $cell)
    {
        $user = $request->user();
        $sheet = $cell->row->sheet;
        $this->authorizeAccess($user, $sheet);

        $validated = $request->validate([
            'cell_note' => 'nullable|string|max:2000',
        ]);

        $cell->cell_note = $validated['cell_note'] ?: null;
        $cell->cell_note_user_id = $validated['cell_note'] ? $user->id : null;
        $cell->save();

        return response()->json(['success' => true]);
    }

    // ─────

    private function authorizeAccess(User $user, ProgressSheet $sheet): void
    {
        $pj = $sheet->projectJob;
        $isOwner  = $pj->user_id === $user->id;
        $isSub    = $pj->coordinators()->where('users.id', $user->id)->exists();
        $isMember = $pj->teamMembers()->where('user_id', $user->id)->exists();
        $isAdmin  = in_array($user->user_role, ['admin', 'superadmin']);

        abort_unless($isOwner || $isSub || $isMember || $isAdmin, 403);
    }
}

<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressCell;
use App\Models\ProgressRow;
use App\Models\ProgressSheet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            'cells'              => 'required|array',
            'cells.*.row_id'     => 'required|integer',
            'cells.*.col_key'    => 'required|string',
            'cells.*.value_type' => 'required|in:text,date,bool,user',
            'cells.*.value'      => 'nullable',
        ]);

        // シートに属する row_id のみ許可
        $allowedRowIds = ProgressRow::where('sheet_id', $sheet->id)->pluck('id')->toArray();

        DB::transaction(function () use ($validated, $allowedRowIds) {
            foreach ($validated['cells'] as $item) {
                if (!in_array($item['row_id'], $allowedRowIds)) {
                    continue;
                }

                $data = [
                    'value_text'    => null,
                    'value_date'    => null,
                    'value_bool'    => null,
                    'value_user_id' => null,
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
                }

                ProgressCell::updateOrCreate(
                    ['row_id' => $item['row_id'], 'col_key' => $item['col_key']],
                    $data
                );
            }
        });

        return back()->with('success', 'セルを保存しました。');
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

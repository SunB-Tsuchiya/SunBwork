<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\WorkflowSheet;
use App\Models\WorkflowRow;
use App\Models\ProjectItemEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowRowController extends Controller
{
    public function store(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeSheet($request->user(), $sheet);

        $validated = $request->validate([
            'label'         => 'required|string|max:255',
            'item_entry_id' => 'nullable|integer|exists:project_item_entries,id',
            'parent_id'     => 'nullable|integer|exists:workflow_rows,id',
        ]);

        $row = WorkflowRow::create([
            'sheet_id'      => $sheet->id,
            'parent_id'     => $validated['parent_id'] ?? null,
            'label'         => $validated['label'],
            'sort_order'    => WorkflowRow::where('sheet_id', $sheet->id)->max('sort_order') + 1,
            'item_entry_id' => $validated['item_entry_id'] ?? null,
        ]);

        return response()->json(['row' => $this->formatRow($row)]);
    }

    public function import(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeSheet($request->user(), $sheet);

        $validated = $request->validate([
            'entry_ids'   => 'required|array',
            'entry_ids.*' => 'integer|exists:project_item_entries,id',
        ]);

        $base = WorkflowRow::where('sheet_id', $sheet->id)->max('sort_order') + 1;
        $entries = ProjectItemEntry::whereIn('id', $validated['entry_ids'])
            ->where('project_job_id', $sheet->project_job_id)
            ->orderBy('sort_order')
            ->get();

        $rows = DB::transaction(function () use ($sheet, $entries, $base) {
            $created = [];
            foreach ($entries as $i => $entry) {
                $created[] = WorkflowRow::create([
                    'sheet_id'      => $sheet->id,
                    'label'         => $entry->name,
                    'sort_order'    => $base + $i,
                    'item_entry_id' => $entry->id,
                ]);
            }
            return $created;
        });

        return response()->json(['rows' => collect($rows)->map(fn($r) => $this->formatRow($r))]);
    }

    public function update(Request $request, WorkflowSheet $sheet, WorkflowRow $row)
    {
        $this->authorizeSheet($request->user(), $sheet);
        abort_unless($row->sheet_id === $sheet->id, 404);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $row->update($validated);

        return response()->json(['row' => $this->formatRow($row)]);
    }

    public function destroy(Request $request, WorkflowSheet $sheet, WorkflowRow $row)
    {
        $this->authorizeSheet($request->user(), $sheet);
        abort_unless($row->sheet_id === $sheet->id, 404);

        $row->delete();

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request, WorkflowSheet $sheet)
    {
        $this->authorizeSheet($request->user(), $sheet);

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($sheet, $validated) {
            foreach ($validated['ids'] as $order => $id) {
                WorkflowRow::where('id', $id)
                    ->where('sheet_id', $sheet->id)
                    ->update(['sort_order' => $order]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function authorizeSheet($user, WorkflowSheet $sheet): void
    {
        $job       = $sheet->projectJob;
        $isOwner   = $job->user_id === $user->id;
        $isSub     = $job->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin   = in_array($user->user_role, ['admin', 'superadmin']);
        $isCreator = $sheet->created_by === $user->id;

        abort_unless($isOwner || $isSub || $isAdmin || $isCreator, 403);
    }

    private function formatRow(WorkflowRow $row): array
    {
        return [
            'id'            => $row->id,
            'label'         => $row->label,
            'sort_order'    => $row->sort_order,
            'item_entry_id' => $row->item_entry_id,
        ];
    }
}

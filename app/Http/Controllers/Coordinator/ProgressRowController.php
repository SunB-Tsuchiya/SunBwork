<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProgressRow;
use App\Models\ProgressSheet;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressRowController extends Controller
{
    /**
     * 行を追加
     */
    public function store(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeEdit($request->user(), $sheet);

        $validated = $request->validate([
            'label'     => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:progress_rows,id',
        ]);

        // 孫行は禁止
        if (!empty($validated['parent_id'])) {
            $parent = ProgressRow::findOrFail($validated['parent_id']);
            abort_unless($parent->sheet_id === $sheet->id, 422, '親行が同じシートに属していません。');
            abort_unless(is_null($parent->parent_id), 422, '孫行は作成できません。');
        }

        $maxOrder = $sheet->rows()->max('order') ?? -1;

        ProgressRow::create([
            'sheet_id'  => $sheet->id,
            'label'     => $validated['label'],
            'order'     => $maxOrder + 1,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        return back()->with('success', '行を追加しました。');
    }

    /**
     * 既存のフラット行をグループ親に変換し、その子行を1件追加
     */
    public function makeGroup(Request $request, ProgressSheet $sheet, ProgressRow $row)
    {
        $this->authorizeEdit($request->user(), $sheet);
        abort_unless($row->sheet_id === $sheet->id, 404);
        abort_unless(is_null($row->parent_id), 422, 'すでに子行のため、グループ親にできません。');

        $validated = $request->validate([
            'child_label' => 'required|string|max:255',
        ]);

        $maxOrder = $sheet->rows()->max('order') ?? -1;

        ProgressRow::create([
            'sheet_id'  => $sheet->id,
            'label'     => $validated['child_label'],
            'order'     => $maxOrder + 1,
            'parent_id' => $row->id,
        ]);

        return back()->with('success', 'グループ化しました。');
    }

    /**
     * 行ラベルを更新
     */
    public function update(Request $request, ProgressSheet $sheet, ProgressRow $row)
    {
        $this->authorizeEdit($request->user(), $sheet);
        abort_unless($row->sheet_id === $sheet->id, 404);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
        ]);

        $row->update($validated);

        return back()->with('success', '行を更新しました。');
    }

    /**
     * 行を削除
     */
    public function destroy(Request $request, ProgressSheet $sheet, ProgressRow $row)
    {
        $this->authorizeEdit($request->user(), $sheet);
        abort_unless($row->sheet_id === $sheet->id, 404);

        // 子行を先に削除（SQLite は CASCADE FK 未対応のため）
        ProgressRow::where('parent_id', $row->id)->delete();
        $row->delete();

        return back()->with('success', '行を削除しました。');
    }

    /**
     * CSV/テキストから複数行を一括インポート
     * リクエスト: { labels: string[] }
     */
    public function import(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeEdit($request->user(), $sheet);

        $validated = $request->validate([
            'labels'   => 'required|array|min:1',
            'labels.*' => 'required|string|max:255',
        ]);

        $maxOrder = $sheet->rows()->max('order') ?? -1;

        $rows = [];
        foreach ($validated['labels'] as $i => $label) {
            $rows[] = [
                'sheet_id'   => $sheet->id,
                'label'      => $label,
                'order'      => $maxOrder + $i + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProgressRow::insert($rows);

        return back()->with('success', count($rows) . '行をインポートしました。');
    }

    /**
     * 並び替え
     * リクエスト: { ids: number[] } — 新しい順序で並んだ行ID配列
     */
    public function reorder(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeEdit($request->user(), $sheet);

        $validated = $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'required|integer',
        ]);

        DB::transaction(function () use ($validated, $sheet) {
            foreach ($validated['ids'] as $order => $id) {
                ProgressRow::where('id', $id)
                    ->where('sheet_id', $sheet->id)
                    ->update(['order' => $order]);
            }
        });

        return back()->with('success', '並び替えを保存しました。');
    }

    // ─────

    private function authorizeEdit(User $user, ProgressSheet $sheet): void
    {
        $pj = $sheet->projectJob;
        $isOwner = $pj->user_id === $user->id;
        $isSub   = $pj->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);

        abort_unless($isOwner || $isSub || $isAdmin, 403);
    }
}

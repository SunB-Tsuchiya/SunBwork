<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProjectJobItem;
use App\Models\ProjectSchedule;
use App\Models\ProgressRow;
use App\Models\ProgressSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectJobItemController extends Controller
{
    /**
     * 固定分類リスト
     */
    public static function categories(): array
    {
        return ['全体スケジュール', '組版', '校正', '入稿', '出力・納品', 'サブ', 'その他'];
    }

    /**
     * 案件の項目一覧を返す（JSON）
     */
    public function index(ProjectJob $projectJob)
    {
        $this->authorizeCoordinator($projectJob);

        $items = $projectJob->items()
            ->orderBy('category')
            ->orderBy('order')
            ->get()
            ->map(fn($i) => $this->formatItem($i));

        return response()->json([
            'items'      => $items,
            'categories' => self::categories(),
        ]);
    }

    /**
     * 項目を作成する
     */
    public function store(Request $request, ProjectJob $projectJob)
    {
        $this->authorizeCoordinator($projectJob);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'category'   => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'deadline'   => 'nullable|date',
        ]);

        $maxOrder = $projectJob->items()
            ->where('category', $validated['category'] ?? null)
            ->max('order') ?? -1;

        $item = ProjectJobItem::create([
            'project_job_id' => $projectJob->id,
            'name'           => $validated['name'],
            'category'       => $validated['category'] ?? null,
            'start_date'     => $validated['start_date'] ?? null,
            'deadline'       => $validated['deadline'] ?? null,
            'order'          => $maxOrder + 1,
        ]);

        return response()->json($this->formatItem($item));
    }

    /**
     * 項目を更新し、紐づいたスケジュール・進行表行に同期する
     */
    public function update(Request $request, ProjectJob $projectJob, ProjectJobItem $item)
    {
        $this->authorizeCoordinator($projectJob);
        abort_unless($item->project_job_id === $projectJob->id, 404);

        $validated = $request->validate([
            'name'       => 'nullable|string|max:255',
            'category'   => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'deadline'   => 'nullable|date',
            'order'      => 'nullable|integer',
        ]);

        $item->update($validated);
        $item->refresh();

        // 紐づいたカレンダー予定に日付・名前を同期
        ProjectSchedule::where('project_job_item_id', $item->id)
            ->get()
            ->each(function ($schedule) use ($item) {
                $schedule->update([
                    'name'       => $item->name,
                    'start_date' => $item->start_date?->format('Y-m-d'),
                    'end_date'   => $item->deadline?->format('Y-m-d'),
                ]);
            });

        // 紐づいた進行表行の締切を同期
        ProgressRow::where('project_job_item_id', $item->id)
            ->update(['deadline' => $item->deadline?->format('Y-m-d')]);

        return response()->json($this->formatItem($item));
    }

    /**
     * 項目を削除（紐づき解除のみ、スケジュール・行は残す）
     */
    public function destroy(ProjectJob $projectJob, ProjectJobItem $item)
    {
        $this->authorizeCoordinator($projectJob);
        abort_unless($item->project_job_id === $projectJob->id, 404);

        ProjectSchedule::where('project_job_item_id', $item->id)
            ->update(['project_job_item_id' => null]);

        ProgressRow::where('project_job_item_id', $item->id)
            ->update(['project_job_item_id' => null]);

        $item->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * 指定分類の項目を進行管理表の行として一括追加する
     */
    public function loadIntoSheet(Request $request, ProjectJob $projectJob, ProgressSheet $sheet)
    {
        $this->authorizeCoordinator($projectJob);
        abort_unless($sheet->project_job_id === $projectJob->id, 404);

        $validated = $request->validate([
            'category' => 'nullable|string|max:255',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer|exists:project_job_items,id',
        ]);

        $query = $projectJob->items()->orderBy('order');

        if (!empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        if (!empty($validated['item_ids'])) {
            $query->whereIn('id', $validated['item_ids']);
        }

        // すでに紐づいている項目はスキップ
        $linkedIds = ProgressRow::where('sheet_id', $sheet->id)
            ->whereNotNull('project_job_item_id')
            ->pluck('project_job_item_id')
            ->toArray();

        $items = $query->whereNotIn('id', $linkedIds)->get();

        $maxOrder = $sheet->rows()->max('order') ?? -1;

        DB::transaction(function () use ($items, $sheet, &$maxOrder) {
            foreach ($items as $item) {
                $maxOrder++;
                ProgressRow::create([
                    'sheet_id'            => $sheet->id,
                    'label'               => $item->name,
                    'order'               => $maxOrder,
                    'project_job_item_id' => $item->id,
                    'deadline'            => $item->deadline?->format('Y-m-d'),
                ]);
            }
        });

        return response()->json(['status' => 'ok', 'added' => $items->count()]);
    }

    // ── private ──────────────────────────────────────────────────────────────

    private function formatItem(ProjectJobItem $item): array
    {
        return [
            'id'         => $item->id,
            'name'       => $item->name,
            'category'   => $item->category,
            'start_date' => $item->start_date?->format('Y-m-d'),
            'deadline'   => $item->deadline?->format('Y-m-d'),
            'order'      => $item->order,
        ];
    }

    private function authorizeCoordinator(ProjectJob $projectJob): void
    {
        $user = request()->user();
        abort_unless(
            in_array($user->role, ['coordinator', 'clerk', 'admin', 'superadmin']),
            403
        );
    }
}

<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use App\Models\ProjectJobItem;
use App\Models\ProjectSchedule;
use App\Models\ProgressRow;
use App\Models\ProgressSheet;
use App\Services\ProgressLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressSheetItemController extends Controller
{
    /**
     * 進行表の連携設定一覧を返す（JSON）
     */
    public function index(ProgressSheet $sheet)
    {
        $this->authorizeCoordinator($sheet);

        $items = $sheet->linkSettings()
            ->orderBy('type')
            ->orderBy('order')
            ->get()
            ->map(fn($i) => $this->formatItem($i));

        // 進行表の行一覧（row セレクター用）
        $rows = ProgressRow::where('sheet_id', $sheet->id)
            ->orderBy('order')
            ->get(['id', 'label', 'parent_id']);

        // column_config のリーフ列一覧（col セレクター用）
        $columns = $this->flattenColumns($sheet->column_config ?? []);

        // 全階層列一覧（col_key セレクター用：親グループも選択可）
        $allColumns = $this->flattenAllColumns($sheet->column_config ?? []);

        // 案件に紐づくスケジュール一覧（進捗連携セレクター用）
        $schedules = ProjectSchedule::where('project_job_id', $sheet->project_job_id)
            ->orderBy('start_date')
            ->get(['id', 'name', 'start_date', 'end_date']);

        return response()->json([
            'items'        => $items,
            'rows'         => $rows,
            'columns'      => $columns,
            'allColumns'   => $allColumns,
            'columnConfig' => $sheet->column_config ?? [],
            'schedules'    => $schedules,
        ]);
    }

    /**
     * 連携設定を1件作成する
     */
    public function store(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeCoordinator($sheet);

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'type'                => 'required|in:row,column',
            'row_id'              => 'nullable|integer|exists:progress_rows,id',
            'col_key'             => 'nullable|string|max:255',
            'parent_label'        => 'nullable|string|max:255',
            'linked_schedule_id'  => 'nullable|integer|exists:project_schedules,id',
        ]);

        $maxOrder = $sheet->linkSettings()
            ->where('type', $validated['type'])
            ->max('order') ?? -1;

        $item = ProjectJobItem::create([
            'progress_sheet_id'  => $sheet->id,
            'name'               => $validated['name'],
            'type'               => $validated['type'],
            'row_id'             => $validated['row_id'] ?? null,
            'col_key'            => $validated['col_key'] ?? null,
            'parent_label'       => $validated['parent_label'] ?? null,
            'calendar_linked'    => true,
            'linked_schedule_id' => $validated['linked_schedule_id'] ?? null,
            'order'              => $maxOrder + 1,
        ]);

        return response()->json($this->formatItem($item));
    }

    /**
     * 連携設定を更新する
     */
    public function update(Request $request, ProgressSheet $sheet, ProjectJobItem $item)
    {
        $this->authorizeCoordinator($sheet);
        abort_unless($item->progress_sheet_id === $sheet->id, 404);

        $validated = $request->validate([
            'name'               => 'nullable|string|max:255',
            'type'               => 'nullable|in:row,column',
            'row_id'             => 'nullable|integer|exists:progress_rows,id',
            'col_key'            => 'nullable|string|max:255',
            'parent_label'       => 'nullable|string|max:255',
            'order'              => 'nullable|integer',
            'linked_schedule_id' => 'nullable|integer|exists:project_schedules,id',
        ]);

        $updateData = array_filter($validated, fn($v) => $v !== null);
        if (array_key_exists('linked_schedule_id', $validated)) {
            $updateData['linked_schedule_id'] = $validated['linked_schedule_id'];
        }
        $updateData['calendar_linked'] = true;

        $item->update($updateData);
        $item->refresh();

        return response()->json($this->formatItem($item));
    }

    /**
     * 連携設定を削除する（紐づいたカレンダー予定のリンクは解除のみ）
     */
    public function destroy(ProgressSheet $sheet, ProjectJobItem $item)
    {
        $this->authorizeCoordinator($sheet);
        abort_unless($item->progress_sheet_id === $sheet->id, 404);

        // カレンダー予定の紐づきを解除
        \App\Models\ProjectSchedule::where('project_job_item_id', $item->id)
            ->update(['project_job_item_id' => null]);

        $item->delete();

        return response()->json(['status' => 'ok']);
    }

    /**
     * 進行表から連携設定を自動生成する提案を返す（dry_run）
     */
    public function propose(ProgressSheet $sheet)
    {
        $this->authorizeCoordinator($sheet);

        return response()->json([
            'rows'    => $this->proposeRows($sheet),
            'columns' => $this->proposeColumns($sheet),
        ]);
    }

    /**
     * 提案リストを一括保存する
     */
    public function importFromSheet(Request $request, ProgressSheet $sheet)
    {
        $this->authorizeCoordinator($sheet);

        $validated = $request->validate([
            'items'                         => 'required|array',
            'items.*.name'                  => 'required|string|max:255',
            'items.*.type'                  => 'required|in:row,column',
            'items.*.row_id'                => 'nullable|integer|exists:progress_rows,id',
            'items.*.col_key'               => 'nullable|string|max:255',
            'items.*.parent_label'          => 'nullable|string|max:255',
            'items.*.order'                 => 'nullable|integer',
            'items.*.linked_schedule_id'    => 'nullable|integer|exists:project_schedules,id',
        ]);

        DB::transaction(function () use ($validated, $sheet) {
            // 既存の設定をすべて削除してから再作成
            $sheet->linkSettings()->delete();

            foreach ($validated['items'] as $idx => $data) {
                ProjectJobItem::create([
                    'progress_sheet_id'  => $sheet->id,
                    'name'               => $data['name'],
                    'type'               => $data['type'],
                    'row_id'             => $data['row_id'] ?? null,
                    'col_key'            => $data['col_key'] ?? null,
                    'parent_label'       => $data['parent_label'] ?? null,
                    'calendar_linked'    => true,
                    'linked_schedule_id' => $data['linked_schedule_id'] ?? null,
                    'order'              => $data['order'] ?? $idx,
                ]);
            }
        });

        $items = $sheet->linkSettings()
            ->orderBy('type')->orderBy('order')
            ->get()
            ->map(fn($i) => $this->formatItem($i));

        // 保存後に全スケジュール進捗を再計算
        ProgressLinkService::recalculateSheet($sheet->id);

        return response()->json(['status' => 'ok', 'items' => $items]);
    }

    /**
     * シートの全リンクスケジュール進捗を手動再計算する
     */
    public function recalculate(ProgressSheet $sheet)
    {
        $this->authorizeCoordinator($sheet);
        ProgressLinkService::recalculateSheet($sheet->id);
        return response()->json(['status' => 'ok']);
    }

    /**
     * カレンダー用：案件に紐づく calendar_linked=true の項目を返す
     */
    public function indexForCalendar(ProjectJob $projectJob)
    {
        $this->authorizeCoordinatorByJob($projectJob);

        $items = ProjectJobItem::whereHas('sheet', fn($q) => $q->where('project_job_id', $projectJob->id))
            ->where('calendar_linked', true)
            ->with('sheet:id,name')
            ->orderBy('type')
            ->orderBy('order')
            ->get()
            ->map(fn($i) => [
                'id'           => $i->id,
                'name'         => $i->name,
                'type'         => $i->type,
                'parent_label' => $i->parent_label,
                'sheet_name'   => $i->sheet?->name,
            ]);

        return response()->json(['items' => $items]);
    }

    // ── private ──────────────────────────────────────────────────────────────

    private function proposeRows(ProgressSheet $sheet): array
    {
        $rows = ProgressRow::where('sheet_id', $sheet->id)
            ->orderBy('order')
            ->get(['id', 'label', 'parent_id']);

        // parent_id=null の行をグループラベルとして使う
        $parents = $rows->whereNull('parent_id')->keyBy('id');
        $children = $rows->whereNotNull('parent_id');

        $items = [];
        $order = 0;

        if ($children->isEmpty()) {
            // 親なし = フラット構造
            foreach ($rows as $r) {
                $items[] = [
                    'name'         => $r->label,
                    'type'         => 'row',
                    'row_id'       => $r->id,
                    'parent_label' => null,
                    'calendar_linked' => false,
                    'order'        => $order++,
                ];
            }
        } else {
            foreach ($children as $c) {
                $parentLabel = $parents[$c->parent_id]?->label ?? null;
                $items[] = [
                    'name'         => $c->label,
                    'type'         => 'row',
                    'row_id'       => $c->id,
                    'parent_label' => $parentLabel,
                    'calendar_linked' => false,
                    'order'        => $order++,
                ];
            }
        }

        return $items;
    }

    private function proposeColumns(ProgressSheet $sheet): array
    {
        $config = $sheet->column_config ?? [];
        $items  = [];
        $order  = 0;

        foreach ($config as $top) {
            $topLabel = $top['label'] ?? null;
            $children = $top['children'] ?? [];

            if (empty($children)) {
                // リーフカラム（子なし）
                $items[] = [
                    'name'         => $top['label'] ?? $top['key'],
                    'type'         => 'column',
                    'col_key'      => $top['key'],
                    'parent_label' => null,
                    'calendar_linked' => false,
                    'order'        => $order++,
                ];
            } else {
                foreach ($children as $child) {
                    $grandChildren = $child['children'] ?? [];
                    if (empty($grandChildren)) {
                        $items[] = [
                            'name'         => $child['label'] ?? $child['key'],
                            'type'         => 'column',
                            'col_key'      => $child['key'],
                            'parent_label' => $topLabel,
                            'calendar_linked' => false,
                            'order'        => $order++,
                        ];
                    } else {
                        // 孫カラムまである場合は孫をリーフとして扱う
                        foreach ($grandChildren as $gc) {
                            $items[] = [
                                'name'         => $gc['label'] ?? $gc['key'],
                                'type'         => 'column',
                                'col_key'      => $gc['key'],
                                'parent_label' => ($topLabel ? $topLabel . ' > ' : '') . ($child['label'] ?? ''),
                                'calendar_linked' => false,
                                'order'        => $order++,
                            ];
                        }
                    }
                }
            }
        }

        return $items;
    }

    /**
     * column_config をフラット化してセレクター用に返す（リーフのみ）
     */
    private function flattenColumns(array $config, string $prefix = ''): array
    {
        $result = [];
        foreach ($config as $col) {
            $label = $prefix ? $prefix . ' > ' . ($col['label'] ?? $col['key']) : ($col['label'] ?? $col['key']);
            $children = $col['children'] ?? [];
            if (empty($children)) {
                $result[] = ['key' => $col['key'], 'label' => $label];
            } else {
                $result = array_merge($result, $this->flattenColumns($children, $label));
            }
        }
        return $result;
    }

    /**
     * column_config をフラット化して全階層（親グループ含む）を返す
     */
    private function flattenAllColumns(array $config, string $prefix = ''): array
    {
        $result = [];
        foreach ($config as $col) {
            $label = $prefix ? $prefix . ' > ' . ($col['label'] ?? $col['key']) : ($col['label'] ?? $col['key']);
            $children = $col['children'] ?? [];
            $result[] = ['key' => $col['key'], 'label' => $label, 'isLeaf' => empty($children)];
            if (!empty($children)) {
                $result = array_merge($result, $this->flattenAllColumns($children, $label));
            }
        }
        return $result;
    }

    private function formatItem(ProjectJobItem $item): array
    {
        return [
            'id'                 => $item->id,
            'name'               => $item->name,
            'type'               => $item->type,
            'row_id'             => $item->row_id,
            'col_key'            => $item->col_key,
            'parent_label'       => $item->parent_label,
            'calendar_linked'    => (bool) $item->calendar_linked,
            'linked_schedule_id' => $item->linked_schedule_id,
            'order'              => $item->order,
        ];
    }

    private function authorizeCoordinator(ProgressSheet $sheet): void
    {
        $user = request()->user();
        $pj   = $sheet->projectJob;
        $isOwner = $pj && $pj->user_id === $user->id;
        $isSub   = $pj && $pj->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isOwner || $isSub || $isAdmin, 403);
    }

    private function authorizeCoordinatorByJob(ProjectJob $job): void
    {
        $user    = request()->user();
        $isOwner = $job->user_id === $user->id;
        $isSub   = $job->coordinators()->where('users.id', $user->id)->exists();
        $isAdmin = in_array($user->user_role, ['admin', 'superadmin']);
        abort_unless($isOwner || $isSub || $isAdmin, 403);
    }
}

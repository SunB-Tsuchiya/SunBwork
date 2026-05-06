<?php

namespace App\Services;

use App\Models\ProgressCell;
use App\Models\ProgressRow;
use App\Models\ProgressSheet;
use App\Models\ProjectJobItem;
use App\Models\ProjectSchedule;
use Carbon\Carbon;

class ProgressLinkService
{
    // column_config の type と ProgressCell の cell_type の両方をカバー
    const COMPLETABLE_TYPES = ['worker', 'schedlink', 'proof_v2', 'proof_user'];

    /**
     * セル完了/未完了後に呼び出す。紐づく全スケジュールの進捗を再計算する。
     */
    public static function recalculate(ProgressCell $cell): void
    {
        $sheetId = $cell->row?->sheet_id;
        if (!$sheetId) return;

        self::recalcForRow($cell->row_id, $sheetId);
        self::recalcForCol($cell->col_key, $sheetId);
    }

    /**
     * シート全体の進捗を一括再計算する（既存完了セルにも対応）。
     */
    public static function recalculateSheet(int $sheetId): void
    {
        $sheet = ProgressSheet::find($sheetId);
        if (!$sheet) return;

        $colTree  = $sheet->column_config ?? [];
        $rowCount = ProgressRow::where('sheet_id', $sheetId)->count();

        // 行リンク
        $sheetLeafKeys = self::getCompletableLeafKeysFromTree($colTree);
        $rowItems = ProjectJobItem::where('progress_sheet_id', $sheetId)
            ->where('type', 'row')
            ->whereNotNull('linked_schedule_id')
            ->get();

        foreach ($rowItems as $item) {
            if (!$item->row_id) continue;
            $total = count($sheetLeafKeys);
            if ($total === 0) continue;
            $done = ProgressCell::where('row_id', $item->row_id)
                ->whereIn('col_key', $sheetLeafKeys)
                ->whereNotNull('completed_at')
                ->count();
            self::applyProgressCounts($item->linked_schedule_id, $done, $total);
        }

        // 列リンク
        if ($rowCount > 0) {
            $colItems = ProjectJobItem::where('progress_sheet_id', $sheetId)
                ->where('type', 'column')
                ->whereNotNull('linked_schedule_id')
                ->get();

            foreach ($colItems as $item) {
                $leafKeys = self::getCompletableLeafKeysUnder($item->col_key, $colTree);
                $total    = count($leafKeys) * $rowCount;
                if ($total === 0) continue;
                $done = ProgressCell::whereIn('col_key', $leafKeys)
                    ->whereHas('row', fn($q) => $q->where('sheet_id', $sheetId))
                    ->whereNotNull('completed_at')
                    ->count();
                self::applyProgressCounts($item->linked_schedule_id, $done, $total);
            }
        }
    }

    // ── 行リンク ──────────────────────────────────────────────────────────────

    private static function recalcForRow(int $rowId, int $sheetId): void
    {
        $item = ProjectJobItem::where('progress_sheet_id', $sheetId)
            ->where('type', 'row')
            ->where('row_id', $rowId)
            ->whereNotNull('linked_schedule_id')
            ->first();

        if (!$item) return;

        $sheet     = ProgressSheet::find($sheetId);
        $colTree   = $sheet->column_config ?? [];
        $leafKeys  = self::getCompletableLeafKeysFromTree($colTree);
        $total     = count($leafKeys);
        if ($total === 0) return;

        $done = ProgressCell::where('row_id', $rowId)
            ->whereIn('col_key', $leafKeys)
            ->whereNotNull('completed_at')
            ->count();

        self::applyProgressCounts($item->linked_schedule_id, $done, $total);
    }

    // ── 列リンク ──────────────────────────────────────────────────────────────

    private static function recalcForCol(string $colKey, int $sheetId): void
    {
        $sheet = ProgressSheet::find($sheetId);
        if (!$sheet) return;

        $colTree = $sheet->column_config ?? [];

        // 完了したcolKeyを子に持つ祖先キー + colKey自身
        $matchingKeys   = self::findAncestorKeys($colKey, $colTree);
        $matchingKeys[] = $colKey;

        $items = ProjectJobItem::where('progress_sheet_id', $sheetId)
            ->where('type', 'column')
            ->whereIn('col_key', $matchingKeys)
            ->whereNotNull('linked_schedule_id')
            ->get();

        $rowCount = ProgressRow::where('sheet_id', $sheetId)->count();
        if ($rowCount === 0) return;

        foreach ($items as $item) {
            $leafKeys = self::getCompletableLeafKeysUnder($item->col_key, $colTree);
            $total    = count($leafKeys) * $rowCount;
            if ($total === 0) continue;

            $done = ProgressCell::whereIn('col_key', $leafKeys)
                ->whereHas('row', fn($q) => $q->where('sheet_id', $sheetId))
                ->whereNotNull('completed_at')
                ->count();

            self::applyProgressCounts($item->linked_schedule_id, $done, $total);
        }
    }

    // ── スケジュール更新 ──────────────────────────────────────────────────────

    private static function applyProgressCounts(int $scheduleId, int $done, int $total): void
    {
        $progress    = (int) round($done / $total * 100);
        $completedAt = $progress >= 100 ? Carbon::now() : null;

        ProjectSchedule::where('id', $scheduleId)->update([
            'progress'     => $progress,
            'completed_at' => $completedAt,
        ]);
    }

    // ── 完了対象リーフキーヘルパー ────────────────────────────────────────────

    /**
     * colTree 全体から完了対象リーフキーをすべて返す
     */
    private static function getCompletableLeafKeysFromTree(array $colTree): array
    {
        $keys = [];
        foreach ($colTree as $node) {
            $children = $node['children'] ?? [];
            if (empty($children)) {
                $type = $node['type'] ?? '';
                // type 未設定も完了対象とみなす（後方互換）
                if ($type === '' || in_array($type, self::COMPLETABLE_TYPES, true)) {
                    $keys[] = $node['key'];
                }
            } else {
                $keys = array_merge($keys, self::getCompletableLeafKeysFromTree($children));
            }
        }
        return $keys;
    }

    /**
     * 指定キー配下の完了対象リーフキーをすべて返す（自身がリーフなら自分だけ）
     */
    public static function getCompletableLeafKeysUnder(string $colKey, array $colTree): array
    {
        $node = self::findNodeByKey($colKey, $colTree);
        if (!$node) return [];

        $children = $node['children'] ?? [];
        if (empty($children)) {
            $type = $node['type'] ?? '';
            if ($type === '' || in_array($type, self::COMPLETABLE_TYPES, true)) {
                return [$colKey];
            }
            return [];
        }

        return self::getCompletableLeafKeysFromTree($children);
    }

    // ── 列ツリーヘルパー ──────────────────────────────────────────────────────

    /**
     * targetKeyの祖先キー（親・祖父...）をすべて返す
     */
    private static function findAncestorKeys(string $targetKey, array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            if (!empty($children) && self::isKeyInSubtree($targetKey, $children)) {
                $result[] = $node['key'];
                $result   = array_merge($result, self::findAncestorKeys($targetKey, $children));
            }
        }
        return $result;
    }

    private static function isKeyInSubtree(string $targetKey, array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($node['key'] === $targetKey) return true;
            if (!empty($node['children']) && self::isKeyInSubtree($targetKey, $node['children'])) return true;
        }
        return false;
    }

    /**
     * 指定キー配下のリーフキーをすべて返す（型問わず・UI用）
     */
    public static function getLeafKeysUnder(string $colKey, array $colTree): array
    {
        $node = self::findNodeByKey($colKey, $colTree);
        if (!$node || empty($node['children'])) return [$colKey];
        return self::collectLeafKeys($node['children']);
    }

    private static function findNodeByKey(string $key, array $nodes): ?array
    {
        foreach ($nodes as $node) {
            if ($node['key'] === $key) return $node;
            if (!empty($node['children'])) {
                $found = self::findNodeByKey($key, $node['children']);
                if ($found) return $found;
            }
        }
        return null;
    }

    private static function collectLeafKeys(array $nodes): array
    {
        $keys = [];
        foreach ($nodes as $node) {
            if (empty($node['children'])) {
                $keys[] = $node['key'];
            } else {
                $keys = array_merge($keys, self::collectLeafKeys($node['children']));
            }
        }
        return $keys;
    }
}

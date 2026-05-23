# 進行表×カレンダー連携 詳細設計書

**作成日:** 2026-05-06
**対象ロール:** Coordinator
**ステータス:** 設計完了・実装待ち

---

## 概要

進行表（ProgressSheet）の行・列と、カレンダーのスケジュール（ProjectSchedule）を直接紐づけ、
セルが完了するたびにスケジュールの進捗率（`progress`）を自動再計算・反映する。
進捗が 100% になったらスケジュールを自動完了（`completed_at` 設定）し、
カレンダー上でグレー表示・完了バッジが表示されるようにする。

---

## コード調査結果サマリー

| 調査項目 | 確認内容 |
|---------|---------|
| `progress_cells` 列識別 | `col_key` (string) + `row_id` (FK) で位置を特定 |
| 完了可能 cell_type | `worker` / `schedlink` / `proof_v2` |
| `project_schedules` の progress カラム | 存在する（integer, default 0）|
| `project_schedules.completed_at` | 存在する。カレンダーのグレー表示に使用済み |
| `project_schedules.project_job_item_id` | 存在する（現在はカレンダー側で設定）|
| column_config 構造 | `[{key, label, children:[{key, label, children:[...]}]}]` のネストJSON |
| `collectLeaves()` | ProgressSheetController に実装済み（再利用可） |
| complete フック箇所 | `Coordinator/ProgressCellController::complete()` + `User/ProgressCellController::complete()` |
| uncomplete フック箇所 | `ProjectSchedulesController::uncomplete()` + `ProgressSheetController::uncompleteAssignment()` |
| 既存ルート | `progress-cells/{cell}/complete` (POST), `project_schedules/{id}/uncomplete` (PATCH) |

---

## 1. DB 設計

### 1-1. 追加マイグレーション

**ファイル名:** `2026_05_06_000001_add_linked_schedule_id_to_project_job_items.php`

```php
Schema::table('project_job_items', function (Blueprint $table) {
    $table->unsignedBigInteger('linked_schedule_id')->nullable()->after('calendar_linked');
    $table->foreign('linked_schedule_id')
          ->references('id')->on('project_schedules')
          ->onDelete('set null');
});
```

**変更理由:**
- 現状は「スケジュール → アイテム」方向（`project_schedules.project_job_item_id`）でしか設定できない
- 連携設定UIから「アイテム → スケジュール」方向で紐づけるために `linked_schedule_id` を追加
- `project_schedules.project_job_item_id` は既存の schedlink セル完了連携で引き続き使用するため削除しない

### 1-2. 既存テーブルへの変更

なし（`project_schedules.progress`, `completed_at` は既存）

---

## 2. 新規サービスクラス

### `app/Services/ProgressLinkService.php`（新規作成）

```php
<?php
namespace App\Services;

use App\Models\ProgressCell;
use App\Models\ProgressSheet;
use App\Models\ProjectJobItem;
use App\Models\ProjectSchedule;
use Carbon\Carbon;

class ProgressLinkService
{
    const COMPLETABLE_TYPES = ['worker', 'schedlink', 'proof_v2'];

    /**
     * セル完了/未完了後に呼び出す。関連スケジュールの進捗を再計算する。
     */
    public static function recalculate(ProgressCell $cell): void
    {
        $sheetId = $cell->row->sheet_id ?? null;
        if (!$sheetId) return;

        // 行リンクの再計算
        self::recalcForRow($cell->row_id, $sheetId);

        // 列リンクの再計算（リーフ一致 + 親グループ一致）
        self::recalcForCol($cell->col_key, $sheetId);
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

        // 対象行の全完了可能セル
        $cells = ProgressCell::where('row_id', $rowId)
            ->whereIn('cell_type', self::COMPLETABLE_TYPES)
            ->get(['completed_at']);

        self::updateScheduleProgress($item->linked_schedule_id, $cells);
    }

    // ── 列リンク ──────────────────────────────────────────────────────────────

    private static function recalcForCol(string $colKey, int $sheetId): void
    {
        $sheet = ProgressSheet::find($sheetId);
        if (!$sheet) return;

        $colTree = $sheet->column_config ?? [];

        // このcolKeyがリーフとして一致するアイテム、または親グループとして含むアイテム
        $matchingKeys = self::findAncestorKeys($colKey, $colTree);
        $matchingKeys[] = $colKey; // リーフ完全一致も含む

        $items = ProjectJobItem::where('progress_sheet_id', $sheetId)
            ->where('type', 'column')
            ->whereIn('col_key', $matchingKeys)
            ->whereNotNull('linked_schedule_id')
            ->get();

        foreach ($items as $item) {
            $leafKeys = self::getLeafKeysUnder($item->col_key, $colTree);

            // 全行 × 対象列のセル
            $cells = ProgressCell::whereIn('col_key', $leafKeys)
                ->whereHas('row', fn($q) => $q->where('sheet_id', $sheetId))
                ->whereIn('cell_type', self::COMPLETABLE_TYPES)
                ->get(['completed_at']);

            self::updateScheduleProgress($item->linked_schedule_id, $cells);
        }
    }

    // ── スケジュール更新 ──────────────────────────────────────────────────────

    private static function updateScheduleProgress(int $scheduleId, $cells): void
    {
        $total = $cells->count();
        if ($total === 0) return;

        $done = $cells->whereNotNull('completed_at')->count();
        $progress = (int) round($done / $total * 100);
        $completedAt = $progress >= 100 ? Carbon::now() : null;

        ProjectSchedule::where('id', $scheduleId)->update([
            'progress'     => $progress,
            'completed_at' => $completedAt,
        ]);
    }

    // ── 列ツリー操作ヘルパー ──────────────────────────────────────────────────

    /**
     * targetKeyの先祖キー（親・祖父母...）をすべて返す
     */
    private static function findAncestorKeys(string $targetKey, array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $children = $node['children'] ?? [];
            if (!empty($children)) {
                if (self::isKeyInSubtree($targetKey, $children)) {
                    $result[] = $node['key'];
                    $result = array_merge($result, self::findAncestorKeys($targetKey, $children));
                }
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
     * 指定キー配下のリーフキーをすべて返す（キー自体がリーフなら自分を返す）
     */
    public static function getLeafKeysUnder(string $colKey, array $colTree): array
    {
        $node = self::findNodeByKey($colKey, $colTree);
        if (!$node) return [$colKey];
        if (empty($node['children'])) return [$colKey];
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
```

---

## 3. コントローラー変更

### 3-1. `Coordinator/ProgressCellController::complete()` に追記

```php
use App\Services\ProgressLinkService;

public function complete(Request $request, ProgressCell $cell)
{
    // ... 既存コード（completed_at保存・schedlink完了）...

    // ── 追加 ──────────────────────────────────────────
    ProgressLinkService::recalculate($cell);
    // ──────────────────────────────────────────────────

    return response()->json([...]);
}
```

### 3-2. `User/ProgressCellController::complete()` に同様に追記

```php
use App\Services\ProgressLinkService;

public function complete(Request $request, ProgressCell $cell)
{
    // ... 既存コード ...
    ProgressLinkService::recalculate($cell);
    return response()->json([...]);
}
```

### 3-3. `ProjectSchedulesController::uncomplete()` に追記

スケジュールを未完了に戻したとき、進捗率も 0 にリセットする。

```php
public function uncomplete(Request $request, ProjectSchedule $projectSchedule)
{
    $this->authorize('update', $projectSchedule);
    $projectSchedule->update(['completed_at' => null, 'progress' => 0]);

    \App\Models\ProgressCell::where('schedule_id', $projectSchedule->id)
        ->where('cell_type', 'schedlink')
        ->update(['completed_at' => null]);

    return response()->json(['status' => 'ok']);
}
```

### 3-4. `ProgressSheetItemController` の変更

#### `index()` — スケジュール一覧を props に追加

```php
public function index(ProgressSheet $sheet)
{
    // 既存コード...

    // 案件に紐づくスケジュール一覧を追加（セレクター用）
    $schedules = \App\Models\ProjectSchedule::where('project_job_id', $sheet->project_job_id)
        ->orderBy('start_date')
        ->get(['id', 'name', 'start_date', 'end_date']);

    return response()->json([
        'items'        => $items,
        'rows'         => $rows,
        'columns'      => $columns,
        'columnConfig' => $sheet->column_config ?? [],
        'schedules'    => $schedules,   // ← 追加
    ]);
}
```

#### `importFromSheet()` — `linked_schedule_id` を受け付けるよう追加

バリデーションに `'items.*.linked_schedule_id' => 'nullable|integer|exists:project_schedules,id'` を追加。
`ProjectJobItem::create()` に `'linked_schedule_id' => $data['linked_schedule_id'] ?? null` を追加。

#### `formatItem()` — `linked_schedule_id` を返すよう追加

```php
private function formatItem(ProjectJobItem $item): array
{
    return [
        // 既存フィールド...
        'linked_schedule_id' => $item->linked_schedule_id,
    ];
}
```

---

## 4. マイグレーション実行

```bash
docker compose exec laravel bash -lc "php artisan migrate"
```

さくら本番デプロイ時も必須。

---

## 5. フロントエンド変更

### 5-1. `ProjectJobItemsTab.vue` — UIの刷新

**主な変更点:**
- `sheetStates` に `schedules: []` を追加（スケジュール一覧）
- 閲覧モード: `calendar_linked` バッジ → `linked_schedule_id` のスケジュール名表示に変更
- 編集モード: `calendar_linked` チェックボックス → スケジュールセレクタ `<select>` に変更
- 作成モード（提案）: 同上

**編集モードの各アイテムUI:**
```vue
<!-- 各行/列アイテムのスケジュール選択 -->
<div class="flex-1">
    <label class="mb-0.5 block text-xs text-gray-500">紐づけるスケジュール</label>
    <select v-model="item.linked_schedule_id"
            class="w-full rounded border border-gray-300 px-2 py-1 text-sm">
        <option :value="null">— 選択しない —</option>
        <option v-for="s in sheetStates[sheet.id].schedules"
                :key="s.id" :value="s.id">
            {{ s.name }}{{ s.start_date ? '（' + s.start_date + '）' : '' }}
        </option>
    </select>
</div>
```

**閲覧モードのバッジ:**
```vue
<span v-if="item.linked_schedule_id" class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-700">
    📅 {{ sheetStates[sheet.id].schedules.find(s => s.id === item.linked_schedule_id)?.name ?? 'スケジュール連携' }}
</span>
```

### 5-2. `ProjectCalendar.vue` — 進捗表示の確認

`progress` フィールドの表示は uniformColors モードで実装済み（`完了` or `XX%` バッジ）。
DB の `progress` カラムが自動更新されるようになれば、追加実装不要。

ただし、スケジュール読み込み時に `progress` を props として渡しているか確認し、
不足があれば `ProjectSchedulesController::index()` または `show.vue` の props 設定を修正する。

---

## 6. 実装順序

| Phase | 内容 | ファイル |
|-------|------|---------|
| P-01 | DBマイグレーション | `2026_05_06_000001_add_linked_schedule_id_to_project_job_items.php` |
| P-02 | ProgressLinkService 作成 | `app/Services/ProgressLinkService.php` |
| P-03 | ProgressCellController（Coordinator）に hook 追加 | `Coordinator/ProgressCellController.php` |
| P-04 | ProgressCellController（User）に hook 追加 | `User/ProgressCellController.php` |
| P-05 | ProjectSchedulesController::uncomplete() 修正 | `ProjectSchedulesController.php` |
| P-06 | ProgressSheetItemController 修正（index/import/format） | `ProgressSheetItemController.php` |
| P-07 | ProjectJobItemsTab.vue UIリニューアル | `ProjectJobItemsTab.vue` |
| P-08 | ビルド・動作確認 | `npm run build` |
| P-09 | カレンダーの progress 表示確認・不足なら修正 | `ProjectCalendar.vue` |

---

## 7. 動作確認チェックリスト

- [ ] 連携設定：行「学校1」にスケジュール「学校1問題」を紐づけて保存できる
- [ ] 連携設定：列「初校（まとめ）」にスケジュール「初校作業」を紐づけて保存できる
- [ ] 連携設定：列「初校・組版」（リーフ）に個別スケジュールを紐づけて保存できる
- [ ] 「学校1」行のセルを完了 → スケジュール「学校1問題」の progress が更新される
- [ ] 全セル完了 → progress = 100 → schedule.completed_at が設定される
- [ ] カレンダーで「学校1問題」がグレー表示になる
- [ ] 「初校」列内のセルを完了 → 「初校作業」progress が更新される（全行 × 初校列 で集計）
- [ ] 「初校・組版」リーフを選択した場合、「初校・組版」のセルのみで進捗を計算する
- [ ] スケジュール「未完了に戻す」→ progress = 0 にリセットされる

---

## 8. さくら本番注意事項

1. `php artisan migrate` を必ず本番で実行すること（`project_job_items.linked_schedule_id` カラム追加）
2. ナビゲーションは `route()` を使うこと
3. CSRF は `meta[name="csrf-token"]` から取得すること

<?php

namespace App\Support;

/** チームルーム・案件のカレンダーの色（Clerk のカレンダーと同じ 11 色）と、色設定の共通処理 */
class CalendarColors
{
    public const KEYS = [
        'indigo', 'blue', 'cyan', 'teal', 'green',
        'yellow', 'orange', 'red', 'pink', 'purple', 'gray',
    ];

    public const DEFAULT_KEY = 'indigo';

    /** 色設定の一覧（無ければ 11 色を作る）。$modelClass は色設定のモデル、$ownerKey/$ownerId は「どのチーム・案件か」 */
    public static function listFor(string $modelClass, string $ownerKey, int $ownerId): array
    {
        foreach (self::KEYS as $i => $key) {
            $modelClass::firstOrCreate([$ownerKey => $ownerId, 'color_key' => $key], ['sort_order' => $i]);
        }
        $rows = $modelClass::where($ownerKey, $ownerId)->with('updater:id,name')->orderBy('sort_order')->get();

        return [
            'colors'       => $rows->map(fn ($r) => ['color_key' => $r->color_key, 'label' => $r->label, 'sort_order' => $r->sort_order])->values()->all(),
            'last_changed' => self::lastChanged($rows),
        ];
    }

    /** 最後に変更した人と日時（誰も変更していなければ null） */
    public static function lastChanged($rows): ?array
    {
        $row = $rows->filter(fn ($r) => $r->updated_by)->sortByDesc('updated_at')->first();

        return $row ? [
            'name' => $row->updater?->name ?? '（退職・削除）',
            'at'   => $row->updated_at->timezone('Asia/Tokyo')->format('Y/m/d H:i'),
        ] : null;
    }

    /** ラベルを更新する */
    public static function updateLabel(string $modelClass, string $ownerKey, int $ownerId, string $colorKey, ?string $label, int $userId): array
    {
        abort_unless(in_array($colorKey, self::KEYS, true), 404);
        $modelClass::where($ownerKey, $ownerId)->where('color_key', $colorKey)->update(['label' => $label ?: null, 'updated_by' => $userId, 'updated_at' => now()]);

        return ['ok' => true, 'last_changed' => self::lastChanged($modelClass::where($ownerKey, $ownerId)->with('updater:id,name')->get())];
    }

    /** 並び順を更新する */
    public static function reorder(string $modelClass, string $ownerKey, int $ownerId, array $orders, int $userId): array
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($modelClass, $ownerKey, $ownerId, $orders, $userId) {
            foreach ($orders as $item) {
                if (!in_array($item['color_key'], self::KEYS, true)) {
                    continue;
                }
                $modelClass::where($ownerKey, $ownerId)->where('color_key', $item['color_key'])
                    ->update(['sort_order' => (int) $item['sort_order'], 'updated_by' => $userId, 'updated_at' => now()]);
            }
        });

        return ['ok' => true, 'last_changed' => self::lastChanged($modelClass::where($ownerKey, $ownerId)->with('updater:id,name')->get())];
    }
}

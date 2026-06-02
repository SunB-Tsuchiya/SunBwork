<?php

namespace App\Services;

use App\Models\WorkItemType;

class GeneralWorkItemDefaultsService
{
    /**
     * 指定会社の会社全体スコープ（department_id IS NULL）に
     * 一般的な作業項目のデフォルトセットを登録する。
     *
     * 既にその会社の work_item_types が1件でも存在する場合はスキップ（冪等）。
     */
    public function seedForCompany(int $companyId): void
    {
        // 会社にアイテムが1件でも存在すればスキップ（スコープ問わず）
        $exists = WorkItemType::where('company_id', $companyId)->exists();

        if ($exists) {
            return;
        }

        foreach ($this->defaultItems() as $item) {
            WorkItemType::create([
                'name'          => $item['name'],
                'slug'          => $companyId . '-' . $item['slug_key'],
                'group'         => $item['group'],
                'company_id'    => $companyId,
                'department_id' => null,
                'sort_order'    => $item['sort_order'],
                'coefficient'   => 1.0,
            ]);
        }
    }

    /**
     * 一般会社向けデフォルト作業項目定義
     * 4グループ × 5項目 = 20件
     */
    private function defaultItems(): array
    {
        return [
            // ── 総務 ─────────────────────────────────────────
            ['group' => '総務',      'name' => '書類作成・管理',   'slug_key' => 'somu-1',  'sort_order' => 1],
            ['group' => '総務',      'name' => '備品・消耗品管理', 'slug_key' => 'somu-2',  'sort_order' => 2],
            ['group' => '総務',      'name' => '来客・電話対応',   'slug_key' => 'somu-3',  'sort_order' => 3],
            ['group' => '総務',      'name' => '社内連絡・調整',   'slug_key' => 'somu-4',  'sort_order' => 4],
            ['group' => '総務',      'name' => '郵便・宅配処理',   'slug_key' => 'somu-5',  'sort_order' => 5],

            // ── 経理・財務 ───────────────────────────────────
            ['group' => '経理・財務', 'name' => '請求書発行・処理', 'slug_key' => 'keiri-1', 'sort_order' => 6],
            ['group' => '経理・財務', 'name' => '支払・振込処理',   'slug_key' => 'keiri-2', 'sort_order' => 7],
            ['group' => '経理・財務', 'name' => '経費精算処理',     'slug_key' => 'keiri-3', 'sort_order' => 8],
            ['group' => '経理・財務', 'name' => '帳簿・仕訳入力',   'slug_key' => 'keiri-4', 'sort_order' => 9],
            ['group' => '経理・財務', 'name' => '月次・決算処理',   'slug_key' => 'keiri-5', 'sort_order' => 10],

            // ── 営業 ─────────────────────────────────────────
            ['group' => '営業',      'name' => '顧客対応・折衝',   'slug_key' => 'eigyo-1', 'sort_order' => 11],
            ['group' => '営業',      'name' => '見積・提案書作成', 'slug_key' => 'eigyo-2', 'sort_order' => 12],
            ['group' => '営業',      'name' => '受注・発注処理',   'slug_key' => 'eigyo-3', 'sort_order' => 13],
            ['group' => '営業',      'name' => '営業資料作成',     'slug_key' => 'eigyo-4', 'sort_order' => 14],
            ['group' => '営業',      'name' => '顧客情報管理',     'slug_key' => 'eigyo-5', 'sort_order' => 15],

            // ── 管理・共通 ───────────────────────────────────
            ['group' => '管理・共通', 'name' => '会議・打ち合わせ',    'slug_key' => 'kanri-1', 'sort_order' => 16],
            ['group' => '管理・共通', 'name' => '報告書・議事録作成',  'slug_key' => 'kanri-2', 'sort_order' => 17],
            ['group' => '管理・共通', 'name' => '進捗管理・確認',      'slug_key' => 'kanri-3', 'sort_order' => 18],
            ['group' => '管理・共通', 'name' => 'メール・連絡対応',    'slug_key' => 'kanri-4', 'sort_order' => 19],
            ['group' => '管理・共通', 'name' => '社内研修・教育',      'slug_key' => 'kanri-5', 'sort_order' => 20],
        ];
    }
}

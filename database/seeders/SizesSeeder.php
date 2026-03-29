<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 組版会社向けサイズマスター
 *
 * グループ構成:
 *   paper   … 紙媒体（JIS 規格 A/B 系・四六判・タブロイド等）
 *   digital … デジタル・Web（画面サイズ・ファイル単位）
 *
 * coefficient は組版難易度に応じた係数。
 * 小サイズ（A5, B6）は文字組が細かくなりがちなので係数高め。
 * 大サイズ（A2 以上）は面積が広いためやや高め。
 */
class SizesSeeder extends Seeder
{
    public function run(): void
    {
        $companyId    = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        $sizes = [
            // ── 紙媒体 ────────────────────────────────────────────────────
            [
                'name'        => 'A3超',
                'label'       => 'A3超（特大）',
                'group'       => 'paper',
                'width'       => 450,
                'height'      => 320,
                'unit'        => 'mm',
                'coefficient' => 1.2,
                'sort_order'  => 0,
            ],
            [
                'name'        => 'A2',
                'label'       => 'A2（420×594mm）',
                'group'       => 'paper',
                'width'       => 420,
                'height'      => 594,
                'unit'        => 'mm',
                'coefficient' => 1.2,
                'sort_order'  => 1,
            ],
            [
                'name'        => 'A3',
                'label'       => 'A3（297×420mm）',
                'group'       => 'paper',
                'width'       => 297,
                'height'      => 420,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 2,
            ],
            [
                'name'        => 'A4',
                'label'       => 'A4（210×297mm）',
                'group'       => 'paper',
                'width'       => 210,
                'height'      => 297,
                'unit'        => 'mm',
                'coefficient' => 1.0,
                'sort_order'  => 3,
            ],
            [
                'name'        => 'A5',
                'label'       => 'A5（148×210mm）',
                'group'       => 'paper',
                'width'       => 148,
                'height'      => 210,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 4,
            ],
            [
                'name'        => 'B4',
                'label'       => 'B4（257×364mm）',
                'group'       => 'paper',
                'width'       => 257,
                'height'      => 364,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 5,
            ],
            [
                'name'        => 'B5',
                'label'       => 'B5（182×257mm）',
                'group'       => 'paper',
                'width'       => 182,
                'height'      => 257,
                'unit'        => 'mm',
                'coefficient' => 1.0,
                'sort_order'  => 6,
            ],
            [
                'name'        => 'B6',
                'label'       => 'B6（128×182mm）',
                'group'       => 'paper',
                'width'       => 128,
                'height'      => 182,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 7,
            ],
            [
                'name'        => '四六判',
                'label'       => '四六判（127×188mm）',
                'group'       => 'paper',
                'width'       => 127,
                'height'      => 188,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 8,
            ],
            [
                'name'        => 'タブロイド',
                'label'       => 'タブロイド（273×406mm）',
                'group'       => 'paper',
                'width'       => 273,
                'height'      => 406,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 9,
            ],
            [
                'name'        => '変形（小）',
                'label'       => '変形・小（A5 以下相当）',
                'group'       => 'paper',
                'width'       => null,
                'height'      => null,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 10,
            ],
            [
                'name'        => '変形（大）',
                'label'       => '変形・大（A3〜A4 相当）',
                'group'       => 'paper',
                'width'       => null,
                'height'      => null,
                'unit'        => 'mm',
                'coefficient' => 1.1,
                'sort_order'  => 11,
            ],

            // ── デジタル・Web ─────────────────────────────────────────────
            [
                'name'        => 'Web（PC）',
                'label'       => 'Web PC（1920×1080px 相当）',
                'group'       => 'digital',
                'width'       => 1920,
                'height'      => 1080,
                'unit'        => 'px',
                'coefficient' => 1.0,
                'sort_order'  => 20,
            ],
            [
                'name'        => 'Web（タブレット）',
                'label'       => 'Web タブレット（1024×768px 相当）',
                'group'       => 'digital',
                'width'       => 1024,
                'height'      => 768,
                'unit'        => 'px',
                'coefficient' => 1.0,
                'sort_order'  => 21,
            ],
            [
                'name'        => 'Web（モバイル）',
                'label'       => 'Web モバイル（390×844px 相当）',
                'group'       => 'digital',
                'width'       => 390,
                'height'      => 844,
                'unit'        => 'px',
                'coefficient' => 1.0,
                'sort_order'  => 22,
            ],
            [
                'name'        => 'ファイル単位',
                'label'       => 'ファイル単位（サイズ問わず）',
                'group'       => 'digital',
                'width'       => null,
                'height'      => null,
                'unit'        => 'file',
                'coefficient' => 1.0,
                'sort_order'  => 23,
            ],
        ];

        foreach ($sizes as $s) {
            $s['company_id']    = $companyId;
            $s['department_id'] = $departmentId;
            DB::table('sizes')->updateOrInsert(['name' => $s['name']], $s);
        }

        // 旧デジタルサイズ（名称変更後は使われないが残存レコードのグループだけ更新）
        $legacyDigital = ['Full HD', 'HD', 'iPhone 14', 'iPad'];
        DB::table('sizes')
            ->whereIn('name', $legacyDigital)
            ->update(['group' => 'digital']);
    }
}

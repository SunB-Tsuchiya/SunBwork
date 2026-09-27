<?php

namespace App\Services\MGinbon;

use App\Models\MGinbon\MGinbonProject;
use App\Models\MGinbon\MGinbonValueList;
use Illuminate\Support\Facades\DB;

class MGinbonValueListDefaults
{
    public const DEFINITIONS = [
        ['checker', 'チェック担当者', ['鈴木', '横田', '土屋']],
        ['drawing_text_operator', '作業担当　作図・文字入力', ['C&C', '社内', '大連若葉', '流用', 'データ有', '後送？']],
        ['composition_operator', '作業担当　組版', ['伊藤', '川村', '折原', '福山', '花上', '村田', '野村', '岩元', '大原', '暁和', '明昌堂', '大連若葉', 'C&C', '赤ナシ', '校了', '実了/']],
        ['original_scan_operator', '原本scan担当', ['社内', '暁和', '西武プロセス']],
        ['original_image_replacement', '原本画像サシカエ', ['未', '済']],
        ['media_category', '媒体分類', ['問題', '解説解答', '解答のみ', '傾向と対策', '解答用紙']],
        ['school_category', '学校分類', ['共学校', '男子校', '女子校', '地方校']],
        ['payment_month', '支払月', ['2月', '3月', '4月', '5月', '6月', '7月']],
        ['proofreader', '文字校正担当', ['鈴木健', '木村', '村瀬', '藤田', '星野', '熊谷', '高沢', '中川', '土屋', '横田', '鈴木和', '河田', '福田']],
        ['nichinoken_category', '日能研分類', ['α版 関西４校', 'α版-1', 'α版-2', 'α版-3', 'α版-4', '通常校', 'α版']],
        ['subject', '科目', ['国語・算数・社会・理科', '国語・算数', '国語・社会', '国語・算数・理科', '算数・理科', '算数']],
        ['answer_availability', '解答あり・なし', ['解答あり', '解答なし']],
        ['ginbon_publication', '銀本掲載', ['○', 'データのみ', '算数のみ']],
    ];

    public function ensureForProject(MGinbonProject $project, ?int $userId = null): void
    {
        DB::connection('mginbon')->transaction(function () use ($project, $userId) {
            foreach (self::DEFINITIONS as $listOrder => [$code, $name, $values]) {
                $list = MGinbonValueList::firstOrCreate(
                    ['mginbon_project_id' => $project->id, 'code' => $code],
                    ['name' => $name, 'sort_order' => ($listOrder + 1) * 10, 'is_active' => true]
                );

                if ($list->wasRecentlyCreated) {
                    foreach ($values as $itemOrder => $value) {
                        $list->items()->firstOrCreate(
                            ['value' => $value],
                            ['sort_order' => ($itemOrder + 1) * 10, 'is_active' => true, 'created_by' => $userId, 'updated_by' => $userId]
                        );
                    }
                }
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('scripts')->insertOrIgnore([
            'name'          => '宛先ラベル生成 V2',
            'slug'          => 'label-generator-v2',
            'description'   => '宛先ラベルPDF生成ツール（シンプル版）。試験項目・学年・一式を設定してExcelから生成します。',
            'component_key' => 'LabelGeneratorV2',
            'sort_order'    => 3,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('scripts')->where('slug', 'label-generator-v2')->delete();
    }
};

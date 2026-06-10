<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('scripts')->insertOrIgnore([
            'name'          => '宛先ラベル生成ツール',
            'slug'          => 'label-generator',
            'description'   => '日能研テストの発送部数 Excel（s1_*.xls）とアイテムPDF から、教室別・アイテム別・学年別の宛先ラベルPDFを自動生成します。',
            'component_key' => 'LabelGenerator',
            'sort_order'    => 2,
            'is_active'     => true,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('scripts')->where('slug', 'label-generator')->delete();
    }
};

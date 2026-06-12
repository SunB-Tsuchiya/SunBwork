<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // sort_order が未設定（0）のレコードを id 順で連番初期化
        foreach (['label_test_names', 'label_item_types'] as $table) {
            $rows = DB::table($table)->orderBy('id')->pluck('id');
            foreach ($rows as $i => $id) {
                DB::table($table)->where('id', $id)->update(['sort_order' => $i + 1]);
            }
        }
    }

    public function down(): void
    {
        foreach (['label_test_names', 'label_item_types'] as $table) {
            DB::table($table)->update(['sort_order' => 0]);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_item_types')->updateOrInsert(
            ['slug' => 'work_schedule'],
            [
                'name' => '作業（予定）',
                'coefficient' => 1.000,
                'description' => '案件や細かなジョブに紐づけずに記録する業務予定',
                'sort_order' => 7,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $id = DB::table('event_item_types')->where('slug', 'work_schedule')->value('id');
        if ($id && ! DB::table('events')->where('event_item_type_id', $id)->exists()) {
            DB::table('event_item_types')->where('id', $id)->delete();
        }
    }
};

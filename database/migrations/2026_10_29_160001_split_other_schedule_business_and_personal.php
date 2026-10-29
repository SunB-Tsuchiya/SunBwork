<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('event_item_types')->where('slug', 'other')->first();
        $business = [
            'name' => 'そのほか（業務）',
            'description' => '掃除や応援作業など、他の種類に当てはまらない業務。新しい試算は時間点の0.9倍。',
            'updated_at' => now(),
        ];
        if ($existing) {
            // Preserve the ID and legacy coefficient: existing events remain linked and V2 stays unchanged.
            DB::table('event_item_types')->where('id', $existing->id)->update($business);
        } else {
            DB::table('event_item_types')->insert($business + [
                'slug' => 'other', 'coefficient' => 1.0, 'sort_order' => 6, 'created_at' => now(),
            ]);
        }
        DB::table('event_item_types')->updateOrInsert(['slug' => 'personal_other'], [
            'name' => 'そのほか（私用）', 'coefficient' => 0.0,
            'description' => '私用の予定。作業時間・評価点には含めません。',
            'sort_order' => 7, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('event_item_types')->where('slug', 'other')->update(['name' => 'そのほか', 'updated_at' => now()]);
        $personalId = DB::table('event_item_types')->where('slug', 'personal_other')->value('id');
        if ($personalId && ! DB::table('events')->where('event_item_type_id', $personalId)->exists()) {
            DB::table('event_item_types')->where('id', $personalId)->delete();
        }
    }
};

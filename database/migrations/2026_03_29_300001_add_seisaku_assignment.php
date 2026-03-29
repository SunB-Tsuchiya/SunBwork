<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite（CI）では assignments テーブルが存在しない場合があるのでスキップ
        if (! \Illuminate\Support\Facades\Schema::hasTable('assignments')) {
            return;
        }

        $company = DB::table('companies')->where('code', 'SUNBRAIN')->first();
        if (! $company) {
            return;
        }

        $deptCodes = ['INFO', 'SEIHAN', 'ONDEMAND'];

        foreach ($deptCodes as $code) {
            $dept = DB::table('departments')
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->first();

            if (! $dept) {
                continue;
            }

            // 「そのほか」の sort_order を +1 にずらす（重複しないよう先に更新）
            DB::table('assignments')
                ->where('department_id', $dept->id)
                ->where('code', 'other')
                ->increment('sort_order');

            // 「制作」を追加（既に存在する場合は名前・sort_order のみ更新）
            $existing = DB::table('assignments')
                ->where('department_id', $dept->id)
                ->where('code', 'seisaku')
                ->first();

            if ($existing) {
                DB::table('assignments')
                    ->where('id', $existing->id)
                    ->update(['name' => '制作', 'sort_order' => 2, 'active' => 1, 'updated_at' => now()]);
            } else {
                DB::table('assignments')->insert([
                    'department_id' => $dept->id,
                    'name'          => '制作',
                    'code'          => 'seisaku',
                    'description'   => null,
                    'sort_order'    => 2,
                    'active'        => 1,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('assignments')) {
            return;
        }

        DB::table('assignments')->where('code', 'seisaku')->delete();

        // sort_order を元に戻す
        $company = DB::table('companies')->where('code', 'SUNBRAIN')->first();
        if (! $company) {
            return;
        }

        foreach (['INFO', 'SEIHAN', 'ONDEMAND'] as $code) {
            $dept = DB::table('departments')
                ->where('company_id', $company->id)
                ->where('code', $code)
                ->first();
            if (! $dept) {
                continue;
            }
            DB::table('assignments')
                ->where('department_id', $dept->id)
                ->where('code', 'other')
                ->decrement('sort_order');
        }
    }
};

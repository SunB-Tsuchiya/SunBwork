<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorktypeSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');

        if (!$companyId) {
            $this->command->info('Company SUNBRAIN not found; skipping WorktypeSeeder');
            return;
        }

        $rows = [
            ['name' => 'A日程', 'start_time' => '09:00:00', 'end_time' => '17:30:00', 'sort_order' => 1],
            ['name' => 'B日程', 'start_time' => '08:00:00', 'end_time' => '16:30:00', 'sort_order' => 2],
            ['name' => 'C日程', 'start_time' => '10:00:00', 'end_time' => '18:30:00', 'sort_order' => 3],
            ['name' => '夜勤',  'start_time' => '18:00:00', 'end_time' => '05:30:00', 'sort_order' => 4],
        ];

        foreach ($rows as $r) {
            DB::table('worktypes')->updateOrInsert(
                ['name' => $r['name'], 'company_id' => $companyId],
                [
                    'company_id'  => $companyId,
                    'start_time'  => $r['start_time'],
                    'end_time'    => $r['end_time'],
                    'sort_order'  => $r['sort_order'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }

        // マイグレーションで挿入された company_id = NULL のレコードにも company_id を設定
        DB::table('worktypes')
            ->whereNull('company_id')
            ->whereIn('name', array_column($rows, 'name'))
            ->update(['company_id' => $companyId]);
    }
}

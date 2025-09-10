<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => '下書き', 'slug' => 'draft', 'sort_order' => 0],
            ['name' => '進行中', 'slug' => 'in_progress', 'sort_order' => 1],
            ['name' => '完了', 'slug' => 'completed', 'sort_order' => 2],
        ];

        foreach ($statuses as $s) {
            DB::table('statuses')->updateOrInsert(['slug' => $s['slug']], $s);
        }
    }
}

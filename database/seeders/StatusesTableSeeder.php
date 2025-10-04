<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StatusesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        $rows = [
            ['key' => 'confirmed', 'name' => '確認済み'],
            ['key' => 'scheduled', 'name' => 'セット済み'],
            ['key' => 'completed', 'name' => '完了'],
            ['key' => 'received', 'name' => '受信済み'],
        ];

        $payloads = array_map(function ($r) use ($now) {
            return [
                'key' => $r['key'],
                'slug' => $r['key'],
                'name' => $r['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $rows);

        // Use upsert to match existing rows by `slug` (legacy seeders/migrations used slug)
        // This avoids unique constraint violations if a slug already exists for the same logical status.
        DB::table('statuses')->upsert($payloads, ['slug'], ['key', 'name', 'updated_at']);

        // Also ensure legacy slugs (order, in_progress) exist with appropriate names and keys.
        $legacy = [
            ['slug' => 'order', 'key' => 'order', 'name' => '依頼'],
            ['slug' => 'in_progress', 'key' => 'in_progress', 'name' => '進行中'],
        ];
        foreach ($legacy as $l) {
            DB::table('statuses')->updateOrInsert([
                'slug' => $l['slug'],
            ], [
                'key' => $l['key'],
                'name' => $l['name'],
                'updated_at' => $now,
            ]);
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('statuses') || !Schema::hasColumn('statuses', 'slug') || !Schema::hasColumn('statuses', 'key')) {
            return;
        }

        $map = [
            'order' => 'order',
            'in_progress' => 'in_progress',
        ];

        foreach ($map as $slug => $key) {
            try {
                $row = DB::table('statuses')->where('slug', $slug)->first();
                if ($row && empty($row->key)) {
                    // update only when key is NULL or empty to be idempotent
                    DB::table('statuses')->where('id', $row->id)->update(['key' => $key, 'updated_at' => now()]);
                }
            } catch (\Throwable $e) {
                // ignore errors (migration should be safe)
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('statuses') || !Schema::hasColumn('statuses', 'slug') || !Schema::hasColumn('statuses', 'key')) {
            return;
        }

        $slugs = ['order', 'in_progress'];
        try {
            DB::table('statuses')->whereIn('slug', $slugs)->whereIn('key', $slugs)->update(['key' => null]);
        } catch (\Throwable $e) {
        }
    }
};

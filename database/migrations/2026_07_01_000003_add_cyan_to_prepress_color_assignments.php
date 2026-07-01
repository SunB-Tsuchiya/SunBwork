<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('prepress_color_assignments')->insertOrIgnore([
            'color_key'  => 'cyan',
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('prepress_color_assignments')->where('color_key', 'cyan')->delete();
    }
};

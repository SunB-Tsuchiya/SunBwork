<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_item_types')->insertOrIgnore([
            'name'        => '来社応対',
            'slug'        => 'client_visit',
            'coefficient' => 1.000,
            'description' => 'クライアントが来社しての応対・打合せ',
            'sort_order'  => 7,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('event_item_types')->where('slug', 'client_visit')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_titles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('applicable_role', ['admin', 'leader']);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 初期データ投入
        DB::table('position_titles')->insert([
            // Admin 用
            ['name' => '社長',     'applicable_role' => 'admin',  'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '取締役',   'applicable_role' => 'admin',  'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '役員',     'applicable_role' => 'admin',  'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            // Leader 用
            ['name' => '部長',     'applicable_role' => 'leader', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '次長',     'applicable_role' => 'leader', 'sort_order' => 5, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '課長',     'applicable_role' => 'leader', 'sort_order' => 6, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '課長代理', 'applicable_role' => 'leader', 'sort_order' => 7, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '係長',     'applicable_role' => 'leader', 'sort_order' => 8, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('position_titles');
    }
};

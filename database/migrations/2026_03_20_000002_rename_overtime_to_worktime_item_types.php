<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('overtime_item_types');

        Schema::create('worktime_item_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('coefficient', 6, 3)->default(1.000);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('type')->default('over'); // 'over' = 残業系, 'short' = 早退
            $table->timestamps();
        });

        // 初期データ
        $now = now();
        DB::table('worktime_item_types')->insert([
            ['name' => '残業',     'sort_order' => 1, 'coefficient' => 1.000, 'type' => 'over',  'created_at' => $now, 'updated_at' => $now],
            ['name' => '早退',     'sort_order' => 2, 'coefficient' => 1.000, 'type' => 'short', 'created_at' => $now, 'updated_at' => $now],
            ['name' => '超過残業', 'sort_order' => 3, 'coefficient' => 0.800, 'type' => 'over',  'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('worktime_item_types');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('worktypes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        DB::table('worktypes')->insert([
            ['name' => 'A日程', 'start_time' => '09:00:00', 'end_time' => '17:30:00', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'B日程', 'start_time' => '08:00:00', 'end_time' => '16:30:00', 'sort_order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'C日程', 'start_time' => '10:00:00', 'end_time' => '18:30:00', 'sort_order' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['name' => '夜勤',  'start_time' => '18:00:00', 'end_time' => '05:30:00', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('worktypes');
    }
};

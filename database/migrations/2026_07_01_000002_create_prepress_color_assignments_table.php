<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepress_color_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('color_key', 20)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // デフォルト10色を挿入
        $colors = ['indigo', 'blue', 'teal', 'green', 'yellow', 'orange', 'red', 'pink', 'purple', 'gray'];
        foreach ($colors as $i => $color) {
            DB::table('prepress_color_assignments')->insert([
                'color_key'  => $color,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prepress_color_assignments');
    }
};

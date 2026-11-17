<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('personal_calendar_items', function (Blueprint $table) {
            $table->json('repeat_rule')->nullable();
            $table->foreignId('repeat_previous_id')->nullable()->constrained('personal_calendar_items');
            $table->unique('repeat_previous_id', 'personal_todo_repeat_previous_unique');
            $table->unsignedInteger('repeat_next_version')->nullable();
            $table->timestamp('repeat_retracted_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('personal_calendar_items', function (Blueprint $table) {
            $table->dropForeign(['repeat_previous_id']);
            $table->dropUnique('personal_todo_repeat_previous_unique');
            $table->dropColumn(['repeat_rule','repeat_previous_id','repeat_next_version','repeat_retracted_at']);
        });
    }
};

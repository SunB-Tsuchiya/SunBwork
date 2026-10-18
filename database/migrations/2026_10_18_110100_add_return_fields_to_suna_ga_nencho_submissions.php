<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 差戻し（総務 → 従業員）: コメントと、直してほしい工程。再提出で消す（履歴は events に残る）
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->text('return_comment')->nullable()->after('snapshot');
            $table->json('return_steps')->nullable()->after('return_comment');
            $table->timestamp('returned_at')->nullable()->after('return_steps');
            $table->string('returned_by_name', 100)->nullable()->after('returned_at');
        });
    }

    public function down(): void
    {
        Schema::table('suna_ga_nencho_submissions', function (Blueprint $table) {
            $table->dropColumn(['return_comment', 'return_steps', 'returned_at', 'returned_by_name']);
        });
    }
};

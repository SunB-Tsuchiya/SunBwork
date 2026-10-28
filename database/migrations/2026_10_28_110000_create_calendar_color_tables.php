<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * チームルーム・案件のカレンダーの色設定（色ごとの名前と並び順）。チームごと・案件ごとに独立。
 * 外部キー名は、MySQL の上限（64 文字）に収まるよう、短く明示する。
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('team_calendar_colors')) {
            Schema::create('team_calendar_colors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('team_id');
                $table->string('color_key', 20);
                $table->string('label', 20)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('updated_by')->nullable();   // 最後に変更した人
                $table->timestamps();

                $table->unique(['team_id', 'color_key'], 'tcc_team_color_unique');
                $table->foreign('team_id', 'tcc_team_fk')->references('id')->on('teams')->cascadeOnDelete();
                $table->foreign('updated_by', 'tcc_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('project_calendar_colors')) {
            Schema::create('project_calendar_colors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_job_id');
                $table->string('color_key', 20);
                $table->string('label', 20)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();

                $table->unique(['project_job_id', 'color_key'], 'pcc_job_color_unique');
                $table->foreign('project_job_id', 'pcc_job_fk')->references('id')->on('project_jobs')->cascadeOnDelete();
                $table->foreign('updated_by', 'pcc_user_fk')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_calendar_colors');
        Schema::dropIfExists('team_calendar_colors');
    }
};

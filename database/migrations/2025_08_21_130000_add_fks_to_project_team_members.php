<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 既にテーブルがある前提で外部キーを追加する
        if (Schema::hasTable('project_team_members')) {
            Schema::table('project_team_members', function (Blueprint $table) {
                // カラムが存在しない場合は追加（必要なら）
                if (!Schema::hasColumn('project_team_members', 'project_job_id')) {
                    $table->unsignedBigInteger('project_job_id')->after('id');
                }
                if (!Schema::hasColumn('project_team_members', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->after('project_job_id');
                }

                // 外部キーを追加（既に同名の外部キーがあるとエラーになるので注意）
                $table->foreign('project_job_id')
                    ->references('id')->on('project_jobs')
                    ->onDelete('cascade');
                $table->foreign('user_id')
                    ->references('id')->on('users')
                    ->onDelete('cascade');
            });
            return;
        }

        // テーブルが存在しない場合のみ作成（最初から作るケース）
        Schema::create('project_team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('project_job_id')
                ->references('id')->on('project_jobs')
                ->onDelete('cascade');
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // 外部キーを先に削除してからテーブルを削除
        if (Schema::hasTable('project_team_members')) {
            Schema::table('project_team_members', function (Blueprint $table) {
                // 外部キー名がわからない場合は DB 内の制約名を確認して削除してください
                $table->dropForeign(['project_job_id']);
                $table->dropForeign(['user_id']);
            });
        }

        Schema::dropIfExists('project_team_members');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proof_requests', function (Blueprint $table) {
            $table->id();

            // 依頼元ジョブ（MyJobBox / 進行表から紐づく project_job_assignment）
            $table->foreignId('project_job_assignment_id')
                  ->nullable()
                  ->constrained('project_job_assignments')
                  ->nullOnDelete();

            // 関連案件（参照用）
            $table->foreignId('project_job_id')
                  ->nullable()
                  ->constrained('project_jobs')
                  ->nullOnDelete();

            // 依頼者
            $table->foreignId('requester_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // 担当窓口（ProofCoordinator が受理すると自分の ID がセット）
            $table->foreignId('proof_coordinator_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // 担当校正員
            $table->foreignId('proofreader_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // 校正依頼タイトル（ジョブタイトルから引き継ぎ・編集可）
            $table->string('title');

            // 校正専用締め切り（依頼者が設定、ジョブの締め切りとは別）
            $table->date('deadline')->nullable();

            // ステータス
            $table->enum('status', ['pending', 'assigned', 'in_progress', 'completed'])
                  ->default('pending');

            // 依頼備考
            $table->text('note')->nullable();

            // 完了日時
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'deadline']);
            $table->index('requester_id');
            $table->index('proofreader_id');
        });

        // user_role enum に proof_coordinator を追加（MySQL のみ）
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM('superadmin','admin','leader','coordinator','proof_coordinator','clerk','user') NOT NULL DEFAULT 'user'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proof_requests');

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN user_role ENUM('superadmin','admin','leader','coordinator','clerk','user') NOT NULL DEFAULT 'user'");
        }
    }
};

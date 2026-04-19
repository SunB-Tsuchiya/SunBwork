<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * source_assignment_id が Coordinator 割当（sender_id ≠ user_id）を指している
     * 自己割当レコードを coordinator_assignment_id へ移行し、source_assignment_id をクリアする。
     *
     * source_assignment_id は「続きチェーン（日またぎ続きジョブ）」専用であり、
     * pja100→pja101 の Coordinator→User リンクには coordinator_assignment_id を使う。
     */
    public function up(): void
    {
        // 自己割当（sender_id = user_id）かつ source_assignment_id が
        // Coordinator 割当（sender_id ≠ user_id）を指しているレコードを移行
        DB::statement('
            UPDATE project_job_assignments AS a
            JOIN project_job_assignments AS src ON src.id = a.source_assignment_id
            SET a.coordinator_assignment_id = a.source_assignment_id,
                a.source_assignment_id      = NULL
            WHERE a.sender_id = a.user_id
              AND a.source_assignment_id IS NOT NULL
              AND src.sender_id != src.user_id
        ');
    }

    public function down(): void
    {
        // 逆方向: coordinator_assignment_id → source_assignment_id に戻す
        DB::statement('
            UPDATE project_job_assignments AS a
            JOIN project_job_assignments AS src ON src.id = a.coordinator_assignment_id
            SET a.source_assignment_id      = a.coordinator_assignment_id,
                a.coordinator_assignment_id = NULL
            WHERE a.sender_id = a.user_id
              AND a.coordinator_assignment_id IS NOT NULL
              AND src.sender_id != src.user_id
        ');
    }
};

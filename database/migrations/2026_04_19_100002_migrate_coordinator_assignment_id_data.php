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
        // SQLite は UPDATE...JOIN 非対応のためサブクエリ方式で統一
        // MySQL では UPDATE対象テーブルをサブクエリのFROMに直接使えないため
        // 二重サブクエリでラップして回避（derived table trick）
        DB::statement('
            UPDATE project_job_assignments
            SET coordinator_assignment_id = source_assignment_id,
                source_assignment_id      = NULL
            WHERE sender_id = user_id
              AND source_assignment_id IS NOT NULL
              AND source_assignment_id IN (
                  SELECT id FROM (
                      SELECT id FROM project_job_assignments
                      WHERE sender_id != user_id
                  ) AS _coordinator_ids
              )
        ');
    }

    public function down(): void
    {
        // 逆方向: coordinator_assignment_id → source_assignment_id に戻す
        DB::statement('
            UPDATE project_job_assignments
            SET source_assignment_id      = coordinator_assignment_id,
                coordinator_assignment_id = NULL
            WHERE sender_id = user_id
              AND coordinator_assignment_id IS NOT NULL
              AND coordinator_assignment_id IN (
                  SELECT id FROM (
                      SELECT id FROM project_job_assignments
                      WHERE sender_id != user_id
                  ) AS _coordinator_ids
              )
        ');
    }
};

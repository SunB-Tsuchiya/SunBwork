<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * project_job_assignment_by_myself テーブルのデータを
 * project_job_assignments テーブルへ統合する。
 * - sender_id = user_id とすることで自己割当マーカーを設定する。
 * - events.project_job_assignment_id を新IDに更新する。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // 旧テーブルが存在しない場合はスキップ（既に統合済み or ローカル環境で未作成）
        if (! Schema::hasTable('project_job_assignment_by_myself')) {
            return;
        }

        // 1. project_job_assignments に scheduled_at を追加（未存在の場合）
        if (! Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
            Schema::table('project_job_assignments', function (Blueprint $table) {
                $table->timestamp('scheduled_at')->nullable()->after('scheduled');
            });
        }

        // 2. 旧テーブルのレコードを新テーブルへ移行し、旧ID→新IDのマッピングを作成
        $oldRecords = DB::table('project_job_assignment_by_myself')->get();
        $mapping = []; // [old_id => new_id]

        foreach ($oldRecords as $old) {
            $newId = DB::table('project_job_assignments')->insertGetId([
                'project_job_id'    => $old->project_job_id,
                'user_id'           => $old->user_id,
                'sender_id'         => $old->user_id, // 自己割当: sender = user
                'title'             => $old->title,
                'detail'            => $old->detail,
                'difficulty_id'     => $old->difficulty_id,
                'desired_end_date'  => $old->desired_end_date,
                'desired_time'      => $old->desired_time,
                'estimated_hours'   => $old->estimated_hours,
                'assigned'          => $old->assigned,
                'accepted'          => $old->accepted,
                'work_item_type_id' => $old->work_item_type_id,
                'size_id'           => $old->size_id,
                'stage_id'          => $old->stage_id,
                'status_id'         => $old->status_id,
                'company_id'        => $old->company_id,
                'department_id'     => $old->department_id,
                'amounts'           => $old->amounts,
                'amounts_unit'      => $old->amounts_unit,
                'completed'         => $old->completed,
                'read_at'           => $old->read_at,
                'scheduled'         => $old->scheduled,
                'scheduled_at'      => $old->scheduled_at,
                'created_at'        => $old->created_at,
                'updated_at'        => $old->updated_at,
            ]);
            $mapping[$old->id] = $newId;
        }

        // 3. events.project_job_assignment_id を旧IDから新IDへ更新
        if (Schema::hasColumn('events', 'project_job_assignment_id') && ! empty($mapping)) {
            foreach ($mapping as $oldId => $newId) {
                DB::table('events')
                    ->where('project_job_assignment_id', $oldId)
                    ->update(['project_job_assignment_id' => $newId]);
            }
        }

        // 旧テーブルはデータを保持したまま残す（安全のため）
        // 削除は別途確認後に行うこと
    }

    public function down(): void
    {
        // このマイグレーションは非可逆的なデータ移行のため down は実装しない
    }
};

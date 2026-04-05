<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unify project_job_assignments and project_job_assignment_by_myself into a single table.
 *
 * Distinguishing rule after this migration:
 *   sender_id = user_id  → self-assigned (was "by_myself")
 *   sender_id ≠ user_id  → coordinator-assigned (was canonical assignment)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add columns that only existed in project_job_assignment_by_myself
        Schema::table('project_job_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('project_job_assignments', 'completed')) {
                $table->boolean('completed')->default(false)->after('accepted');
            }
            if (! Schema::hasColumn('project_job_assignments', 'scheduled')) {
                $table->boolean('scheduled')->default(false)->after('completed');
            }
            if (! Schema::hasColumn('project_job_assignments', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('scheduled');
            }
            if (! Schema::hasColumn('project_job_assignments', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('scheduled_at');
            }
            if (! Schema::hasColumn('project_job_assignments', 'start_time')) {
                $table->time('start_time')->nullable()->after('desired_time');
            }
        });

        // 2. Migrate data from project_job_assignment_by_myself if the table exists
        if (Schema::hasTable('project_job_assignment_by_myself')) {
            // Determine which columns exist in the destination table
            $destCols = Schema::getColumnListing('project_job_assignments');
            $srcCols  = Schema::getColumnListing('project_job_assignment_by_myself');

            DB::table('project_job_assignment_by_myself')->orderBy('id')->each(function ($row) use ($destCols, $srcCols) {
                $insert = [
                    'project_job_id'    => $row->project_job_id,
                    'user_id'           => $row->user_id,
                    'sender_id'         => $row->sender_id ?? $row->user_id,
                    'title'             => $row->title,
                    'detail'            => $row->detail ?? null,
                    'difficulty_id'     => $row->difficulty_id ?? null,
                    'desired_end_date'  => $row->desired_end_date ?? null,
                    'desired_time'      => $row->desired_time ?? null,
                    'start_time'        => $row->start_time ?? null,
                    'starts_at'         => $row->starts_at ?? null,
                    'ends_at'           => $row->ends_at ?? null,
                    'estimated_hours'   => $row->estimated_hours ?? null,
                    'assigned'          => $row->assigned ?? 0,
                    'accepted'          => $row->accepted ?? 0,
                    'completed'         => $row->completed ?? 0,
                    'scheduled'         => $row->scheduled ?? 0,
                    'scheduled_at'      => $row->scheduled_at ?? null,
                    'read_at'           => $row->read_at ?? null,
                    'size_id'           => $row->size_id ?? null,
                    'work_item_type_id' => $row->work_item_type_id ?? null,
                    'stage_id'          => $row->stage_id ?? null,
                    'status_id'         => $row->status_id ?? null,
                    'company_id'        => $row->company_id ?? null,
                    'department_id'     => $row->department_id ?? null,
                    'amounts'           => $row->amounts ?? null,
                    'amounts_unit'      => $row->amounts_unit ?? null,
                    'created_at'        => $row->created_at,
                    'updated_at'        => $row->updated_at,
                ];

                // Only include dest columns that actually exist
                $insert = array_filter($insert, fn($k) => in_array($k, $destCols), ARRAY_FILTER_USE_KEY);

                DB::table('project_job_assignments')->insert($insert);
            });

            // 3. Drop the old table
            Schema::drop('project_job_assignment_by_myself');
        }
    }

    public function down(): void
    {
        // Recreate the old table (empty – no data rollback)
        if (! Schema::hasTable('project_job_assignment_by_myself')) {
            Schema::create('project_job_assignment_by_myself', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_job_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('sender_id')->nullable()->index();
                $table->string('title')->nullable();
                $table->text('detail')->nullable();
                $table->string('difficulty')->default('normal');
                $table->unsignedBigInteger('difficulty_id')->nullable()->index();
                $table->date('desired_start_date')->nullable();
                $table->date('desired_end_date')->nullable();
                $table->time('desired_time')->nullable();
                $table->time('start_time')->nullable();
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->decimal('estimated_hours', 6, 2)->nullable();
                $table->boolean('assigned')->default(false);
                $table->boolean('accepted')->default(false);
                $table->boolean('completed')->default(false);
                $table->boolean('scheduled')->default(false);
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->unsignedBigInteger('size_id')->nullable()->index();
                $table->unsignedBigInteger('work_item_type_id')->nullable()->index();
                $table->unsignedBigInteger('stage_id')->nullable()->index();
                $table->unsignedBigInteger('status_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->integer('amounts')->nullable();
                $table->string('amounts_unit')->nullable();
                $table->timestamps();
            });
        }

        // Remove columns added in up() (best-effort)
        Schema::table('project_job_assignments', function (Blueprint $table) {
            foreach (['completed', 'scheduled', 'scheduled_at', 'read_at', 'start_time'] as $col) {
                if (Schema::hasColumn('project_job_assignments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

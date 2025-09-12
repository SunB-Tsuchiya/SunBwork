<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('work_items')) {
            // nothing to migrate
            return;
        }

        // create presets table if missing
        if (!Schema::hasTable('work_item_presets')) {
            Schema::create('work_item_presets', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('work_item_type_id')->nullable()->index();
                $table->unsignedBigInteger('size_id')->nullable()->index();
                $table->integer('pages')->nullable();
                $table->integer('quantity')->nullable();
                $table->integer('estimated_minutes')->nullable();
                $table->string('status')->default('preset');
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->timestamps();
            });
        }

        // Copy work_items into presets table
        $workItems = DB::table('work_items')->get();
        foreach ($workItems as $wi) {
            DB::table('work_item_presets')->updateOrInsert(
                ['title' => $wi->title],
                [
                    'description' => $wi->description ?? null,
                    'work_item_type_id' => $wi->work_item_type_id ?? null,
                    'size_id' => $wi->size_id ?? null,
                    'pages' => $wi->pages ?? null,
                    'quantity' => $wi->quantity ?? null,
                    'estimated_minutes' => $wi->estimated_minutes ?? null,
                    'status' => $wi->status ?? 'preset',
                    'company_id' => $wi->company_id ?? null,
                    'department_id' => $wi->department_id ?? null,
                    'created_at' => $wi->created_at ?? now(),
                    'updated_at' => $wi->updated_at ?? now(),
                ]
            );
        }

        // For work_items that were attached to project jobs via scripts or work_item_id FK on assignments
        // attempt to create ProjectJobAssignment entries when possible
        if (Schema::hasTable('project_job_assignments')) {
            foreach ($workItems as $wi) {
                // If work_item had project_job_id stored (legacy scripts), try to use it
                if (!empty($wi->project_job_id) && Schema::hasTable('project_job_assignments')) {
                    // create assignment if not exists (matching title + project_job_id)
                    $exists = DB::table('project_job_assignments')->where('project_job_id', $wi->project_job_id)->where('title', $wi->title)->first();
                    if (!$exists) {
                        $assignmentId = DB::table('project_job_assignments')->insertGetId([
                            'project_job_id' => $wi->project_job_id,
                            'user_id' => null,
                            'title' => $wi->title,
                            'detail' => $wi->description ?? null,
                            'difficulty' => 'normal',
                            'desired_start_date' => null,
                            'desired_end_date' => null,
                            'desired_time' => null,
                            'estimated_hours' => null,
                            'size_id' => $wi->size_id ?? null,
                            'work_item_type_id' => $wi->work_item_type_id ?? null,
                            'stage_id' => null,
                            'status_id' => null,
                            'company_id' => $wi->company_id ?? null,
                            'department_id' => $wi->department_id ?? null,
                            'created_at' => $wi->created_at ?? now(),
                            'updated_at' => $wi->updated_at ?? now(),
                        ]);

                        // Update related stage entries to point to this assignment where applicable
                        if (Schema::hasTable('work_item_stage_entries')) {
                            DB::table('work_item_stage_entries')->where('work_item_id', $wi->id)->update(['work_item_id' => null]);
                            // Note: we don't create project_job_assignment_id column here; a separate migration can add it.
                        }
                    }
                }
            }
        }

        // Before dropping work_items, remove foreign key constraints referencing it (best-effort)
        // 1) work_item_stage_entries.work_item_id
        if (Schema::hasTable('work_item_stage_entries') && Schema::hasColumn('work_item_stage_entries', 'work_item_id')) {
            try {
                Schema::table('work_item_stage_entries', function (Blueprint $table) {
                    try {
                        $table->dropForeign(['work_item_id']);
                    } catch (\Exception $e) {
                        // ignore if constraint name differs or doesn't exist
                    }
                });
            } catch (\Exception $e) {
                try { DB::statement('SET FOREIGN_KEY_CHECKS=0'); } catch (\Exception $_) {}
            }
        }

        // 2) project_job_assignments.work_item_id (drop FK and remove column)
        if (Schema::hasTable('project_job_assignments') && Schema::hasColumn('project_job_assignments', 'work_item_id')) {
            try {
                Schema::table('project_job_assignments', function (Blueprint $table) {
                    try {
                        $table->dropForeign(['work_item_id']);
                    } catch (\Exception $e) {
                        // ignore
                    }
                    // drop the column since work_items are being removed
                    try {
                        $table->dropColumn('work_item_id');
                    } catch (\Exception $e) {
                        // ignore if cannot drop
                    }
                });
            } catch (\Exception $e) {
                try { DB::statement('SET FOREIGN_KEY_CHECKS=0'); } catch (\Exception $_) {}
            }
        }

        // Finally, drop work_items table
        Schema::dropIfExists('work_items');

        // Re-enable foreign key checks if we disabled them earlier
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (\Exception $e) {
            // ignore
        }
    }

    public function down(): void
    {
        // Best-effort recreate work_items table
        if (!Schema::hasTable('work_items')) {
            Schema::create('work_items', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('work_item_type_id')->nullable();
                $table->unsignedBigInteger('size_id')->nullable();
                $table->integer('pages')->nullable();
                $table->integer('quantity')->nullable();
                $table->integer('estimated_minutes')->nullable();
                $table->string('status')->default('draft');
                $table->json('specs')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->timestamps();
            });
        }
    }
};

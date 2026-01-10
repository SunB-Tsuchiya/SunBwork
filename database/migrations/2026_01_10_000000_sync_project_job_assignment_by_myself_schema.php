<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_job_assignment_by_myself')) {
            // nothing to sync
            return;
        }

        $count = 0;
        try {
            $count = (int) DB::table('project_job_assignment_by_myself')->count();
        } catch (\Throwable $e) {
            // ignore
        }

        // If table is empty, drop & recreate to exactly match canonical schema
        if ($count === 0) {
            Schema::dropIfExists('project_job_assignment_by_myself');
            Schema::create('project_job_assignment_by_myself', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_job_id')->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();

                // canonical fields
                $table->string('title')->nullable();
                $table->text('detail')->nullable();
                $table->string('difficulty')->default('normal');

                // desired dates/times: date + time columns
                $table->date('desired_start_date')->nullable();
                $table->date('desired_end_date')->nullable();
                $table->time('desired_time')->nullable();

                // fractional estimated hours
                $table->decimal('estimated_hours', 6, 2)->nullable();

                $table->boolean('assigned')->default(false);
                $table->boolean('accepted')->default(false);
                $table->boolean('scheduled')->default(false);

                // Lookup columns
                $table->unsignedBigInteger('size_id')->nullable()->index();
                $table->unsignedBigInteger('work_item_type_id')->nullable()->index();
                $table->unsignedBigInteger('stage_id')->nullable()->index();
                $table->unsignedBigInteger('status_id')->nullable()->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();

                // quantity
                $table->integer('amounts')->nullable();
                $table->string('amounts_unit')->nullable();

                // scheduling
                $table->timestamp('read_at')->nullable();
                $table->timestamp('scheduled_at')->nullable();

                // start/end timestamps for precise scheduling
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('ends_at')->nullable();

                // start_time (time-of-day) separate from desired_time
                $table->time('start_time')->nullable();

                // sender for requests
                $table->unsignedBigInteger('sender_id')->nullable()->index();

                // difficulty lookup
                $table->unsignedBigInteger('difficulty_id')->nullable()->index();

                $table->timestamps();

                // optional foreign keys where applicable
                try {
                    if (Schema::hasTable('project_jobs')) {
                        $table->foreign('project_job_id')->references('id')->on('project_jobs')->onDelete('cascade');
                    }
                    if (Schema::hasTable('users')) {
                        $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                        $table->foreign('sender_id')->references('id')->on('users')->onDelete('set null');
                    }
                    if (Schema::hasTable('sizes')) {
                        $table->foreign('size_id')->references('id')->on('sizes')->onDelete('set null');
                    }
                    if (Schema::hasTable('work_item_types')) {
                        $table->foreign('work_item_type_id')->references('id')->on('work_item_types')->onDelete('set null');
                    }
                    if (Schema::hasTable('stages')) {
                        $table->foreign('stage_id')->references('id')->on('stages')->onDelete('set null');
                    }
                    if (Schema::hasTable('statuses')) {
                        $table->foreign('status_id')->references('id')->on('statuses')->onDelete('set null');
                    }
                    if (Schema::hasTable('companies')) {
                        $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
                    }
                    if (Schema::hasTable('departments')) {
                        $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                    }
                    if (Schema::hasTable('difficulties')) {
                        $table->foreign('difficulty_id')->references('id')->on('difficulties')->onDelete('set null');
                    }
                } catch (\Throwable $__e) {
                    // ignore FK creation errors in some environments
                }
            });

            return;
        }

        // If non-empty, try to add missing columns where safe (best-effort)
        Schema::table('project_job_assignment_by_myself', function (Blueprint $table) {
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'project_job_id')) {
                $table->unsignedBigInteger('project_job_id')->nullable()->index();
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'desired_time')) {
                $table->time('desired_time')->nullable()->after('desired_end_date');
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'start_time')) {
                $table->time('start_time')->nullable()->after('desired_start_date');
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'estimated_hours')) {
                $table->decimal('estimated_hours', 6, 2)->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'amounts')) {
                $table->integer('amounts')->nullable()->after('size_id');
                $table->string('amounts_unit')->nullable()->after('amounts');
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'starts_at')) {
                $table->timestamp('starts_at')->nullable();
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'ends_at')) {
                $table->timestamp('ends_at')->nullable();
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'sender_id')) {
                $table->unsignedBigInteger('sender_id')->nullable()->after('start_time');
            }
            if (!Schema::hasColumn('project_job_assignment_by_myself', 'difficulty_id')) {
                $table->unsignedBigInteger('difficulty_id')->nullable()->after('difficulty');
            }
        });
    }

    public function down(): void
    {
        // Attempt to revert: if table exists and is empty, drop it and recreate minimal original schema
        if (!Schema::hasTable('project_job_assignment_by_myself')) {
            return;
        }

        $count = 0;
        try {
            $count = (int) DB::table('project_job_assignment_by_myself')->count();
        } catch (\Throwable $e) {
            // ignore
        }

        if ($count === 0) {
            Schema::dropIfExists('project_job_assignment_by_myself');
            Schema::create('project_job_assignment_by_myself', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('project_job_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('title')->nullable();
                $table->text('detail')->nullable();
                $table->string('difficulty')->nullable();
                $table->float('estimated_hours')->nullable();
                $table->date('desired_start_date')->nullable();
                $table->date('desired_end_date')->nullable();
                $table->string('desired_time')->nullable();
                $table->timestamp('desired_at')->nullable();
                $table->timestamps();
            });
        } else {
            // non-empty: do not attempt destructive revert
        }
    }
};

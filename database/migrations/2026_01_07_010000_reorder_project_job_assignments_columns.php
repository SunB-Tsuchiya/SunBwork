<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reorder columns in project_job_assignments for readability.
     *
     * WARNING:
     *  - ALTER TABLE ... MODIFY requires exact column type definitions.
     *  - BACKUP the table before running this migration (mysqldump).
     *  - Test on a staging copy first.
     */
    public function up(): void
    {
        if (!Schema::hasTable('project_job_assignments')) return;
        if (DB::getDriverName() === 'sqlite') return;

        // Adjust these MODIFY statements to match your actual column definitions.
        // The examples below use typical definitions inferred from the codebase.
        // If your columns differ (types/lengths/defaults), edit accordingly.

        try {
            // place core FK/id columns at the front
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `project_job_id` BIGINT UNSIGNED NULL AFTER `id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `user_id` BIGINT UNSIGNED NULL AFTER `project_job_id`");
            // move sender_id next to user_id (sender may be null)
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `sender_id` BIGINT UNSIGNED NULL AFTER `user_id`");

            // primary descriptive fields
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `title` VARCHAR(255) NULL AFTER `sender_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `detail` TEXT NULL AFTER `title`");

            // difficulty/difficulty_id (use BIGINT UNSIGNED to match existing schema)
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `difficulty_id` BIGINT UNSIGNED NULL AFTER `detail`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `difficulty` VARCHAR(255) NOT NULL DEFAULT 'normal' AFTER `difficulty_id`");

            // desired / scheduling related columns
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `desired_start_date` DATE NULL AFTER `difficulty`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `desired_end_date` DATE NULL AFTER `desired_start_date`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `desired_time` TIME NULL AFTER `desired_end_date`");
            // place starts_at/ends_at closer to scheduling fields (use TIMESTAMP as in current schema)
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `starts_at` TIMESTAMP NULL AFTER `desired_time`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `ends_at` TIMESTAMP NULL AFTER `starts_at`");

            // estimate & status
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `estimated_hours` DECIMAL(6,2) NULL AFTER `ends_at`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `assigned` TINYINT(1) DEFAULT 0 AFTER `estimated_hours`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `accepted` TINYINT(1) DEFAULT 0 AFTER `assigned`");

            // lookup/relationship ids
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `work_item_type_id` INT NULL AFTER `accepted`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `size_id` INT NULL AFTER `work_item_type_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `stage_id` INT NULL AFTER `size_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `status_id` INT NULL AFTER `stage_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `company_id` INT NULL AFTER `status_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `department_id` INT NULL AFTER `company_id`");

            // quantity
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `amounts` INT NULL AFTER `department_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `amounts_unit` VARCHAR(255) NULL AFTER `amounts`");

            // timestamps remain at the end (created_at / updated_at)
        } catch (\Exception $e) {
            // If something goes wrong, rethrow so migration fails and can be rolled back
            throw $e;
        }
    }

    public function down(): void
    {
        // This down() attempts to move columns back to a reasonable default,
        // but precise reversal is environment-specific. Keep this migration
        // reversible only if you update types/positions accordingly.
        if (!Schema::hasTable('project_job_assignments')) return;
        if (DB::getDriverName() === 'sqlite') return;

        try {
            // Move starts_at/ends_at and sender_id back to the end as before
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `amounts_unit` VARCHAR(32) NULL AFTER `amounts`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `amounts` INT NULL AFTER `department_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `department_id` INT NULL AFTER `company_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `company_id` INT NULL AFTER `status_id`");

            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `work_item_type_id` INT NULL AFTER `accepted`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `status_id` INT NULL AFTER `stage_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `stage_id` INT NULL AFTER `size_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `size_id` INT NULL AFTER `work_item_type_id`");

            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `accepted` TINYINT(1) DEFAULT 0 AFTER `assigned`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `assigned` TINYINT(1) DEFAULT 0 AFTER `estimated_hours`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `estimated_hours` DECIMAL(6,2) NULL AFTER `desired_time`");

            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `ends_at` DATETIME NULL AFTER `starts_at`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `starts_at` DATETIME NULL AFTER `desired_time`");

            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `desired_time` TIME NULL AFTER `desired_end_date`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `desired_end_date` DATE NULL AFTER `desired_start_date`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `desired_start_date` DATE NULL AFTER `difficulty`");

            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `difficulty` VARCHAR(255) NULL AFTER `detail`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `difficulty_id` INT NULL AFTER `detail`");

            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `detail` TEXT NULL AFTER `title`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `title` VARCHAR(255) NULL AFTER `sender_id`");

            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `sender_id` BIGINT UNSIGNED NULL AFTER `user_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `user_id` BIGINT UNSIGNED NULL AFTER `project_job_id`");
            DB::statement("ALTER TABLE `project_job_assignments` MODIFY `project_job_id` BIGINT UNSIGNED NULL AFTER `id`");
        } catch (\Exception $e) {
            throw $e;
        }
    }
};

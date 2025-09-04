<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop existing foreign key, make column nullable, re-add FK with ON DELETE SET NULL
        // Use raw statements to avoid requiring doctrine/dbal for column change
        DB::statement('ALTER TABLE `assignments` DROP FOREIGN KEY `assignments_department_id_foreign`');
        DB::statement('ALTER TABLE `assignments` MODIFY `department_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `assignments` ADD CONSTRAINT `assignments_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        // Revert: drop FK, make NOT NULL, re-add FK with ON DELETE CASCADE
        DB::statement('ALTER TABLE `assignments` DROP FOREIGN KEY `assignments_department_id_foreign`');
        DB::statement('ALTER TABLE `assignments` MODIFY `department_id` BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE `assignments` ADD CONSTRAINT `assignments_department_id_foreign` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE');
    }
};

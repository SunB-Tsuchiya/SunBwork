<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Backfill company_id/department_id using company.code='SUNBRAIN' and department.code='INFO'.
     * Also initialize sort_order to the row id where it is NULL or 0.
     *
     * @return void
     */
    public function up()
    {
        $companyId = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        if ($companyId && $departmentId) {
            $tables = ['sizes', 'work_item_types', 'stages'];

            foreach ($tables as $table) {
                // Fill company_id where NULL
                DB::table($table)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyId]);

                // Fill department_id where NULL
                DB::table($table)
                    ->whereNull('department_id')
                    ->update(['department_id' => $departmentId]);

                // Initialize sort_order where it's NULL or 0; set to the current id to preserve insertion order
                DB::statement("
                    UPDATE {$table}
                    SET sort_order = id
                    WHERE sort_order IS NULL OR sort_order = 0
                ");
            }
        } else {
            // If one of them is missing, write to log via DB (no logger here) - skip silently
        }
    }

    /**
     * Reverse the migrations.
     * This will null out company_id/department_id where they were set to the SUNBRAIN/INFO ids
     * and reset sort_order to 0 where it equals id (best-effort).
     *
     * @return void
     */
    public function down()
    {
        $companyId = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        if ($companyId && $departmentId) {
            $tables = ['sizes', 'work_item_types', 'stages'];

            foreach ($tables as $table) {
                DB::table($table)
                    ->where('company_id', $companyId)
                    ->update(['company_id' => null]);

                DB::table($table)
                    ->where('department_id', $departmentId)
                    ->update(['department_id' => null]);

                DB::statement("
                    UPDATE {$table}
                    SET sort_order = 0
                    WHERE sort_order = id
                ");
            }
        }
    }
};

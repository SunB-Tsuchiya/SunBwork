<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'work_items';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (!Schema::hasColumn($tableName, 'company_id')) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->after('team_id');
            }
            if (!Schema::hasColumn($tableName, 'department_id')) {
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('company_id');
            }
            if (!Schema::hasColumn($tableName, 'status_id')) {
                $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete()->after('estimated_minutes');
            }
        });
    }

    public function down(): void
    {
        $tableName = 'work_items';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'status_id')) {
                $table->dropForeign(['status_id']);
                $table->dropColumn('status_id');
            }
            if (Schema::hasColumn($tableName, 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }
            if (Schema::hasColumn($tableName, 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }
};

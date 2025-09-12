<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['work_item_types', 'sizes', 'stages'];
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (!Schema::hasColumn($t, 'company_id')) {
                    $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete()->after('id');
                }
                if (!Schema::hasColumn($t, 'department_id')) {
                    $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete()->after('company_id');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['work_item_types', 'sizes', 'stages'];
        foreach ($tables as $t) {
            Schema::table($t, function (Blueprint $table) use ($t) {
                if (Schema::hasColumn($t, 'department_id')) {
                    $table->dropForeign([$t . '_department_id_foreign']);
                    $table->dropColumn('department_id');
                }
                if (Schema::hasColumn($t, 'company_id')) {
                    $table->dropForeign([$t . '_company_id_foreign']);
                    $table->dropColumn('company_id');
                }
            });
        }
    }
};

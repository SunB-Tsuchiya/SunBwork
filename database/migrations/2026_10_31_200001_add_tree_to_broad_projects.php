<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broad_projects', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('broad_projects')->restrictOnDelete();
            $table->string('import_key', 80)->nullable();
            $table->unique(['company_id', 'department_id', 'import_key'], 'broad_projects_scope_import_unique');
        });
    }

    public function down(): void
    {
        Schema::table('broad_projects', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropUnique('broad_projects_scope_import_unique');
            $table->dropColumn(['parent_id', 'import_key']);
        });
    }
};

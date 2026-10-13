<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broad_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['company_id', 'department_id', 'is_active']);
        });

        Schema::table('personal_work_entries', function (Blueprint $table) {
            $table->foreignId('broad_project_id')->nullable()->constrained('broad_projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_work_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('broad_project_id');
        });
        Schema::dropIfExists('broad_projects');
    }
};

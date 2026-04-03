<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained('progress_rows')->cascadeOnDelete();
            $table->string('col_key');
            $table->text('value_text')->nullable();
            $table->date('value_date')->nullable();
            $table->boolean('value_bool')->nullable();
            $table->foreignId('value_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('project_job_assignments')->nullOnDelete();
            $table->timestamps();

            $table->unique(['row_id', 'col_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_cells');
    }
};

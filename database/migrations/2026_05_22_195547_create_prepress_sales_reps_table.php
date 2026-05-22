<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepress_sales_reps', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('company', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('prepress_sales_rep_department', function (Blueprint $table) {
            $table->foreignId('sales_rep_id')
                  ->constrained('prepress_sales_reps')
                  ->cascadeOnDelete();
            $table->foreignId('department_id')
                  ->constrained('departments')
                  ->cascadeOnDelete();
            $table->primary(['sales_rep_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepress_sales_rep_department');
        Schema::dropIfExists('prepress_sales_reps');
    }
};

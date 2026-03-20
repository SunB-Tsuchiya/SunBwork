<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('worktype_id')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->time('scheduled_start')->nullable();
            $table->time('scheduled_end')->nullable();
            $table->smallInteger('overtime_minutes')->default(0);
            $table->smallInteger('early_leave_minutes')->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('worktype_id')->references('id')->on('worktypes')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_records');
    }
};

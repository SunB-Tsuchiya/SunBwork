<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('work_job_completion_syncs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('department_id');
            $table->date('occurred_on');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('synced_at')->nullable()->index();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_job_completion_syncs');
    }
};

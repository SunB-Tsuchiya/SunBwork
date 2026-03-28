<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leader_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('client_management')->default(false);
            $table->boolean('diary_management')->default(false);
            $table->boolean('workload_analysis')->default(false);
            $table->boolean('workload_setting')->default(false);
            $table->boolean('work_record_management')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leader_permissions');
    }
};

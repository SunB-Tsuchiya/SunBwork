<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->boolean('company_management')->default(false);
            $table->boolean('user_management')->default(false);
            $table->boolean('team_management')->default(false);
            $table->boolean('diary_management')->default(false);
            $table->boolean('client_management')->default(false);
            $table->boolean('workload_analysis')->default(false);
            $table->boolean('worktype_setting')->default(false);
            $table->boolean('work_record_management')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_permissions');
    }
};

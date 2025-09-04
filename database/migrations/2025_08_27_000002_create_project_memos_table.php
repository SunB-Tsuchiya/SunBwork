<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_memos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id');
            $table->unsignedBigInteger('user_id');
            $table->text('memo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_memos');
    }
};

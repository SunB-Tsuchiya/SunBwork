<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_job_continuations', function (Blueprint $table) {
            $table->string('request_key', 64)->primary();
            $table->unsignedBigInteger('user_id');
            // Historical source identity remains after a permitted leaf deletion.
            $table->unsignedBigInteger('source_entry_id');
            $table->string('payload_hash', 64);
            $table->foreignId('entry_id')->nullable()->constrained('personal_work_entries')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_job_continuations');
    }
};

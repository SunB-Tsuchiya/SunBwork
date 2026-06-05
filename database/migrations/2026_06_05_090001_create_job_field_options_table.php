<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_field_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('group_key', 100)->default('');
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('coefficient', 6, 3)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'group_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_field_options');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clerk_schedule_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('color_key', 20)->default('indigo');
            $table->string('recurrence', 30);
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->unsignedTinyInteger('ordinal')->nullable(); // 0は最終
            $table->unsignedTinyInteger('day_of_week')->nullable();
            $table->json('custom_dates')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('clerk_schedule_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('clerk_schedule_rules')->cascadeOnDelete();
            $table->date('nominal_date');
            $table->foreignId('clerk_event_id')->nullable()->constrained('clerk_events')->nullOnDelete();
            $table->string('state', 20)->default('generated');
            $table->timestamps();
            $table->unique(['rule_id', 'nominal_date'], 'clerk_occurrence_rule_date_unique');
            $table->unique('clerk_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clerk_schedule_occurrences');
        Schema::dropIfExists('clerk_schedule_rules');
    }
};

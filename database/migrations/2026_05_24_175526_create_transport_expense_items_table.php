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
        Schema::create('transport_expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_expense_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->date('occurrence_date')->nullable();
            $table->string('destination', 100)->nullable();
            $table->enum('purpose', ['round_trip', 'outbound', 'return', 'direct_home', 'other'])->default('round_trip');
            $table->string('purpose_text', 100)->nullable();
            $table->string('station_from', 100)->nullable();
            $table->string('station_to', 100)->nullable();
            $table->enum('fare_type', ['ic', 'ticket'])->default('ic');
            $table->unsignedInteger('amount')->default(0);
            $table->timestamps();

            $table->index('transport_expense_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_expense_items');
    }
};

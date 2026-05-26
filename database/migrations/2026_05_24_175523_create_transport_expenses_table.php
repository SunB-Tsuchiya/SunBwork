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
        Schema::create('transport_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('department_code')->default(0);
            $table->date('billing_date');
            $table->char('billing_month', 7); // YYYY-MM
            $table->unsignedInteger('total_amount')->default(0);
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->timestamps();

            $table->index(['user_id', 'billing_month']);
            $table->index(['department_id', 'billing_month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transport_expenses');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // 通常DB user_id。クロスDB FKは張らない
            $table->string('action', 64);
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('context')->nullable(); // 得意先名・明細本文を入れない
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_audit_logs');
    }
};

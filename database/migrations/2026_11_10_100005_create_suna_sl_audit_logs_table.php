<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 売上レポートの監査ログ（company_id つき）。取込・許可の変更・Excel出力を記録する。
// context には得意先名・金額などの内容を入れない（件数・IDのみ）。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('suna_sl_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action', 64);
            $table->string('target_type', 64)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('suna_sl_audit_logs');
    }
};

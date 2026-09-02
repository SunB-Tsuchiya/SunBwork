<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 売上分析: 専用の sales DB接続にのみテーブルを作成する。通常DBには一切作成しない。
return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_imports', function (Blueprint $table) {
            $table->id();
            $table->string('department_key', 32)->default('planning');
            $table->string('source_type', 16); // annual / monthly
            $table->smallInteger('source_year');
            $table->tinyInteger('source_month')->nullable();
            $table->unsignedInteger('version');
            $table->string('original_filename', 255);
            $table->char('file_sha256', 64);
            $table->string('status', 20); // validating / failed / completed
            $table->unsignedBigInteger('imported_by'); // 通常DB user_id。クロスDB FKは張らない
            $table->timestamp('imported_at');
            $table->unsignedInteger('order_count')->default(0);
            $table->unsignedInteger('detail_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->json('warnings')->nullable();
            $table->timestamps();

            $table->index(['department_key', 'source_year', 'source_month', 'version'], 'sales_imports_dept_period_version_idx');
            $table->index('file_sha256');
            $table->index(['status', 'imported_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_imports');
    }
};

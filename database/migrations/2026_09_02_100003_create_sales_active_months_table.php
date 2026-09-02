<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_active_months', function (Blueprint $table) {
            $table->id();
            $table->string('department_key', 32);
            $table->smallInteger('sales_year');
            $table->tinyInteger('sales_month');
            $table->foreignId('sales_import_id')->constrained('sales_imports')->onDelete('restrict');
            $table->unsignedBigInteger('activated_by'); // 通常DB user_id。クロスDB FKは張らない
            $table->timestamp('activated_at');
            $table->timestamps();

            $table->unique(['department_key', 'sales_year', 'sales_month']);
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_active_months');
    }
};

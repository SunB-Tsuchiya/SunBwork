<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_import_id')->constrained('sales_imports')->onDelete('cascade');
            $table->string('order_number', 64);
            $table->string('client_name', 255);
            $table->string('product_name', 500);
            $table->date('plate_date');
            $table->smallInteger('sales_year');
            $table->tinyInteger('sales_month');
            $table->decimal('order_amount', 15, 2);
            $table->timestamps();

            $table->unique(['sales_import_id', 'order_number']);
            $table->index(['sales_year', 'sales_month']);
            $table->index('client_name');
            $table->index('plate_date');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_orders');
    }
};

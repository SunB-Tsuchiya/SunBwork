<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_order_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->onDelete('cascade');
            $table->unsignedInteger('source_row_number');
            $table->string('client_name', 255);
            $table->string('product_name', 500);
            $table->string('part_name', 255)->nullable();
            $table->string('category', 255);
            $table->string('item_name', 255);
            $table->string('progress', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->string('format_size', 255);
            $table->decimal('color_count', 10, 2);
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_amount', 15, 2);
            $table->decimal('order_amount_component', 15, 2);
            $table->date('plate_date');
            $table->timestamps();

            $table->index('sales_order_id');
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_order_details');
    }
};

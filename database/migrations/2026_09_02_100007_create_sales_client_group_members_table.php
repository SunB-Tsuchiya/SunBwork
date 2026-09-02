<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sales';

    public function up(): void
    {
        Schema::connection('sales')->create('sales_client_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_client_group_id')->constrained('sales_client_groups')->onDelete('cascade');
            $table->string('client_name', 255)->unique();
            $table->string('normalized_name', 255);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('sales')->dropIfExists('sales_client_group_members');
    }
};

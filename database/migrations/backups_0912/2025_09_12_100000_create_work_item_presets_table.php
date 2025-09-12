<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('work_item_presets')) {
            Schema::create('work_item_presets', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedBigInteger('work_item_type_id')->nullable()->index();
                $table->unsignedBigInteger('size_id')->nullable()->index();
                $table->integer('pages')->nullable();
                $table->integer('quantity')->nullable();
                $table->integer('estimated_minutes')->nullable();
                $table->string('status')->default('preset');
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('work_item_presets');
    }
};

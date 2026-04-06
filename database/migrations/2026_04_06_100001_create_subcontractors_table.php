<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontractors', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // 個人名・会社名どちらでも
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('subcontractor_coordinators', function (Blueprint $table) {
            $table->unsignedBigInteger('subcontractor_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['subcontractor_id', 'user_id']);

            if (DB::getDriverName() !== 'sqlite') {
                $table->foreign('subcontractor_id')->references('id')->on('subcontractors')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontractor_coordinators');
        Schema::dropIfExists('subcontractors');
    }
};

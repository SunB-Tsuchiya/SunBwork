<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('leader_id')->nullable();
            $table->timestamps();
        });

        Schema::create('unit_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
            $table->unique(['unit_id', 'user_id'], 'unit_user_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('unit_members');
        Schema::dropIfExists('units');
    }
};

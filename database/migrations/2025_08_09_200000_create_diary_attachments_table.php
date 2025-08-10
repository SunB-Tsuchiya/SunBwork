<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('diary_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_id')->constrained()->onDelete('cascade');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('diary_attachments');
    }
};

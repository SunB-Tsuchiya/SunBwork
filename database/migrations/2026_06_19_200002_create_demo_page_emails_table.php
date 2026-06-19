<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_page_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('demo_page_id')->constrained('demo_pages')->cascadeOnDelete();
            $table->string('email', 200);
            $table->string('label', 100)->nullable();
            $table->timestamps();
            $table->unique(['demo_page_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_page_emails');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->integer('status')->default(0)->change();
            $table->bigInteger('size')->nullable()->change();
        });
    }

    public function down(): void
    {
        // revert not implemented
    }
};

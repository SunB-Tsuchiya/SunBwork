<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            $table->unsignedBigInteger('cell_note_user_id')->nullable()->after('cell_note');
            $table->foreign('cell_note_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('progress_cells', function (Blueprint $table) {
            $table->dropForeign(['cell_note_user_id']);
            $table->dropColumn('cell_note_user_id');
        });
    }
};

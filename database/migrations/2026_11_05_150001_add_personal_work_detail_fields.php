<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('personal_work_entries', function (Blueprint $table) {
            $table->text('detail_note')->nullable();
            $table->json('file_info')->nullable();
            $table->unsignedInteger('details_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('personal_work_entries', fn (Blueprint $table) => $table->dropColumn(['detail_note', 'file_info', 'details_version']));
    }
};

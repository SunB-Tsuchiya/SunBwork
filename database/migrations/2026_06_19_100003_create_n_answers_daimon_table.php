<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('n_answers_daimon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('n_schools')->cascadeOnDelete();
            $table->string('subject', 5);
            $table->tinyInteger('daimon_index')->unsigned();
            $table->longText('body_html');
            $table->text('body_text');
            $table->timestamps();
            $table->unique(['school_id', 'subject', 'daimon_index']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE n_answers_daimon ADD FULLTEXT INDEX n_answers_daimon_body_text_fulltext (body_text)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('n_answers_daimon');
    }
};

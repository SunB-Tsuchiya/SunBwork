<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE n_exam_daimons DROP INDEX n_exam_daimons_body_text_fulltext');
        DB::statement('ALTER TABLE n_exam_daimons ADD FULLTEXT INDEX n_exam_daimons_body_text_fulltext (body_text) WITH PARSER ngram');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE n_exam_daimons DROP INDEX n_exam_daimons_body_text_fulltext');
        DB::statement('ALTER TABLE n_exam_daimons ADD FULLTEXT INDEX n_exam_daimons_body_text_fulltext (body_text)');
    }
};

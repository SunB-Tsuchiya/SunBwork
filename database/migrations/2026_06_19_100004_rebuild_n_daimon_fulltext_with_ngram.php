<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE n_questions_daimon DROP INDEX n_questions_daimon_body_text_fulltext');
        DB::statement('ALTER TABLE n_questions_daimon ADD FULLTEXT INDEX n_questions_daimon_body_text_fulltext (body_text) WITH PARSER ngram');

        DB::statement('ALTER TABLE n_answers_daimon DROP INDEX n_answers_daimon_body_text_fulltext');
        DB::statement('ALTER TABLE n_answers_daimon ADD FULLTEXT INDEX n_answers_daimon_body_text_fulltext (body_text) WITH PARSER ngram');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE n_questions_daimon DROP INDEX n_questions_daimon_body_text_fulltext');
        DB::statement('ALTER TABLE n_questions_daimon ADD FULLTEXT INDEX n_questions_daimon_body_text_fulltext (body_text)');

        DB::statement('ALTER TABLE n_answers_daimon DROP INDEX n_answers_daimon_body_text_fulltext');
        DB::statement('ALTER TABLE n_answers_daimon ADD FULLTEXT INDEX n_answers_daimon_body_text_fulltext (body_text)');
    }
};

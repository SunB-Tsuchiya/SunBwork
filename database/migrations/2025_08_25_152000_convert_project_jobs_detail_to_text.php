<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration converts existing JSON-shaped `detail` values like {"text":"..."}
     * into plain text (the inner `text` value), then changes the column type to TEXT.
     */
    public function up()
    {
        // If some rows have JSON objects like {"text":"..."}, extract the inner text.
        // Use MySQL JSON functions when available.
        DB::statement("UPDATE project_jobs SET detail = JSON_UNQUOTE(JSON_EXTRACT(detail, '$$.text')) WHERE JSON_VALID(detail) AND JSON_EXTRACT(detail, '$$.text') IS NOT NULL");

        // Alter column to TEXT. Use raw statement to avoid requiring doctrine/dbal.
        DB::statement('ALTER TABLE project_jobs MODIFY detail TEXT');
    }

    /**
     * Reverse the migrations.
     *
     * Wrap plain text back into a JSON object {"text": "..."} and change column back to JSON.
     */
    public function down()
    {
        // Wrap current text into a JSON object
        DB::statement("UPDATE project_jobs SET detail = JSON_OBJECT('text', detail) WHERE detail IS NOT NULL");

        // Change column type back to JSON
        DB::statement('ALTER TABLE project_jobs MODIFY detail JSON');
    }
};

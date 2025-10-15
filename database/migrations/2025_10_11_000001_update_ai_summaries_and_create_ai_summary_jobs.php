<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // extend ai_summaries with operational metadata
        Schema::table('ai_summaries', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_summaries', 'tokens_estimate')) {
                $table->integer('tokens_estimate')->nullable()->after('char_count');
            }
            if (!Schema::hasColumn('ai_summaries', 'model')) {
                $table->string('model')->nullable()->after('tokens_estimate');
            }
            if (!Schema::hasColumn('ai_summaries', 'status')) {
                $table->string('status')->default('ready')->after('model');
            }
            if (!Schema::hasColumn('ai_summaries', 'meta')) {
                $table->json('meta')->nullable()->after('status');
            }
        });

        // create ai_summary_jobs to track summarization runs
        if (!Schema::hasTable('ai_summary_jobs')) {
            Schema::create('ai_summary_jobs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->onDelete('cascade');
                $table->uuid('job_uuid')->nullable();
                $table->string('status')->default('pending'); // pending | running | succeeded | failed
                $table->integer('message_count')->default(0);
                $table->integer('chars_processed')->default(0);
                $table->foreignId('ai_summary_id')->nullable()->constrained('ai_summaries')->nullOnDelete();
                $table->json('api_response')->nullable();
                $table->text('error')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('ai_summary_jobs')) {
            Schema::dropIfExists('ai_summary_jobs');
        }

        Schema::table('ai_summaries', function (Blueprint $table) {
            if (Schema::hasColumn('ai_summaries', 'tokens_estimate')) {
                $table->dropColumn('tokens_estimate');
            }
            if (Schema::hasColumn('ai_summaries', 'model')) {
                $table->dropColumn('model');
            }
            if (Schema::hasColumn('ai_summaries', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('ai_summaries', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};

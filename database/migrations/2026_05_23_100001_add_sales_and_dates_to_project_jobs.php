<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->string('sales_rep', 100)->nullable()->after('detail');
            $table->unsignedBigInteger('sales_rep_id')->nullable()->after('sales_rep');
            $table->date('plate_submission_date')->nullable()->after('sales_rep_id');
            $table->date('plate_down_date')->nullable()->after('plate_submission_date');

            $table->foreign('sales_rep_id')->references('id')->on('prepress_sales_reps')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropForeign(['sales_rep_id']);
            $table->dropColumn(['sales_rep', 'sales_rep_id', 'plate_submission_date', 'plate_down_date']);
        });
    }
};

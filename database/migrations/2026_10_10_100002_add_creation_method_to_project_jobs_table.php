<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->string('creation_method', 20)->nullable()->after('creator_source');
        });

        DB::table('project_jobs')->where('creator_source', 'legacy_leader')
            ->whereNull('creation_method')->update(['creation_method' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropColumn('creation_method');
        });
    }
};

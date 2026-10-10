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
            $table->foreignId('created_by_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->string('creator_source', 20)->nullable()->after('created_by_user_id');
        });

        // Older projects did not record the actor. The current leader is the agreed proxy.
        DB::table('project_jobs')
            ->whereNotNull('user_id')
            ->whereNull('created_by_user_id')
            ->update([
                'created_by_user_id' => DB::raw('user_id'),
                'creator_source' => 'legacy_leader',
            ]);
    }

    public function down(): void
    {
        Schema::table('project_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn('creator_source');
        });
    }
};

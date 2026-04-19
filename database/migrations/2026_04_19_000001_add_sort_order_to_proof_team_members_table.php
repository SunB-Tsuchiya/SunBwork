<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proof_team_members', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('user_id');
        });

        // 既存レコードに連番をセット
        $records = DB::table('proof_team_members')->orderBy('id')->get();
        foreach ($records as $i => $record) {
            DB::table('proof_team_members')
                ->where('id', $record->id)
                ->update(['sort_order' => $i + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('proof_team_members', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};

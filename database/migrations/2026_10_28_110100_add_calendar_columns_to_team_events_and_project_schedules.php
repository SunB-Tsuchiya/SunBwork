<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * チームの予定: 色（color_key。初期値は今の色 indigo）と完了日時。案件の予定: 色（color_key。空なら今の状態色のまま）。
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('team_events', 'color_key')) {
            Schema::table('team_events', fn (Blueprint $t) => $t->string('color_key', 20)->default('indigo')->after('all_day'));
        }
        if (!Schema::hasColumn('team_events', 'completed_at')) {
            Schema::table('team_events', fn (Blueprint $t) => $t->timestamp('completed_at')->nullable()->after('color_key'));
        }
        if (!Schema::hasColumn('project_schedules', 'color_key')) {
            Schema::table('project_schedules', fn (Blueprint $t) => $t->string('color_key', 20)->nullable()->after('color'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_schedules', 'color_key')) {
            Schema::table('project_schedules', fn (Blueprint $t) => $t->dropColumn('color_key'));
        }
        foreach (['completed_at', 'color_key'] as $col) {
            if (Schema::hasColumn('team_events', $col)) {
                Schema::table('team_events', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};

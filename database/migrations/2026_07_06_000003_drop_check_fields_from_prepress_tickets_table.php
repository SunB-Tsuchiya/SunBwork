<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->dropColumn([
                'check_finish_size', 'check_trim_marks', 'check_imposition',
                'check_color_count', 'check_screen_ruling', 'check_n_mark_trap',
                'check_color_correction',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('prepress_tickets', function (Blueprint $table) {
            $table->boolean('check_finish_size')->default(false)->after('sb_delivery_date');
            $table->boolean('check_trim_marks')->default(false)->after('check_finish_size');
            $table->boolean('check_imposition')->default(false)->after('check_trim_marks');
            $table->boolean('check_color_count')->default(false)->after('check_imposition');
            $table->boolean('check_screen_ruling')->default(false)->after('check_color_count');
            $table->boolean('check_n_mark_trap')->default(false)->after('check_screen_ruling');
            $table->boolean('check_color_correction')->default(false)->after('check_n_mark_trap');
        });

        // このロールバックの時点では後続（2026_07_06_000002）の down() がまだ実行されておらず
        // prepress_ticket_stage_checks の「初校」行が残っているため、そこから値を復元する
        $stageChecks = DB::table('prepress_ticket_stage_checks')->where('stage', '初校')->get();
        foreach ($stageChecks as $sc) {
            DB::table('prepress_tickets')->where('id', $sc->prepress_ticket_id)->update([
                'check_finish_size'      => $sc->check_finish_size,
                'check_trim_marks'       => $sc->check_trim_marks,
                'check_imposition'       => $sc->check_imposition,
                'check_color_count'      => $sc->check_color_count,
                'check_screen_ruling'    => $sc->check_screen_ruling,
                'check_n_mark_trap'      => $sc->check_n_mark_trap,
                'check_color_correction' => $sc->check_color_correction,
            ]);
        }
    }
};

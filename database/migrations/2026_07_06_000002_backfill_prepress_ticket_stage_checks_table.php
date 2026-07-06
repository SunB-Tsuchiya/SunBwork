<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $tickets = DB::table('prepress_tickets')->get([
            'id', 'check_finish_size', 'check_trim_marks', 'check_imposition',
            'check_color_count', 'check_screen_ruling', 'check_n_mark_trap', 'check_color_correction',
        ]);

        $now = now();

        foreach ($tickets as $ticket) {
            DB::table('prepress_ticket_stage_checks')->insert([
                'prepress_ticket_id'     => $ticket->id,
                'stage'                  => '初校',
                'check_finish_size'      => $ticket->check_finish_size,
                'check_trim_marks'       => $ticket->check_trim_marks,
                'check_imposition'       => $ticket->check_imposition,
                'check_color_count'      => $ticket->check_color_count,
                'check_screen_ruling'    => $ticket->check_screen_ruling,
                'check_n_mark_trap'      => $ticket->check_n_mark_trap,
                'check_color_correction' => $ticket->check_color_correction,
                'created_at'             => $now,
                'updated_at'             => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('prepress_ticket_stage_checks')->where('stage', '初校')->delete();
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrepressTicketStageCheck extends Model
{
    const STAGE_FIRST  = '初校';
    const STAGE_SECOND = '再校';
    const STAGE_THIRD  = '三校';
    const STAGE_FINAL  = '下版';

    const STAGES = [self::STAGE_FIRST, self::STAGE_SECOND, self::STAGE_THIRD, self::STAGE_FINAL];

    protected $fillable = [
        'prepress_ticket_id',
        'stage',
        'check_finish_size',
        'check_trim_marks',
        'check_imposition',
        'check_color_count',
        'check_screen_ruling',
        'check_n_mark_trap',
        'check_color_correction',
        'user_id',
    ];

    protected $casts = [
        'check_finish_size'      => 'boolean',
        'check_trim_marks'       => 'boolean',
        'check_imposition'       => 'boolean',
        'check_color_count'      => 'boolean',
        'check_screen_ruling'    => 'boolean',
        'check_n_mark_trap'      => 'boolean',
        'check_color_correction' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(PrepressTicket::class, 'prepress_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

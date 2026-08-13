<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 進行表スケジュールのコメント（カレンダー上のメモ）。
 *
 * 実テーブルのカラムは `comment` / `comment_date` だが、Controller と Vue は
 * 一貫して `body` / `date` というキーで扱っている。過去、モデルの fillable と
 * Controller が存在しないカラム（body / date / metadata）を直接書こうとしていたため
 * INSERT が Unknown column で失敗し、コメント投稿がまったく機能していなかった。
 *
 * Event モデルの start <-> starts_at と同じ方式で、アクセサ／ミューテタにより
 * 入出力キー（body / date）と実カラム（comment / comment_date）を対応させる。
 */
class ProjectScheduleComment extends Model
{
    use HasFactory;

    // body / date はミューテタ経由で comment / comment_date に格納される
    protected $fillable = [
        'project_schedule_id',
        'user_id',
        'comment',
        'comment_date',
        'body',
        'date',
    ];

    protected $casts = [
        'comment_date' => 'date:Y-m-d',
    ];

    // JSON / Inertia へ body・date を含めて返す（フロントはこのキーを参照する）
    protected $appends = ['body', 'date'];

    // body <-> comment
    public function getBodyAttribute()
    {
        return $this->attributes['comment'] ?? null;
    }

    public function setBodyAttribute($value)
    {
        $this->attributes['comment'] = $value;
    }

    // date <-> comment_date
    public function getDateAttribute()
    {
        $value = $this->comment_date ?? null;
        if (! $value) {
            return null;
        }

        return method_exists($value, 'format') ? $value->format('Y-m-d') : (string) $value;
    }

    public function setDateAttribute($value)
    {
        $this->attributes['comment_date'] = $value;
    }

    public function schedule()
    {
        return $this->belongsTo(ProjectSchedule::class, 'project_schedule_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

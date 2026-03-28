<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_monthly_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('year_month', 7);         // 'YYYY-MM'
            $table->json('schedule');               // {"01": 2, "15": 3, ...} key=DD, value=worktype_id
            $table->timestamps();

            $table->unique(['user_id', 'year_month']);
        });

        // user_daily_worktypes の既存データを移行
        if (Schema::hasTable('user_daily_worktypes')) {
            $rows = DB::table('user_daily_worktypes')->get();
            $byUserMonth = [];
            foreach ($rows as $row) {
                if (! $row->worktype_id) {
                    continue;
                }
                $ym = substr($row->date, 0, 7); // 'YYYY-MM'
                $dd = substr($row->date, 8, 2); // 'DD'
                $byUserMonth[$row->user_id][$ym][$dd] = $row->worktype_id;
            }
            foreach ($byUserMonth as $userId => $months) {
                foreach ($months as $ym => $schedule) {
                    DB::table('user_monthly_schedules')->insert([
                        'user_id'    => $userId,
                        'year_month' => $ym,
                        'schedule'   => json_encode($schedule),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::dropIfExists('user_daily_worktypes');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_monthly_schedules');

        Schema::create('user_daily_worktypes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('worktype_id')->nullable()->constrained('worktypes')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'date']);
        });
    }
};

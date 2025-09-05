<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class z_SampleDiariesSeeder extends Seeder
{
    public function run()
    {
        // Emails from sample_users22.csv; only insert for users that exist
        $emails = [
            'yamada@test.com',
            'itoake@test.com',
            'suzuken@test.com',
            'yamaon@test.com',
            'iwaki@test.com',
            'genji@test.com',
            'ito@test.com',
            'suzuki@test.com',
            'yayama@test.com',
            "sato_k@test.com",
            "takahashi_m@test.com",
            "tanaka_y@test.com",
            "watanabe_a@test.com",
            "ito_k@test.com",
            "nakamura_y@test.com",
            "kobayashi_r@test.com",
            "kato_n@test.com",
            "yoshida_m@test.com",
            "yamaguchi_n@test.com",
            "noguchi_r@test.com",
            "taniguchi_m@test.com",
            "matsumoto_y@test.com",
            "sasaki_n@test.com",
            "ishikawa_a@test.com",
            "ueda_m@test.com",
            "ochiai_r@test.com",
            "nakajima_a@test.com",
            "inoue_k@test.com",
            "hashimoto_t@test.com",
            "okamoto_r@test.com",
        ];

        $users = DB::table('users')->whereIn('email', $emails)->pluck('id', 'email')->toArray();
        if (empty($users)) {
            $this->command->info('No sample users found, skipping diaries seeder.');
            return;
        }

        // Generate dates for July and August (current year) for weekdays only
        $year = Carbon::now()->year;
        $dates = [];
        for ($month = 7; $month <= 9; $month++) {
            $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $dt = Carbon::create($year, $month, $d);
                // weekday: 1 (Mon) - 5 (Fri)
                if ($dt->dayOfWeekIso >= 1 && $dt->dayOfWeekIso <= 5) {
                    $dates[] = $dt->toDateString();
                }
            }
        }

        // Generate roughly 100 character diary content (Japanese-ish placeholder)
        $sampleText = "今日は重要な会議があり、プロジェクトの進捗について詳細に議論しました。いくつかの課題が見つかり、対応方針を決定しました。引き続きフォローします。";

        $now = Carbon::now();
        foreach ($users as $email => $userId) {
            foreach ($dates as $date) {
                $diaryDate = Carbon::parse($date)->setTime(9, 0, 0);
                // Upsert to avoid duplicates; ensure `date` column is provided (unique constraint)
                DB::table('diaries')->updateOrInsert([
                    'user_id' => $userId,
                    'date' => $date,
                ], [
                    'content' => $sampleText,
                    'created_at' => $diaryDate,
                    'updated_at' => $now,
                ]);
            }
            $this->command->info("Inserted diaries for user {$email} (user_id={$userId})");
        }
    }
}

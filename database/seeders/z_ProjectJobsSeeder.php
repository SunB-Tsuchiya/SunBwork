<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class z_ProjectJobsSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        $clients = DB::table('clients')->get();
        if ($clients->isEmpty()) {
            $this->command->info('No clients found, skipping project jobs seeder.');
            return;
        }

        // pick a fallback user if users exist
        $fallbackUser = DB::table('users')->select('id')->first();
        $userId = $fallbackUser->id ?? null;

        foreach ($clients as $client) {
            for ($i = 1; $i <= 3; $i++) {
                $title = "{$client->name} - サンプル案件 {$i}";
                $detail = "サンプルのプロジェクトです（クライアント: {$client->name}）。詳細はダミーです。";

                DB::table('project_jobs')->updateOrInsert([
                    'client_id' => $client->id,
                    'title' => $title,
                ], [
                    'detail' => $detail,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->command->info("Upserted 3 project_jobs for client: {$client->name}");
        }
    }
}

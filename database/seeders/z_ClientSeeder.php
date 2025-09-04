<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class z_ClientSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();
        $clients = [
            ['name' => '朝日デザイン株式会社', 'notes' => '主要クライアントの一つ。'],
            ['name' => '富士プロダクション', 'notes' => '外注案件中心。'],
            ['name' => 'みどり商事', 'notes' => '小規模案件が多い。'],
        ];

        foreach ($clients as $c) {
            // ensure the default company exists and get its id
            $company = DB::table('companies')->where('name', '株式会社サン・ブレーン')->first();
            if (!$company) {
                $companyId = DB::table('companies')->insertGetId([
                    'name' => '株式会社サン・ブレーン',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $companyId = $company->id;
            }

            DB::table('clients')->updateOrInsert([
                'name' => $c['name']
            ], [
                'company_id' => $companyId,
                'notes' => $c['notes'] ?? null,
                'updated_at' => $now,
                'created_at' => $now,
            ]);
            $this->command->info("Upserted client: {$c['name']}");
        }
    }
}

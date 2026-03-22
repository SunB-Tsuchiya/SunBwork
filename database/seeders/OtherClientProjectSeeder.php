<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * 「その他」クライアント・案件のSeeder
 *
 * EventController が firstOrCreate で実行時生成していた「その他」レコードを
 * 事前にSeederで確保する。company_id=null はシステム共通（全社共有）を意味する。
 *
 * 参照コード: app/Http/Controllers/EventController.php（otherClientId/otherProjectId）
 *             resources/js/Pages/Events/Create_Job.vue（other_client_id/other_project_id props）
 *             resources/js/Pages/Coordinator/ProjectJobs/JobAssign/AssignmentForm_user.vue（otherClientId/otherProjectId props）
 */
class OtherClientProjectSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // 「その他」クライアント（company_id=null で全社共通）
        $otherClient = DB::table('clients')
            ->where('name', 'その他')
            ->whereNull('company_id')
            ->first();

        if (!$otherClient) {
            $otherClientId = DB::table('clients')->insertGetId([
                'name'       => 'その他',
                'company_id' => null,
                'notes'      => 'デフォルト「その他」クライアント（システム共通）',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->command->info('Created client: その他');
        } else {
            $otherClientId = $otherClient->id;
            $this->command->info('Client その他 already exists (id=' . $otherClientId . ')');
        }

        // 「その他」案件（上記クライアントに紐付く、user_id=null）
        $otherProject = DB::table('project_jobs')
            ->where('title', 'その他')
            ->where('client_id', $otherClientId)
            ->first();

        if (!$otherProject) {
            DB::table('project_jobs')->insert([
                'title'      => 'その他',
                'client_id'  => $otherClientId,
                'user_id'    => null,
                'detail'     => 'デフォルト「その他」案件（システム共通）',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->command->info('Created project_job: その他');
        } else {
            $this->command->info('project_job その他 already exists (id=' . $otherProject->id . ')');
        }
    }
}

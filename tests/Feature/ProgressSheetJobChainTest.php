<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ProjectJob;
use App\Models\ProjectJobAssignment;
use App\Models\ProgressSheet;
use App\Models\ProgressRow;
use App\Models\ProgressCell;
use Illuminate\Support\Facades\DB;

/**
 * テスト: 進行管理表から作成したジョブを「続きとして連動」し、
 * 日をまたいで2つ作成 → 完了時に進行表・チェーンが正しく記録されるか
 */
class ProgressSheetJobChainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * テスト用の共通セットアップ
     * - user, coordinator, client, projectJob, progressSheet, progressRow を作成
     */
    private function setupBase(): array
    {
        // ユーザー作成（worker）
        $user = User::factory()->create([
            'name'      => 'テストユーザー',
            'user_role' => 'user',
        ]);

        // coordinatorユーザー（案件オーナー）
        $coordinator = User::factory()->create([
            'name'      => 'テストコーディネーター',
            'user_role' => 'coordinator',
        ]);

        // クライアント作成（名前だけで最低限OK）
        $client = DB::table('clients')->insertGetId([
            'name'       => 'テストクライアント',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 案件作成
        $projectJob = ProjectJob::create([
            'jobcode' => 'TEST-001',
            'title'   => 'テスト案件',
            'user_id' => $coordinator->id,
            'client_id' => $client,
        ]);

        // 進行管理表作成
        $sheet = ProgressSheet::create([
            'project_job_id' => $projectJob->id,
            'name'           => 'テスト進行表',
            'column_config'  => ['col_1' => '工程A'],
            'created_by'     => $coordinator->id,
        ]);

        // 行を追加
        $row = ProgressRow::create([
            'sheet_id' => $sheet->id,
            'label'    => 'デザイン作業',
            'order'    => 1,
        ]);

        return compact('user', 'coordinator', 'projectJob', 'sheet', 'row');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // テスト①: ProgressCell に紐づくジョブ（元ジョブ）が作成されること
    // ─────────────────────────────────────────────────────────────────────────
    /** @test */
    public function progress_cell_links_to_assignment_when_job_created()
    {
        ['user' => $user, 'projectJob' => $projectJob, 'sheet' => $sheet, 'row' => $row] = $this->setupBase();

        $this->actingAs($user);

        // 元ジョブを作成（進行表セルに紐づく）
        $response = $this->post(
            route('user.project_jobs.assignments.store', ['projectJob' => $projectJob->id]),
            [
                'assignments' => [[
                    'title'               => '1日目のデザイン作業',
                    'detail'              => '初日の作業',
                    'desired_start_date'  => '2026-04-04',
                    '_progress_sheet_id'  => $sheet->id,
                    '_row_id'             => $row->id,
                    '_col_key'            => 'col_1',
                ]],
            ]
        );

        $response->assertStatus(302); // Inertia は redirect

        // ジョブが作成されていること
        $this->assertDatabaseHas('project_job_assignments', [
            'project_job_id' => $projectJob->id,
            'user_id'        => $user->id,
            'title'          => '1日目のデザイン作業',
        ]);

        // ProgressCell が正しくリンクされていること
        $assignment = ProjectJobAssignment::where('title', '1日目のデザイン作業')->first();
        $this->assertNotNull($assignment, '元ジョブが作成されていること');

        $cell = ProgressCell::where('row_id', $row->id)->where('col_key', 'col_1')->first();
        $this->assertNotNull($cell, 'ProgressCell が作成されていること');
        $this->assertEquals($assignment->id, $cell->assignment_id, 'ProgressCell が元ジョブに紐づいていること');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // テスト②: source_assignment_id が設定された「続きジョブ」が作成されること
    // ─────────────────────────────────────────────────────────────────────────
    /** @test */
    public function continuation_job_stores_source_assignment_id()
    {
        ['user' => $user, 'projectJob' => $projectJob, 'sheet' => $sheet, 'row' => $row] = $this->setupBase();

        $this->actingAs($user);

        // ① 元ジョブ作成
        $original = ProjectJobAssignment::create([
            'project_job_id' => $projectJob->id,
            'user_id'        => $user->id,
            'sender_id'      => $user->id,
            'title'          => '1日目のデザイン',
            'desired_end_date' => '2026-04-04',
            'read_at'        => now(),
        ]);

        // ProgressCell を元ジョブに紐づける
        ProgressCell::create([
            'row_id'        => $row->id,
            'col_key'       => 'col_1',
            'assignment_id' => $original->id,
        ]);

        // ② 続きジョブを作成（source_assignment_id = original.id）
        $response = $this->post(
            route('user.project_jobs.assignments.store', ['projectJob' => $projectJob->id]),
            [
                'assignments' => [[
                    'title'                => '2日目のデザイン作業（続き）',
                    'detail'               => '翌日の続き',
                    'desired_start_date'   => '2026-04-05',
                    'source_assignment_id' => $original->id,
                ]],
            ]
        );

        $response->assertStatus(302);

        // 続きジョブに source_assignment_id が保存されていること
        $continuation = ProjectJobAssignment::where('title', '2日目のデザイン作業（続き）')->first();
        $this->assertNotNull($continuation, '続きジョブが作成されていること');
        $this->assertEquals($original->id, $continuation->source_assignment_id, 'source_assignment_id が正しく保存されていること');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // テスト③: 続きジョブを完了にすると元ジョブも完了になる（カスケード）
    // ─────────────────────────────────────────────────────────────────────────
    /** @test */
    public function completing_continuation_job_also_completes_ancestor()
    {
        ['user' => $user, 'projectJob' => $projectJob] = $this->setupBase();

        $this->actingAs($user);

        // 元ジョブ（未完了）
        $original = ProjectJobAssignment::create([
            'project_job_id' => $projectJob->id,
            'user_id'        => $user->id,
            'sender_id'      => $user->id,
            'title'          => '1日目のデザイン',
            'desired_end_date' => '2026-04-04',
            'completed'      => false,
            'read_at'        => now(),
        ]);

        // 続きジョブ（未完了）
        $continuation = ProjectJobAssignment::create([
            'project_job_id'      => $projectJob->id,
            'user_id'             => $user->id,
            'sender_id'           => $user->id,
            'title'               => '2日目のデザイン（続き）',
            'desired_end_date'    => '2026-04-05',
            'source_assignment_id' => $original->id,
            'completed'           => false,
            'read_at'             => now(),
        ]);

        // 続きジョブを完了にする（completeAssignment エンドポイント）
        $response = $this->post(
            route('myjobbox.assignments.complete', ['assignment' => $continuation->id]),
            [],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // ① 続きジョブが完了していること
        $this->assertDatabaseHas('project_job_assignments', [
            'id'        => $continuation->id,
            'completed' => true,
        ]);

        // ② 元ジョブも完了していること（カスケード）
        $this->assertDatabaseHas('project_job_assignments', [
            'id'        => $original->id,
            'completed' => true,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // テスト④: 3段チェーン（A→B→C）でCを完了するとA・B両方が完了になる
    // ─────────────────────────────────────────────────────────────────────────
    /** @test */
    public function three_level_chain_cascade_on_complete()
    {
        ['user' => $user, 'projectJob' => $projectJob] = $this->setupBase();

        $this->actingAs($user);

        $jobA = ProjectJobAssignment::create([
            'project_job_id' => $projectJob->id,
            'user_id'        => $user->id,
            'sender_id'      => $user->id,
            'title'          => 'ジョブA（1日目）',
            'desired_end_date' => '2026-04-04',
            'completed'      => false,
            'read_at'        => now(),
        ]);

        $jobB = ProjectJobAssignment::create([
            'project_job_id'      => $projectJob->id,
            'user_id'             => $user->id,
            'sender_id'           => $user->id,
            'title'               => 'ジョブB（2日目 続き）',
            'desired_end_date'    => '2026-04-05',
            'source_assignment_id' => $jobA->id,
            'completed'           => false,
            'read_at'             => now(),
        ]);

        $jobC = ProjectJobAssignment::create([
            'project_job_id'      => $projectJob->id,
            'user_id'             => $user->id,
            'sender_id'           => $user->id,
            'title'               => 'ジョブC（3日目 続き）',
            'desired_end_date'    => '2026-04-06',
            'source_assignment_id' => $jobB->id,
            'completed'           => false,
            'read_at'             => now(),
        ]);

        // C を完了
        $response = $this->post(
            route('myjobbox.assignments.complete', ['assignment' => $jobC->id]),
            [],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        // A, B, C すべて完了
        foreach ([$jobA->id, $jobB->id, $jobC->id] as $id) {
            $this->assertDatabaseHas('project_job_assignments', [
                'id'        => $id,
                'completed' => true,
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // テスト⑤: chainAssignments API がチェーン全体を返すこと
    // ─────────────────────────────────────────────────────────────────────────
    /** @test */
    public function chain_api_returns_all_chain_members()
    {
        ['user' => $user, 'projectJob' => $projectJob] = $this->setupBase();

        $this->actingAs($user);

        $jobA = ProjectJobAssignment::create([
            'project_job_id' => $projectJob->id,
            'user_id'        => $user->id,
            'sender_id'      => $user->id,
            'title'          => 'チェーンテストA',
            'desired_end_date' => '2026-04-04',
            'read_at'        => now(),
        ]);

        $jobB = ProjectJobAssignment::create([
            'project_job_id'      => $projectJob->id,
            'user_id'             => $user->id,
            'sender_id'           => $user->id,
            'title'               => 'チェーンテストB（続き）',
            'desired_end_date'    => '2026-04-05',
            'source_assignment_id' => $jobA->id,
            'read_at'             => now(),
        ]);

        // chainAssignments API を叩く（jobB から呼ぶ）
        $response = $this->get(
            route('user.myjobbox.assignments.chain', ['assignment' => $jobB->id]),
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('chain', $data);
        $chainIds = collect($data['chain'])->pluck('id')->toArray();

        // A と B の両方が含まれていること
        $this->assertContains($jobA->id, $chainIds, 'チェーンにジョブAが含まれていること');
        $this->assertContains($jobB->id, $chainIds, 'チェーンにジョブBが含まれていること');
        $this->assertCount(2, $chainIds, 'チェーンが2件であること');

        // current_id が jobB であること
        $this->assertEquals($jobB->id, $data['current_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // テスト⑥（統合）: 進行表から作成→1日目完了→2日目続き作成→2日目完了
    //   → ProgressCell のリンクが維持され、元ジョブも完了になること
    // ─────────────────────────────────────────────────────────────────────────
    /** @test */
    public function full_flow_progress_sheet_day1_day2_chain_and_complete()
    {
        ['user' => $user, 'projectJob' => $projectJob, 'sheet' => $sheet, 'row' => $row] = $this->setupBase();

        $this->actingAs($user);

        // ─── Day1: 進行表セルから1日目のジョブを登録 ───
        $this->post(
            route('user.project_jobs.assignments.store', ['projectJob' => $projectJob->id]),
            [
                'assignments' => [[
                    'title'              => '1日目のデザイン作業',
                    'detail'             => '下書き作成',
                    'desired_start_date' => '2026-04-04',
                    '_progress_sheet_id' => $sheet->id,
                    '_row_id'            => $row->id,
                    '_col_key'           => 'col_1',
                ]],
            ]
        )->assertStatus(302);

        $day1 = ProjectJobAssignment::where('title', '1日目のデザイン作業')->first();
        $this->assertNotNull($day1, '1日目ジョブが作成されていること');

        // ProgressCell が1日目ジョブに紐づいていることを確認
        $cell = ProgressCell::where('row_id', $row->id)->where('col_key', 'col_1')->first();
        $this->assertNotNull($cell, 'ProgressCellが作成されていること');
        $this->assertEquals($day1->id, $cell->assignment_id, 'ProgressCellが1日目ジョブに紐づいていること');

        // ─── Day2: 続きジョブを作成（source_assignment_id = day1.id） ───
        $this->post(
            route('user.project_jobs.assignments.store', ['projectJob' => $projectJob->id]),
            [
                'assignments' => [[
                    'title'                => '2日目のデザイン作業（続き）',
                    'detail'               => '仕上げ',
                    'desired_start_date'   => '2026-04-05',
                    'source_assignment_id' => $day1->id,
                    // ProgressCellはDay1のまま（上書きしない）
                ]],
            ]
        )->assertStatus(302);

        $day2 = ProjectJobAssignment::where('title', '2日目のデザイン作業（続き）')->first();
        $this->assertNotNull($day2, '2日目ジョブが作成されていること');
        $this->assertEquals($day1->id, $day2->source_assignment_id, 'source_assignment_id が正しく設定されていること');

        // ─── ProgressCell は1日目のままであること（上書きされていない） ───
        $cell->refresh();
        $this->assertEquals($day1->id, $cell->assignment_id, 'ProgressCellは1日目ジョブのまま維持されていること');

        // ─── 2日目ジョブを完了にする ───
        $this->post(
            route('myjobbox.assignments.complete', ['assignment' => $day2->id]),
            [],
            ['Accept' => 'application/json']
        )->assertStatus(200)->assertJson(['success' => true]);

        // ─── 2日目が完了していること ───
        $this->assertDatabaseHas('project_job_assignments', [
            'id'        => $day2->id,
            'completed' => true,
        ]);

        // ─── 1日目（元ジョブ）もカスケードで完了していること ───
        $this->assertDatabaseHas('project_job_assignments', [
            'id'        => $day1->id,
            'completed' => true,
        ]);

        // ─── チェーンAPIでA・B両方を確認 ───
        $chainResponse = $this->get(
            route('user.myjobbox.assignments.chain', ['assignment' => $day2->id]),
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $chainIds = collect($chainResponse->json('chain'))->pluck('id')->toArray();
        $this->assertContains($day1->id, $chainIds, 'チェーンに1日目ジョブが含まれること');
        $this->assertContains($day2->id, $chainIds, 'チェーンに2日目ジョブが含まれること');

        // ─── ProgressCell が壊れていないこと（最終確認） ───
        $cell->refresh();
        $this->assertEquals($day1->id, $cell->assignment_id, 'ProgressCell は完了後も1日目ジョブのまま維持されていること');
    }
}

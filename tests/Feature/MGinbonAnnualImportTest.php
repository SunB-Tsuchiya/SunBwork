<?php

namespace Tests\Feature;

use App\Http\Controllers\Coordinator\MGinbonAnnualImportController;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MGinbonAnnualImportTest extends TestCase
{
    use DatabaseTransactions;
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.stores.mginbon_preview', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        $this->databasePath = tempnam(sys_get_temp_dir(), 'mginbon_annual_test_');
        config()->set('database.connections.mginbon', [
            'driver' => 'sqlite',
            'database' => $this->databasePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge('mginbon');
        Artisan::call('migrate', [
            '--database' => 'mginbon',
            '--path' => 'database/migrations/mginbon',
            '--realpath' => false,
            '--force' => true,
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('mginbon');
        @unlink($this->databasePath);
        parent::tearDown();
    }

    public function test_empty_ledger_redirects_to_annual_import(): void
    {
        $user = User::factory()->create(['user_role' => 'coordinator']);

        $this->actingAs($user)
            ->get(route('coordinator.mginbon.index'))
            ->assertRedirect(route('coordinator.mginbon.annual_import.create'));
    }

    public function test_confirmed_preview_creates_project_units_and_ordered_stages(): void
    {
        $token = (string) Str::uuid();
        $companyId = DB::table('companies')->insertGetId(['name' => 'MGinbon Test', 'code' => 'mginbon-test-'.Str::uuid(), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $user = User::factory()->create(['company_id' => $companyId, 'user_role' => 'coordinator']);
        $rows = [
            $this->row(2, '101', '1721', '①', '青山学院中等部', '第1回'),
            $this->row(3, '102', '3331', '', '青山学院横浜英和中学校', 'A', ['注記を確認']),
        ];
        Cache::store('mginbon_preview')->put('mginbon:annual-import:'.$user->id.':'.$token, [
            'year' => 2098,
            'project_link_mode' => 'create',
            'project_job_id' => null,
            'filename' => '2098年度対象校.xlsx',
            'rows' => $rows,
        ], now()->addMinutes(30));

        $request = Request::create('/coordinator/mginbon/annual-import', 'POST', [
            'token' => $token,
            'year' => 2098,
            'project_link_mode' => 'create',
            'project_job_id' => null,
            'rows' => [
                [...$rows[0], 'confirmed' => false],
                [...$rows[1], 'school_name' => '青山学院横浜英和中学校（確認済）', 'confirmed' => true],
            ],
        ]);

        $request->setUserResolver(fn () => $user);
        $response = app(MGinbonAnnualImportController::class)->store($request);

        $this->assertTrue($response->isRedirect(route('coordinator.mginbon.index', ['year' => 2098])));
        $db = DB::connection('mginbon');
        $project = $db->table('mginbon_projects')->where('year', 2098)->first();
        $this->assertNotNull($project);
        $this->assertSame(2, $db->table('mginbon_production_units')->where('mginbon_project_id', $project->id)->count());
        $this->assertSame(14, $db->table('mginbon_stage_definitions')->where('mginbon_project_id', $project->id)->count());
        $this->assertSame(13, $db->table('mginbon_value_lists')->where('mginbon_project_id', $project->id)->count());
        $answerListId = $db->table('mginbon_value_lists')->where('mginbon_project_id', $project->id)->where('code', 'answer_availability')->value('id');
        $this->assertSame(['解答あり', '解答なし'], $db->table('mginbon_value_list_items')->where('mginbon_value_list_id', $answerListId)->orderBy('sort_order')->pluck('value')->all());
        $this->assertSame(
            ['text_input', 'drawing', 'initial_operation', 'initial_check', 'initial_text_proof', 'reproof_scan_check', 'reproof_text_proof', 'reproof_operation', 'client_return_operation', 'third_proof', 'third_operation', 'fourth_operation', 'fourth_proof', 'fifth_operation'],
            $db->table('mginbon_stage_definitions')->where('mginbon_project_id', $project->id)->orderBy('sort_order')->pluck('code')->all()
        );
        $unit = $db->table('mginbon_production_units')->where('source_row_number', 3)->first();
        $this->assertSame('青山学院横浜英和中学校（確認済）', $unit->display_name);
        $this->assertSame(['注記を確認'], json_decode($unit->import_warnings, true));
        $this->assertSame('青山学院横浜英和中学校', json_decode($unit->source_data, true)['school_name']);
        $this->assertFalse(Cache::store('mginbon_preview')->has('mginbon:annual-import:'.$user->id.':'.$token));
    }

    public function test_default_values_are_not_restored_after_an_item_is_renamed(): void
    {
        $projectId = DB::connection('mginbon')->table('mginbon_projects')->insertGetId([
            'year' => 2097, 'name' => '2097年 中学入試問題集', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $project = \App\Models\MGinbon\MGinbonProject::findOrFail($projectId);
        $service = app(\App\Services\MGinbon\MGinbonValueListDefaults::class);
        $service->ensureForProject($project, 99);

        $listId = DB::connection('mginbon')->table('mginbon_value_lists')->where('mginbon_project_id', $projectId)->where('code', 'checker')->value('id');
        DB::connection('mginbon')->table('mginbon_value_list_items')->where('mginbon_value_list_id', $listId)->where('value', '鈴木')->update(['value' => '鈴木（修正）']);
        $service->ensureForProject($project, 99);

        $values = DB::connection('mginbon')->table('mginbon_value_list_items')->where('mginbon_value_list_id', $listId)->orderBy('sort_order')->pluck('value')->all();
        $this->assertSame(['鈴木（修正）', '横田', '土屋'], $values);
    }

    public function test_value_lists_can_be_copied_between_years(): void
    {
        $db = DB::connection('mginbon');
        $sourceId = $db->table('mginbon_projects')->insertGetId(['project_job_id' => 1, 'year' => 2095, 'name' => '2095年', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $targetId = $db->table('mginbon_projects')->insertGetId(['project_job_id' => 1, 'year' => 2096, 'name' => '2096年', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $service = app(\App\Services\MGinbon\MGinbonValueListDefaults::class);
        $service->ensureForProject(\App\Models\MGinbon\MGinbonProject::findOrFail($sourceId), 99);
        $service->ensureForProject(\App\Models\MGinbon\MGinbonProject::findOrFail($targetId), 99);
        $sourceListId = $db->table('mginbon_value_lists')->where('mginbon_project_id', $sourceId)->where('code', 'checker')->value('id');
        $db->table('mginbon_value_list_items')->where('mginbon_value_list_id', $sourceListId)->where('value', '鈴木')->update(['value' => '派遣A', 'is_active' => false]);

        $request = Request::create('/coordinator/mginbon/value-masters/copy', 'POST', ['source_project_id' => $sourceId, 'target_project_id' => $targetId]);
        app(\App\Http\Controllers\Coordinator\MGinbonValueMasterController::class)->copy($request, app(\App\Services\MGinbon\MGinbonProjectAccess::class));
        $targetListId = $db->table('mginbon_value_lists')->where('mginbon_project_id', $targetId)->where('code', 'checker')->value('id');
        $copied = $db->table('mginbon_value_list_items')->where('mginbon_value_list_id', $targetListId)->orderBy('sort_order')->get(['value', 'is_active']);
        $this->assertSame(['派遣A', '横田', '土屋'], $copied->pluck('value')->all());
        $this->assertFalse((bool) $copied->first()->is_active);
        $this->assertSame(13, $db->table('mginbon_value_lists')->where('mginbon_project_id', $targetId)->count());
    }

    private function row(int $number, string $mCode, string $nCode, string $alpha, string $school, string $exam, array $warnings = []): array
    {
        return [
            'source_row_number' => $number,
            'mikuni_code' => $mCode,
            'n_code' => $nCode,
            'alpha_group' => $alpha,
            'school_name' => $school,
            'exam_session' => $exam,
            'warnings' => $warnings,
            'raw' => [
                'mikuni_code' => $mCode,
                'n_code' => $nCode,
                'alpha_group' => $alpha,
                'school_name' => $school,
                'exam_session' => $exam,
            ],
        ];
    }
}

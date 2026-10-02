<?php

namespace Tests\Feature;

use App\Models\MGinbon\MGinbonProject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MGinbonSavedSearchTest extends TestCase
{
    use DatabaseTransactions;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'mginbon_search_test_');
        config()->set('database.connections.mginbon', [
            'driver' => 'sqlite', 'database' => $this->databasePath, 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge('mginbon');
        Artisan::call('migrate', ['--database' => 'mginbon', '--path' => 'database/migrations/mginbon', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('mginbon');
        @unlink($this->databasePath);
        parent::tearDown();
    }

    public function test_user_can_save_list_restore_history_and_delete_personal_search(): void
    {
        $user = User::factory()->create(['user_role' => 'coordinator']);
        $project = MGinbonProject::create(['year' => 2097, 'name' => '2097年', 'status' => 'active']);
        $criteria = ['version' => 2, 'requests' => [['conditions' => [['field' => 'school_name', 'value' => '青山']]]]];

        $created = $this->actingAs($user)->postJson(route('coordinator.mginbon.saved_searches.store'), [
            'project_id' => $project->id, 'name' => '青山検索', 'scope' => 'personal',
            'criteria' => $criteria, 'display_mode' => 'table',
        ])->assertCreated()->json('saved_search');

        $this->actingAs($user)->getJson(route('coordinator.mginbon.saved_searches.index', ['project_id' => $project->id]))
            ->assertOk()->assertJsonPath('saved_searches.0.name', '青山検索')->assertJsonPath('saved_searches.0.can_edit', true);

        foreach ([3, 5] as $count) {
            $this->actingAs($user)->postJson(route('coordinator.mginbon.search_histories.store'), [
                'project_id' => $project->id, 'criteria' => $criteria, 'result_count' => $count, 'display_mode' => 'table',
            ])->assertCreated();
        }
        $this->assertDatabaseCount('mginbon_search_histories', 1, 'mginbon');
        $this->assertDatabaseHas('mginbon_search_histories', ['result_count' => 5], 'mginbon');
        $this->actingAs($user)->getJson(route('coordinator.mginbon.search_histories.index', ['project_id' => $project->id]))
            ->assertOk()->assertJsonPath('histories.0.result_count', 5);

        $this->actingAs($user)->deleteJson(route('coordinator.mginbon.saved_searches.destroy', ['savedSearch' => $created['id']]))->assertNoContent();
        $this->assertDatabaseCount('mginbon_saved_searches', 0, 'mginbon');
    }

    public function test_leader_cannot_create_shared_search(): void
    {
        $user = User::factory()->create(['user_role' => 'leader']);
        $project = MGinbonProject::create(['year' => 2096, 'name' => '2096年', 'status' => 'active']);

        $this->actingAs($user)->postJson(route('coordinator.mginbon.saved_searches.store'), [
            'project_id' => $project->id, 'name' => '共有不可', 'scope' => 'shared',
            'criteria' => ['version' => 2, 'requests' => [['conditions' => [['field' => 'media', 'value' => '問題']]]]],
            'display_mode' => 'list',
        ])->assertForbidden();
    }

    public function test_match_descriptors_only_mark_conditions_that_match_each_page_item(): void
    {
        $project = MGinbonProject::create(['year' => 2095, 'name' => '2095年', 'status' => 'active']);
        $db = DB::connection('mginbon');
        $mediaId = $db->table('mginbon_media_types')->insertGetId(['code' => 'problem', 'name' => '問題', 'created_at' => now(), 'updated_at' => now()]);
        $unitId = $db->table('mginbon_production_units')->insertGetId(['mginbon_project_id' => $project->id, 'unit_type' => 'school', 'mikuni_code' => '500', 'display_name' => '地方校', 'created_at' => now(), 'updated_at' => now()]);
        $itemId = $db->table('mginbon_items')->insertGetId(['mginbon_production_unit_id' => $unitId, 'mginbon_media_type_id' => $mediaId, 'created_at' => now(), 'updated_at' => now()]);
        $search = app(\App\Services\MGinbon\MGinbonLedgerSearch::class);
        $requests = $search->decode(json_encode(['version' => 2, 'requests' => [['conditions' => [
            ['field' => 'mikuni_code', 'value' => '>400'], ['field' => 'school_name', 'value' => '存在しない'],
        ]]]], JSON_UNESCAPED_UNICODE));
        $base = $db->table('mginbon_items as items')->join('mginbon_production_units as units', 'units.id', '=', 'items.mginbon_production_unit_id')->join('mginbon_media_types as media', 'media.id', '=', 'items.mginbon_media_type_id');

        $matches = $search->matchDescriptors($base, [$itemId], $requests);

        $this->assertCount(1, $matches[$itemId]);
        $this->assertSame('mikuni_code', $matches[$itemId][0]['field']);
    }

    public function test_csv_export_uses_advanced_search_and_appends_search_metadata(): void
    {
        $user = User::factory()->create(['user_role' => 'coordinator', 'name' => '検索担当']);
        $project = MGinbonProject::create(['year' => 2094, 'name' => '2094年', 'status' => 'active']);
        $db = DB::connection('mginbon');
        $mediaId = $db->table('mginbon_media_types')->insertGetId(['code' => 'problem', 'name' => '問題', 'created_at' => now(), 'updated_at' => now()]);
        $subjectId = $db->table('mginbon_subjects')->insertGetId(['code' => 'japanese', 'name' => '国語', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['500', '対象校'], ['600', '対象外校']] as [$code, $name]) {
            $unitId = $db->table('mginbon_production_units')->insertGetId(['mginbon_project_id' => $project->id, 'unit_type' => 'school', 'mikuni_code' => $code, 'display_name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $itemId = $db->table('mginbon_items')->insertGetId(['mginbon_production_unit_id' => $unitId, 'mginbon_media_type_id' => $mediaId, 'created_at' => now(), 'updated_at' => now()]);
            $db->table('mginbon_item_subjects')->insert(['mginbon_item_id' => $itemId, 'mginbon_subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        }
        $find = json_encode(['version' => 2, 'requests' => [['conditions' => [['field' => 'mikuni_code', 'value' => '==500']]]]], JSON_UNESCAPED_UNICODE);

        $response = $this->actingAs($user)->get(route('coordinator.mginbon.export.csv', ['year' => 2094, 'find' => $find]));
        $response->assertOk();
        $csv = mb_convert_encoding($response->getContent(), 'UTF-8', 'SJIS-win');

        $this->assertStringContainsString('対象校', $csv);
        $this->assertStringNotContainsString('対象外校', $csv);
        $this->assertStringContainsString('検索条件', $csv);
        $this->assertStringContainsString('Mコード:==500', $csv);
        $this->assertStringContainsString('検索担当', $csv);
    }
}

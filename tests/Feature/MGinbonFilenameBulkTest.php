<?php

namespace Tests\Feature;

use App\Models\MGinbon\MGinbonProject;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MGinbonFilenameBulkTest extends TestCase
{
    use DatabaseTransactions;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databasePath = tempnam(sys_get_temp_dir(), 'mginbon_filename_bulk_');
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

    public function test_preview_classifies_rows_and_store_updates_only_exact_subject(): void
    {
        [$user, $project, $itemId, $japaneseItemSubjectId, $socialItemSubjectId] = $this->fixture();
        $payload = [
            'project_id' => $project->id,
            'milestone_code' => 'initial_text_proof_completed_on',
            'date' => '2026-10-02',
            'filenames' => "30812026__AASh.pdf\ninvalid.pdf\n30812025__AAKo.pdf",
            'overwrite' => false,
        ];

        $this->actingAs($user)->postJson(route('coordinator.mginbon.filename_bulk.preview'), $payload)
            ->assertOk()
            ->assertJsonPath('registrable_count', 1)
            ->assertJsonPath('rows.0.status', 'ready')
            ->assertJsonPath('rows.0.media_name', '解説解答')
            ->assertJsonPath('rows.0.subject_name', '社会')
            ->assertJsonPath('rows.1.status', 'invalid_format')
            ->assertJsonPath('rows.2.status', 'year_mismatch');

        $this->actingAs($user)->postJson(route('coordinator.mginbon.filename_bulk.store'), $payload)
            ->assertOk()->assertJsonPath('updated', 1);

        $this->assertDatabaseHas('mginbon_milestones', [
            'mginbon_item_id' => $itemId, 'mginbon_item_subject_id' => $socialItemSubjectId,
            'code' => 'initial_text_proof_completed_on', 'occurred_on' => '2026-10-02',
        ], 'mginbon');
        $this->assertDatabaseMissing('mginbon_milestones', [
            'mginbon_item_subject_id' => $japaneseItemSubjectId, 'code' => 'initial_text_proof_completed_on',
        ], 'mginbon');
        $this->assertDatabaseHas('mginbon_change_logs', [
            'mginbon_item_id' => $itemId, 'mginbon_item_subject_id' => $socialItemSubjectId, 'changed_by' => $user->id,
        ], 'mginbon');
    }

    public function test_existing_date_is_protected_until_overwrite_is_selected(): void
    {
        [$user, $project, $itemId, , $socialItemSubjectId] = $this->fixture();
        DB::connection('mginbon')->table('mginbon_milestones')->insert([
            'mginbon_item_id' => $itemId, 'mginbon_item_subject_id' => $socialItemSubjectId,
            'code' => 'reproof_shared_on', 'occurred_on' => '2026-09-30', 'source' => 'manual',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $payload = ['project_id' => $project->id, 'milestone_code' => 'reproof_shared_on', 'date' => '2026-10-02',
            'filenames' => '30812026__AASh.pdf', 'overwrite' => false];

        $this->actingAs($user)->postJson(route('coordinator.mginbon.filename_bulk.preview'), $payload)
            ->assertOk()->assertJsonPath('rows.0.status', 'existing_value')->assertJsonPath('registrable_count', 0);
        $this->actingAs($user)->postJson(route('coordinator.mginbon.filename_bulk.store'), $payload)
            ->assertOk()->assertJsonPath('updated', 0);

        $payload['overwrite'] = true;
        $this->actingAs($user)->postJson(route('coordinator.mginbon.filename_bulk.preview'), $payload)
            ->assertOk()->assertJsonPath('rows.0.status', 'overwrite_ready');
        $this->actingAs($user)->postJson(route('coordinator.mginbon.filename_bulk.store'), $payload)
            ->assertOk()->assertJsonPath('updated', 1);
        $this->assertDatabaseHas('mginbon_milestones', ['mginbon_item_subject_id' => $socialItemSubjectId,
            'code' => 'reproof_shared_on', 'occurred_on' => '2026-10-02'], 'mginbon');
    }

    public function test_duplicate_lines_are_not_registered(): void
    {
        [$user, $project] = $this->fixture();
        $payload = ['project_id' => $project->id, 'milestone_code' => 'completed_on', 'date' => '2026-10-02',
            'filenames' => "30812026__AASh.pdf\n30812026__AASh.pdf", 'overwrite' => false];
        $this->actingAs($user)->postJson(route('coordinator.mginbon.filename_bulk.preview'), $payload)
            ->assertOk()->assertJsonPath('registrable_count', 0)->assertJsonPath('rows.0.status', 'duplicate')->assertJsonPath('rows.1.status', 'duplicate');
    }

    private function fixture(): array
    {
        $user = User::factory()->create(['user_role' => 'coordinator']);
        $project = MGinbonProject::create(['project_job_id' => 999, 'year' => 2026, 'name' => '2026年', 'status' => 'active']);
        $db = DB::connection('mginbon');
        $mediaId = $db->table('mginbon_media_types')->insertGetId(['code' => 'explanation', 'name' => '解説解答', 'created_at' => now(), 'updated_at' => now()]);
        $japaneseId = $db->table('mginbon_subjects')->insertGetId(['code' => 'japanese', 'name' => '国語', 'created_at' => now(), 'updated_at' => now()]);
        $socialId = $db->table('mginbon_subjects')->insertGetId(['code' => 'social', 'name' => '社会', 'created_at' => now(), 'updated_at' => now()]);
        $unitId = $db->table('mginbon_production_units')->insertGetId(['mginbon_project_id' => $project->id, 'unit_type' => 'school',
            'n_code' => '3081', 'display_name' => '対象校', 'created_at' => now(), 'updated_at' => now()]);
        $itemId = $db->table('mginbon_items')->insertGetId(['mginbon_production_unit_id' => $unitId, 'mginbon_media_type_id' => $mediaId,
            'created_at' => now(), 'updated_at' => now()]);
        $japaneseItemSubjectId = $db->table('mginbon_item_subjects')->insertGetId(['mginbon_item_id' => $itemId, 'mginbon_subject_id' => $japaneseId,
            'created_at' => now(), 'updated_at' => now()]);
        $socialItemSubjectId = $db->table('mginbon_item_subjects')->insertGetId(['mginbon_item_id' => $itemId, 'mginbon_subject_id' => $socialId,
            'created_at' => now(), 'updated_at' => now()]);
        return [$user, $project, $itemId, $japaneseItemSubjectId, $socialItemSubjectId];
    }
}

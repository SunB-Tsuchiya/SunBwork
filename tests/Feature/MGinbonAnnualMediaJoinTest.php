<?php

namespace Tests\Feature;

use App\Models\MGinbon\MGinbonImportBatch;
use App\Models\MGinbon\MGinbonImportRow;
use App\Services\MGinbon\MGinbonNormalizedDataPromotionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MGinbonAnnualMediaJoinTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databasePath = tempnam(sys_get_temp_dir(), 'mginbon_join_test_');
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

    public function test_filemaker_media_reuses_the_annual_school_row_and_keeps_annual_fields(): void
    {
        $db = DB::connection('mginbon');
        $now = now();
        $projectId = $db->table('mginbon_projects')->insertGetId([
            'year' => 2097,
            'name' => '2097年 中学入試問題集',
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $unitId = $db->table('mginbon_production_units')->insertGetId([
            'mginbon_project_id' => $projectId,
            'unit_type' => 'exam',
            'mikuni_code' => '101',
            'n_code' => '1721',
            'display_name' => '年度Excelの学校名',
            'alpha_group' => '①',
            'exam_session' => '第1回',
            'source_row_number' => 2,
            'source_data' => json_encode(['school_name' => '年度Excelの学校名'], JSON_UNESCAPED_UNICODE),
            'import_warnings' => json_encode([], JSON_UNESCAPED_UNICODE),
            'review_status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $batch = MGinbonImportBatch::create([
            'mginbon_project_id' => $projectId,
            'source_filename' => 'filemaker.mer',
            'source_sha256' => str_repeat('a', 64),
            'source_year' => 2097,
            'status' => 'normalized',
            'record_count' => 1,
            'column_count' => 5,
        ]);
        MGinbonImportRow::create([
            'mginbon_import_batch_id' => $batch->id,
            'source_row_number' => 2,
            'raw_mikuni_code' => '101',
            'raw_n_code' => '1721',
            'raw_school_name' => 'FileMaker側の学校名',
            'raw_media_type' => '解答',
            'raw_json' => ['学校名' => 'FileMaker側の学校名', '媒体分類' => '解答'],
            'normalized_json' => [
                'unit_type' => 'exam',
                'mikuni_code' => '101',
                'n_code' => '1721',
                'display_name' => 'FileMaker側の学校名',
                'media_type' => '解答',
                'publication_status' => '掲載',
                'subjects' => [
                    'japanese' => [
                        'active' => true,
                        'measurements' => [],
                        'dates' => [],
                        'actors' => [],
                    ],
                ],
                'shared_dates' => [],
            ],
            'resolution_status' => 'candidate',
        ]);

        $summary = app(MGinbonNormalizedDataPromotionService::class)->promote($batch->id);

        $this->assertSame(0, $summary['units']);
        $this->assertSame(1, $summary['items']);
        $this->assertSame(1, $db->table('mginbon_production_units')->where('mginbon_project_id', $projectId)->count());
        $unit = $db->table('mginbon_production_units')->where('id', $unitId)->first();
        $this->assertSame('年度Excelの学校名', $unit->display_name);
        $this->assertSame('①', $unit->alpha_group);
        $this->assertSame('第1回', $unit->exam_session);
        $this->assertSame($unitId, (int) $db->table('mginbon_items')->value('mginbon_production_unit_id'));
        $this->assertSame(60, (int) $db->table('mginbon_media_types')->where('name', '解答')->value('sort_order'));
        $this->assertSame(
            ['text_input', 'drawing', 'initial_operation', 'initial_check', 'initial_text_proof', 'reproof_scan_check', 'reproof_text_proof', 'reproof_operation', 'client_return_operation', 'third_proof', 'third_operation', 'fourth_operation', 'fourth_proof', 'fifth_operation'],
            $db->table('mginbon_stage_definitions')->where('mginbon_project_id', $projectId)->orderBy('sort_order')->pluck('code')->all()
        );
    }
}

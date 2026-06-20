<?php

namespace Tests\Feature\NSystem;

use App\Services\NSystem\NPublicationCatalogImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NPublicationCatalogImportTest extends TestCase
{
    use RefreshDatabase;

    // MySQL InnoDB FULLTEXT はトランザクション内の未コミットデータを検索できないため
    // トランザクションラップを無効化し、TRUNCATE で各テストのクリーンアップを行う
    protected $connectionsToTransact = [];

    private static array $nSystemTables = [
        'n_exam_daimons', 'n_exam_documents', 'n_source_school_rows',
        'n_import_batches', 'n_publication_entries', 'n_publication_editions',
        'n_exams', 'n_exam_series', 'n_school_years', 'n_schools',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->wipeNSystemTables();
    }

    protected function tearDown(): void
    {
        $this->wipeNSystemTables();
        parent::tearDown();
    }

    private function wipeNSystemTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::$nSystemTables as $table) {
            DB::statement("TRUNCATE TABLE `{$table}`");
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function test_import_splits_shared_m109_and_keeps_2026_edogawa_as_4331(): void
    {
        if (! is_file(base_path('z_NDBSystem/Nコードリスト2025.xlsx'))) {
            $this->markTestSkipped('z_NDBSystem/Nコードリスト2025.xlsx が存在しないためスキップ');
        }

        $summary = app(NPublicationCatalogImportService::class)->import([2025, 2026]);

        $this->assertSame(356, $summary['entries']);

        $sharedEntries2025 = DB::table('n_publication_entries')
            ->join('n_publication_editions', 'n_publication_editions.id', '=', 'n_publication_entries.publication_edition_id')
            ->join('n_exams', 'n_exams.id', '=', 'n_publication_entries.exam_id')
            ->where('n_publication_editions.admission_year', 2025)
            ->where('n_publication_entries.mikuni_code', 109)
            ->orderBy('n_exams.n_code')
            ->pluck('n_exams.n_code')
            ->all();

        $sharedEntries2026 = DB::table('n_publication_entries')
            ->join('n_publication_editions', 'n_publication_editions.id', '=', 'n_publication_entries.publication_edition_id')
            ->join('n_exams', 'n_exams.id', '=', 'n_publication_entries.exam_id')
            ->where('n_publication_editions.admission_year', 2026)
            ->where('n_publication_entries.mikuni_code', 109)
            ->orderBy('n_exams.n_code')
            ->pluck('n_exams.n_code')
            ->all();

        $this->assertSame(['4551', '4751'], $sharedEntries2025);
        $this->assertSame(['4551', '4751'], $sharedEntries2026);

        $edogawa2026 = DB::table('n_exams')
            ->where('admission_year', 2026)
            ->where('n_code', '4331')
            ->first();

        $this->assertNotNull($edogawa2026);
        $this->assertSame(0, DB::table('n_exams')->where('admission_year', 2026)->where('n_code', '4335')->count());

        $sourceRow = DB::table('n_source_school_rows')
            ->where('admission_year', 2026)
            ->where('raw_mikuni_code', '106')
            ->first();

        $this->assertNotNull($sourceRow);
        $this->assertStringContainsString('変更前コードを採用', (string) $sourceRow->resolution_notes);
    }
}

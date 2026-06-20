<?php

namespace Tests\Feature\NSystem;

use App\Services\NSystem\NPublicationCatalogImportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NPublicationCatalogImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('n_schools', function (Blueprint $table) {
            $table->id();
            $table->string('n_code_prefix');
            $table->string('canonical_name');
            $table->string('prefecture')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('merged_into_id')->nullable();
            $table->timestamps();
        });

        Schema::create('n_school_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->unsignedSmallInteger('admission_year');
            $table->string('school_name');
            $table->string('normalized_name');
            $table->string('gender_type');
            $table->string('prefecture')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('n_exam_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            $table->string('series_key');
            $table->string('canonical_label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('n_exams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_series_id');
            $table->unsignedSmallInteger('admission_year');
            $table->string('n_code');
            $table->string('exam_label')->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('n_publication_editions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('admission_year');
            $table->string('title');
            $table->string('source_filename')->nullable();
            $table->timestamps();
        });

        Schema::create('n_publication_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('publication_edition_id');
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedSmallInteger('mikuni_code');
            $table->string('publication_section');
            $table->unsignedSmallInteger('sort_order');
            $table->string('printed_school_name');
            $table->string('printed_exam_label')->nullable();
            $table->unsignedSmallInteger('source_row_number')->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('n_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('import_type');
            $table->string('source_filename');
            $table->unsignedSmallInteger('source_year')->nullable();
            $table->string('file_hash')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('status');
            $table->json('summary_json')->nullable();
            $table->timestamps();
        });

        Schema::create('n_source_school_rows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_batch_id');
            $table->unsignedSmallInteger('source_row_number')->nullable();
            $table->unsignedSmallInteger('admission_year');
            $table->string('raw_mikuni_code')->nullable();
            $table->string('raw_n_code')->nullable();
            $table->text('raw_school_name')->nullable();
            $table->string('raw_exam_label')->nullable();
            $table->json('parsed_json')->nullable();
            $table->string('resolution_status');
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('n_source_school_rows');
        Schema::dropIfExists('n_import_batches');
        Schema::dropIfExists('n_publication_entries');
        Schema::dropIfExists('n_publication_editions');
        Schema::dropIfExists('n_exams');
        Schema::dropIfExists('n_exam_series');
        Schema::dropIfExists('n_school_years');
        Schema::dropIfExists('n_schools');

        parent::tearDown();
    }

    public function test_import_splits_shared_m109_and_keeps_2026_edogawa_as_4331(): void
    {
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

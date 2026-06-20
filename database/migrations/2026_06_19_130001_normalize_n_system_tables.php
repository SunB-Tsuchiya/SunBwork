<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('n_answers_daimon', 'n_legacy_answers_daimon');
        Schema::rename('n_questions_daimon', 'n_legacy_questions_daimon');
        Schema::rename('n_schools', 'n_legacy_schools');

        $this->createTables();
        $this->migrateLegacyData();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach ([
            'n_source_school_rows', 'n_import_batches', 'n_exam_daimons',
            'n_exam_documents', 'n_publication_entry_exams', 'n_publication_entries',
            'n_publication_editions', 'n_exams', 'n_exam_series', 'n_school_years', 'n_schools',
        ] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();

        Schema::rename('n_legacy_schools', 'n_schools');
        Schema::rename('n_legacy_questions_daimon', 'n_questions_daimon');
        Schema::rename('n_legacy_answers_daimon', 'n_answers_daimon');
    }

    private function createTables(): void
    {
        Schema::create('n_schools', function (Blueprint $table) {
            $table->id();
            $table->string('n_code_prefix', 3)->unique();
            $table->string('canonical_name', 200);
            $table->string('prefecture', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('merged_into_id')->nullable()->constrained('n_schools')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('n_school_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('n_schools')->cascadeOnDelete();
            $table->unsignedSmallInteger('admission_year');
            $table->string('school_name', 200);
            $table->string('normalized_name', 200);
            $table->string('gender_type', 10)->default('unknown');
            $table->string('prefecture', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['school_id', 'admission_year']);
            $table->index(['admission_year', 'normalized_name']);
        });

        Schema::create('n_exam_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('n_schools')->cascadeOnDelete();
            $table->string('series_key', 50);
            $table->string('canonical_label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['school_id', 'series_key']);
        });

        Schema::create('n_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_series_id')->constrained('n_exam_series')->cascadeOnDelete();
            $table->unsignedSmallInteger('admission_year');
            $table->string('n_code', 10);
            $table->string('exam_label', 200)->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamps();
            $table->unique(['exam_series_id', 'admission_year']);
            $table->unique(['admission_year', 'n_code']);
            $table->index(['admission_year', 'n_code']);
        });

        Schema::create('n_publication_editions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('admission_year')->unique();
            $table->string('title', 200);
            $table->string('source_filename', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('n_publication_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publication_edition_id')->constrained('n_publication_editions')->cascadeOnDelete();
            $table->unsignedSmallInteger('mikuni_code');
            $table->string('publication_section', 20);
            $table->unsignedSmallInteger('sort_order');
            $table->string('printed_school_name', 300);
            $table->string('printed_exam_label', 200)->nullable();
            $table->unsignedSmallInteger('source_row_number')->nullable();
            $table->text('source_notes')->nullable();
            $table->timestamps();
            $table->unique(['publication_edition_id', 'mikuni_code']);
            $table->index(['publication_edition_id', 'publication_section', 'sort_order'], 'n_pub_entries_section_sort_idx');
        });

        Schema::create('n_publication_entry_exams', function (Blueprint $table) {
            $table->foreignId('publication_entry_id')->constrained('n_publication_entries')->cascadeOnDelete();
            $table->foreignId('exam_id')->constrained('n_exams')->cascadeOnDelete();
            $table->boolean('is_primary')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['publication_entry_id', 'exam_id']);
        });

        Schema::create('n_exam_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('n_exams')->cascadeOnDelete();
            $table->string('subject', 5);
            $table->char('document_type', 1);
            $table->string('source_filename', 255)->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'subject', 'document_type']);
            $table->index(['subject', 'document_type']);
        });

        Schema::create('n_exam_daimons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_document_id')->constrained('n_exam_documents')->cascadeOnDelete();
            $table->unsignedTinyInteger('daimon_index');
            $table->longText('body_html');
            $table->text('body_text');
            $table->timestamps();
            $table->unique(['exam_document_id', 'daimon_index']);
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE n_exam_daimons ADD FULLTEXT INDEX n_exam_daimons_body_text_fulltext (body_text) WITH PARSER ngram');
        }

        Schema::create('n_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('import_type', 50);
            $table->string('source_filename', 255);
            $table->unsignedSmallInteger('source_year')->nullable();
            $table->string('file_hash', 64)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->string('status', 20);
            $table->json('summary_json')->nullable();
            $table->timestamps();
        });

        Schema::create('n_source_school_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('n_import_batches')->cascadeOnDelete();
            $table->unsignedSmallInteger('source_row_number')->nullable();
            $table->unsignedSmallInteger('admission_year');
            $table->string('raw_mikuni_code', 50)->nullable();
            $table->string('raw_n_code', 100)->nullable();
            $table->text('raw_school_name')->nullable();
            $table->string('raw_exam_label', 200)->nullable();
            $table->json('parsed_json')->nullable();
            $table->string('resolution_status', 20);
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
        });
    }

    private function migrateLegacyData(): void
    {
        $now = now();

        DB::table('n_publication_editions')->insert([
            'admission_year' => 2024,
            'title' => '2024年度版',
            'source_filename' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $batchId = DB::table('n_import_batches')->insertGetId([
            'import_type' => 'legacy_2024_migration',
            'source_filename' => 'n_legacy_*',
            'source_year' => 2024,
            'imported_at' => $now,
            'status' => 'completed_unresolved',
            'summary_json' => json_encode(['unresolved_n_codes' => ['464F']]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach (DB::table('n_legacy_schools')->orderBy('id')->get() as $legacySchool) {
            if ($legacySchool->year === 2024 && $legacySchool->code === '464F') {
                DB::table('n_source_school_rows')->insert([
                    'import_batch_id' => $batchId,
                    'admission_year' => 2024,
                    'raw_n_code' => '464F',
                    'raw_school_name' => $legacySchool->name,
                    'parsed_json' => json_encode(['legacy_school_id' => $legacySchool->id]),
                    'resolution_status' => 'unresolved',
                    'resolution_notes' => '2024学校リストには464N（星野学園中学校）が存在し、464Fは仮データのため有効な試験へ統合しない。旧本文はlegacyテーブルに保持。',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            $nCode = $legacySchool->code;
            $prefix = substr($nCode, 0, 3);
            $schoolId = DB::table('n_schools')->where('n_code_prefix', $prefix)->value('id');

            if (! $schoolId) {
                $schoolId = DB::table('n_schools')->insertGetId([
                    'n_code_prefix' => $prefix,
                    'canonical_name' => $legacySchool->name,
                    'is_active' => true,
                    'created_at' => $legacySchool->created_at ?? $now,
                    'updated_at' => $legacySchool->updated_at ?? $now,
                ]);
            }

            DB::table('n_school_years')->updateOrInsert(
                ['school_id' => $schoolId, 'admission_year' => $legacySchool->year],
                [
                    'school_name' => $legacySchool->name,
                    'normalized_name' => $legacySchool->name,
                    'gender_type' => $this->genderType($legacySchool->category),
                    'notes' => $legacySchool->category === '地方' ? '2024年度版の地方掲載区分' : null,
                    'created_at' => $legacySchool->created_at ?? $now,
                    'updated_at' => $legacySchool->updated_at ?? $now,
                ]
            );

            $seriesId = DB::table('n_exam_series')->insertGetId([
                'school_id' => $schoolId,
                'series_key' => 'n-' . strtolower($nCode),
                'canonical_label' => $nCode,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $examId = DB::table('n_exams')->insertGetId([
                'exam_series_id' => $seriesId,
                'admission_year' => $legacySchool->year,
                'n_code' => $nCode,
                'exam_label' => null,
                'source_notes' => '旧n_schools.id=' . $legacySchool->id . 'から移行',
                'created_at' => $legacySchool->created_at ?? $now,
                'updated_at' => $legacySchool->updated_at ?? $now,
            ]);

            $this->migrateDaimons('n_legacy_questions_daimon', $legacySchool->id, $examId, 'Q');
            $this->migrateDaimons('n_legacy_answers_daimon', $legacySchool->id, $examId, 'A');
        }
    }

    private function migrateDaimons(string $legacyTable, int $legacySchoolId, int $examId, string $type): void
    {
        foreach (DB::table($legacyTable)->where('school_id', $legacySchoolId)->get()->groupBy('subject') as $subject => $rows) {
            $documentId = DB::table('n_exam_documents')->insertGetId([
                'exam_id' => $examId,
                'subject' => $subject,
                'document_type' => $type,
                'source_filename' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($rows as $row) {
                DB::table('n_exam_daimons')->insert([
                    'exam_document_id' => $documentId,
                    'daimon_index' => $row->daimon_index,
                    'body_html' => $row->body_html,
                    'body_text' => $row->body_text,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }

    private function genderType(string $category): string
    {
        return match ($category) {
            '共学' => 'coed',
            '男子' => 'boys',
            '女子' => 'girls',
            default => 'unknown',
        };
    }
};

<?php

namespace App\Console\Commands\NSystem;

use App\Models\NSystem\NExam;
use App\Models\NSystem\NExamDaimon;
use App\Models\NSystem\NExamDocument;
use App\Models\NSystem\NExamSeries;
use App\Models\NSystem\NImportBatch;
use App\Models\NSystem\NSchool;
use App\Models\NSystem\NSchoolYear;
use App\Models\NSystem\NSourceSchoolRow;
use App\Services\NSystem\NPublicationCatalogImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class NSystemImport extends Command
{
    protected $signature = 'n-system:import {--force : 確認をスキップして実行}';

    protected $description = 'NSystem入試データと年度版Mコードを正規化テーブルへインポートする';

    public function handle(NPublicationCatalogImportService $publicationCatalogImportService): int
    {
        if (! $this->option('force') && ! $this->confirm('NSystemの学校・試験・問題・解答をupsertします。続けますか？')) {
            $this->line('キャンセルしました。');

            return self::SUCCESS;
        }

        $importDir = Storage::disk('local')->path('n_import');
        $schoolsPath = $importDir . '/schools_index.json';
        if (! file_exists($schoolsPath)) {
            $this->error("schools_index.json が見つかりません: {$schoolsPath}");

            return self::FAILURE;
        }

        $schools = json_decode(file_get_contents($schoolsPath), true);
        if (! is_array($schools)) {
            $this->error('schools_index.jsonを解析できません。');

            return self::FAILURE;
        }

        $batch = NImportBatch::create([
            'import_type' => 'exam_json',
            'source_filename' => 'n_import',
            'source_year' => 2024,
            'file_hash' => hash_file('sha256', $schoolsPath),
            'status' => 'running',
        ]);

        $examMap = [];
        DB::transaction(function () use ($schools, $batch, &$examMap) {
            foreach ($schools as $item) {
                $year = (int) $item['year'];
                $sourceCode = strtoupper((string) $item['code']);
                if ($year === 2024 && $sourceCode === '464F') {
                    NSourceSchoolRow::create([
                        'import_batch_id' => $batch->id,
                        'admission_year' => $year,
                        'raw_n_code' => $sourceCode,
                        'raw_school_name' => $item['name'],
                        'parsed_json' => $item,
                        'resolution_status' => 'unresolved',
                        'resolution_notes' => '学校リストの464Nと衝突する仮データのため取込対象外。',
                    ]);
                    continue;
                }
                $nCode = $this->normalizeNCode($sourceCode, $year);
                $prefix = substr($nCode, 0, 3);

                $school = NSchool::updateOrCreate(
                    ['n_code_prefix' => $prefix],
                    ['canonical_name' => $item['name'], 'is_active' => true]
                );
                NSchoolYear::updateOrCreate(
                    ['school_id' => $school->id, 'admission_year' => $year],
                    [
                        'school_name' => $item['name'],
                        'normalized_name' => $item['name'],
                        'gender_type' => $this->genderType($item['category'] ?? ''),
                        'notes' => ($item['category'] ?? '') === '地方' ? "{$year}年度版の地方掲載区分" : null,
                    ]
                );
                $series = NExamSeries::updateOrCreate(
                    ['school_id' => $school->id, 'series_key' => 'n-' . strtolower($nCode)],
                    ['canonical_label' => $nCode, 'is_active' => true]
                );
                $exam = NExam::updateOrCreate(
                    ['admission_year' => $year, 'n_code' => $nCode],
                    [
                        'exam_series_id' => $series->id,
                        'source_notes' => null,
                    ]
                );
                $examMap["{$sourceCode}_{$year}"] = $exam;
            }
        });

        $counts = ['schools' => count($schools), 'questions' => 0, 'answers' => 0, 'skipped' => 0, 'errors' => 0];
        $pattern = '/^([A-Za-z0-9]{4})(\d{4})__(Q|A)(Ko|Sa|Sh|Ri)\.json$/';

        foreach (glob($importDir . '/*.json') as $filepath) {
            $filename = basename($filepath);
            if (! preg_match($pattern, $filename, $matches)) {
                continue;
            }

            [, $sourceCode, $year, $type, $subject] = $matches;
            $exam = $examMap[strtoupper($sourceCode) . '_' . $year] ?? null;
            if (! $exam) {
                $counts['skipped']++;
                continue;
            }

            $items = json_decode(file_get_contents($filepath), true);
            if (! is_array($items)) {
                $counts['errors']++;
                continue;
            }

            $document = NExamDocument::updateOrCreate(
                ['exam_id' => $exam->id, 'subject' => $subject, 'document_type' => $type],
                ['source_filename' => $filename]
            );
            foreach ($items as $item) {
                NExamDaimon::updateOrCreate(
                    ['exam_document_id' => $document->id, 'daimon_index' => $item['daimon_index'] ?? 0],
                    [
                        'body_html' => str_replace('src="images/', 'src="/n_images/', $item['body_html'] ?? ''),
                        'body_text' => $item['body_text'] ?? '',
                    ]
                );
                $counts[$type === 'Q' ? 'questions' : 'answers']++;
            }
        }

        $batch->update([
            'imported_at' => now(),
            'status' => $counts['errors'] ? 'completed_errors' : 'completed',
            'summary_json' => $counts,
        ]);

        $catalogSummary = $publicationCatalogImportService->import();

        $this->info("学校: {$counts['schools']}件 / 問題: {$counts['questions']}件 / 解答: {$counts['answers']}件");
        $this->info("年度版Mコード: {$catalogSummary['entries']}掲載行 / 監査元行: {$catalogSummary['source_rows']}件");
        if ($counts['skipped'] || $counts['errors']) {
            $this->warn("スキップ: {$counts['skipped']}ファイル / エラー: {$counts['errors']}ファイル");
        }

        return $counts['errors'] ? self::FAILURE : self::SUCCESS;
    }

    private function normalizeNCode(string $code, int $year): string
    {
        return $code;
    }

    private function genderType(string $category): string
    {
        return match ($category) {
            '共学' => 'coed', '男子' => 'boys', '女子' => 'girls', default => 'unknown',
        };
    }
}

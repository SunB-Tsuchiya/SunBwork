<?php

namespace App\Services\NSystem;

use App\Models\NSystem\NExam;
use App\Models\NSystem\NExamSeries;
use App\Models\NSystem\NImportBatch;
use App\Models\NSystem\NPublicationEdition;
use App\Models\NSystem\NPublicationEntry;
use App\Models\NSystem\NSchool;
use App\Models\NSystem\NSchoolYear;
use App\Models\NSystem\NSourceSchoolRow;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

class NPublicationCatalogImportService
{
    private const HEADER_ALIASES = [
        'mikuni_code' => ['みくにコード'],
        'n_code' => ['日能研コード'],
        'school_name' => ['学校名'],
        'exam_label' => ['入試回掲載用', '入試回'],
    ];

    public function import(array $years = [2022, 2023, 2024, 2025, 2026]): array
    {
        $summary = [
            'years' => [],
            'entries' => 0,
            'source_rows' => 0,
            'created_batches' => 0,
        ];

        foreach ($years as $year) {
            $yearSummary = $this->importYear((int) $year);
            $summary['years'][$year] = $yearSummary;
            $summary['entries'] += $yearSummary['entries'];
            $summary['source_rows'] += $yearSummary['source_rows'];
            $summary['created_batches']++;
        }

        return $summary;
    }

    public function importYear(int $year): array
    {
        $path = base_path("z_NDBSystem/Nコードリスト{$year}.xlsx");
        if (! is_file($path)) {
            throw new RuntimeException("年度ファイルが見つかりません: {$path}");
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $header = $this->resolveHeader($sheet);
        $lastRow = $sheet->getHighestDataRow();

        $batch = NImportBatch::create([
            'import_type' => 'publication_catalog',
            'source_filename' => basename($path),
            'source_year' => $year,
            'file_hash' => hash_file('sha256', $path),
            'status' => 'running',
        ]);

        try {
            $rowSummaries = [];
            $normalizedEntries = [];
            $seenNCodes = [];
            $seenMikuni = [];

            for ($row = $header['row'] + 1; $row <= $lastRow; $row++) {
                $rawMikuniCode = $this->cellText($sheet, $header['columns']['mikuni_code'], $row);
                $rawNCode = $this->cellText($sheet, $header['columns']['n_code'], $row);
                $rawSchoolName = $this->cellText($sheet, $header['columns']['school_name'], $row);
                $rawExamLabel = $this->cellText($sheet, $header['columns']['exam_label'] ?? null, $row);

                if ($rawMikuniCode === '' && $rawNCode === '' && $rawSchoolName === '') {
                    continue;
                }

                $mikuniCode = $this->parseMikuniCode($rawMikuniCode, $year, $row);
                $nCodes = $this->parseNCodes($rawNCode);
                $schoolNames = $this->parseSchoolNames($rawSchoolName);
                $examLabel = $this->cleanExamLabel($rawExamLabel);
                $sourceNotes = $this->buildSourceNotes($year, $mikuniCode, $rawNCode, $rawSchoolName);
                $status = count($nCodes) > 1 ? 'normalized_exception' : 'imported';

                if (count($nCodes) !== count($schoolNames)) {
                    throw new RuntimeException("{$year}年度 {$row}行目: Nコード数と学校名数が一致しません。");
                }

                foreach ($nCodes as $nCode) {
                    if (isset($seenNCodes[$nCode])) {
                        throw new RuntimeException("{$year}年度でNコード {$nCode} が重複しています。{$seenNCodes[$nCode]}行目と{$row}行目です。");
                    }

                    $seenNCodes[$nCode] = $row;
                }

                $seenMikuni[$mikuniCode] ??= [];
                $seenMikuni[$mikuniCode] = array_merge($seenMikuni[$mikuniCode], $nCodes);

                foreach ($nCodes as $index => $nCode) {
                    $normalizedEntries[] = [
                        'row' => $row,
                        'sort_order' => count($normalizedEntries) + 1,
                        'mikuni_code' => $mikuniCode,
                        'n_code' => $nCode,
                        'school_name' => $schoolNames[$index],
                        'exam_label' => $examLabel,
                        'publication_section' => $this->publicationSection($mikuniCode),
                        'source_notes' => $sourceNotes,
                    ];
                }

                $rowSummaries[] = [
                    'row' => $row,
                    'mikuni_code' => $mikuniCode,
                    'n_codes' => $nCodes,
                    'school_names' => $schoolNames,
                    'exam_label' => $examLabel,
                    'source_notes' => $sourceNotes,
                    'status' => $status,
                    'raw_mikuni_code' => $rawMikuniCode,
                    'raw_n_code' => $rawNCode,
                    'raw_school_name' => $rawSchoolName,
                    'raw_exam_label' => $rawExamLabel,
                ];
            }

            foreach ($seenMikuni as $mikuniCode => $nCodes) {
                $uniqueNCodes = array_values(array_unique($nCodes));
                if (count($uniqueNCodes) <= 1) {
                    continue;
                }

                $isAllowedSharedMikuni = $year >= 2025
                    && $mikuniCode === 109
                    && $uniqueNCodes === ['4551', '4751'];

                if (! $isAllowedSharedMikuni) {
                    $joined = implode(', ', $uniqueNCodes);
                    throw new RuntimeException("{$year}年度でMコード {$mikuniCode} が重複しています: {$joined}");
                }
            }

            DB::transaction(function () use ($year, $path, $batch, $rowSummaries, $normalizedEntries): void {
                $edition = NPublicationEdition::updateOrCreate(
                    ['admission_year' => $year],
                    ['title' => "{$year}年度版", 'source_filename' => basename($path)]
                );

                NPublicationEntry::where('publication_edition_id', $edition->id)->delete();

                foreach ($rowSummaries as $rowSummary) {
                    NSourceSchoolRow::create([
                        'import_batch_id' => $batch->id,
                        'source_row_number' => $rowSummary['row'],
                        'admission_year' => $year,
                        'raw_mikuni_code' => $rowSummary['raw_mikuni_code'],
                        'raw_n_code' => $rowSummary['raw_n_code'],
                        'raw_school_name' => $rowSummary['raw_school_name'],
                        'raw_exam_label' => $rowSummary['raw_exam_label'],
                        'parsed_json' => [
                            'mikuni_code' => $rowSummary['mikuni_code'],
                            'n_codes' => $rowSummary['n_codes'],
                            'school_names' => $rowSummary['school_names'],
                            'exam_label' => $rowSummary['exam_label'],
                        ],
                        'resolution_status' => $rowSummary['status'],
                        'resolution_notes' => $rowSummary['source_notes'],
                    ]);
                }

                foreach ($normalizedEntries as $entry) {
                    $exam = $this->resolveExam($year, $entry['n_code'], $entry['school_name'], $entry['exam_label'], $entry['publication_section']);

                    NPublicationEntry::create([
                        'publication_edition_id' => $edition->id,
                        'school_id' => $exam->examSeries->school_id,
                        'exam_id' => $exam->id,
                        'mikuni_code' => $entry['mikuni_code'],
                        'publication_section' => $entry['publication_section'],
                        'sort_order' => $entry['sort_order'],
                        'printed_school_name' => $entry['school_name'],
                        'printed_exam_label' => $entry['exam_label'],
                        'source_row_number' => $entry['row'],
                        'source_notes' => $entry['source_notes'],
                    ]);
                }
            });

            $summary = [
                'entries' => count($normalizedEntries),
                'source_rows' => count($rowSummaries),
                'shared_m_codes' => collect($rowSummaries)
                    ->filter(fn (array $row): bool => count($row['n_codes']) > 1)
                    ->map(fn (array $row): string => 'M' . $row['mikuni_code'])
                    ->values()
                    ->all(),
            ];

            $batch->update([
                'imported_at' => now(),
                'status' => 'completed',
                'summary_json' => $summary,
            ]);

            return $summary;
        } catch (Throwable $throwable) {
            $batch->update([
                'imported_at' => now(),
                'status' => 'failed',
                'summary_json' => ['error' => $throwable->getMessage()],
            ]);

            throw $throwable;
        }
    }

    private function resolveHeader(Worksheet $sheet): array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        for ($row = 1; $row <= min(5, $sheet->getHighestDataRow()); $row++) {
            $columns = [];
            for ($column = 1; $column <= $highestColumnIndex; $column++) {
                $coordinate = Coordinate::stringFromColumnIndex($column) . $row;
                $text = $this->normalizeHeader($sheet->getCell($coordinate)->getFormattedValue());
                if ($text === '') {
                    continue;
                }

                foreach (self::HEADER_ALIASES as $key => $aliases) {
                    if (in_array($text, $aliases, true) && ! isset($columns[$key])) {
                        $columns[$key] = Coordinate::stringFromColumnIndex($column);
                    }
                }
            }

            if (isset($columns['mikuni_code'], $columns['n_code'], $columns['school_name'])) {
                return ['row' => $row, 'columns' => $columns];
            }
        }

        throw new RuntimeException('Excelヘッダーを解決できませんでした。');
    }

    private function resolveExam(int $year, string $nCode, string $schoolName, string $examLabel, string $publicationSection): NExam
    {
        $prefix = substr($nCode, 0, 3);
        $genderType = match ($publicationSection) {
            '共学' => 'coed',
            '男子' => 'boys',
            '女子' => 'girls',
            default => 'unknown',
        };

        $school = NSchool::updateOrCreate(
            ['n_code_prefix' => $prefix],
            ['canonical_name' => $schoolName, 'is_active' => true]
        );

        NSchoolYear::updateOrCreate(
            ['school_id' => $school->id, 'admission_year' => $year],
            [
                'school_name' => $schoolName,
                'normalized_name' => $schoolName,
                'gender_type' => $genderType,
                'notes' => $publicationSection === '地方' ? "{$year}年度版の地方掲載区分" : null,
            ]
        );

        $series = NExamSeries::updateOrCreate(
            ['school_id' => $school->id, 'series_key' => 'n-' . strtolower($nCode)],
            ['canonical_label' => $examLabel !== '' ? $examLabel : $nCode, 'is_active' => true]
        );

        return NExam::updateOrCreate(
            ['admission_year' => $year, 'n_code' => $nCode],
            ['exam_series_id' => $series->id, 'exam_label' => $examLabel !== '' ? $examLabel : null]
        );
    }

    private function normalizeHeader(string $value): string
    {
        $value = mb_convert_kana($value, 'asKV', 'UTF-8');
        $value = str_replace(["\r", "\n"], '', $value);
        $value = preg_replace('/[[:space:]【】\\[\\]（）()]/u', '', $value);

        return trim((string) $value);
    }

    private function cellText(Worksheet $sheet, ?string $column, int $row): string
    {
        if ($column === null) {
            return '';
        }

        return trim((string) $sheet->getCell($column . $row)->getFormattedValue());
    }

    private function parseMikuniCode(string $rawMikuniCode, int $year, int $row): int
    {
        if (! preg_match('/\d+/', $rawMikuniCode, $matches)) {
            throw new RuntimeException("{$year}年度 {$row}行目: Mコードを解釈できません。");
        }

        return (int) $matches[0];
    }

    private function parseNCodes(string $rawNCode): array
    {
        if (str_contains($rawNCode, '→')) {
            preg_match('/[0-9A-Za-z]+/u', strtoupper($rawNCode), $firstCode);

            if ($firstCode !== []) {
                return [$firstCode[0]];
            }
        }

        $codes = array_values(array_filter(array_map(
            fn (string $token): string => strtoupper(trim($token)),
            preg_split('/[^\dA-Za-z]+/u', $rawNCode) ?: []
        )));

        if ($codes === []) {
            throw new RuntimeException('Nコードを解釈できません。');
        }

        return $codes;
    }

    private function parseSchoolNames(string $rawSchoolName): array
    {
        $firstLine = trim(strtok(str_replace("\r", "\n", $rawSchoolName), "\n") ?: '');
        $parts = preg_split('/\s*／\s*/u', $firstLine) ?: [];

        return array_values(array_filter(array_map(function (string $name): string {
            $name = preg_replace('/[※＊].*$/u', '', trim($name));

            return trim((string) $name);
        }, $parts)));
    }

    private function cleanExamLabel(string $rawExamLabel): string
    {
        $line = trim(strtok(str_replace("\r", "\n", $rawExamLabel), "\n") ?: '');

        return trim($line);
    }

    private function publicationSection(int $mikuniCode): string
    {
        return match (true) {
            $mikuniCode >= 100 && $mikuniCode < 200 => '共学',
            $mikuniCode >= 200 && $mikuniCode < 300 => '男子',
            $mikuniCode >= 300 && $mikuniCode < 400 => '女子',
            $mikuniCode >= 500 && $mikuniCode < 600 => '地方',
            default => '不明',
        };
    }

    private function buildSourceNotes(int $year, int $mikuniCode, string $rawNCode, string $rawSchoolName): ?string
    {
        $notes = [];

        if ($year >= 2025 && $mikuniCode === 109) {
            $notes[] = '同一問題のためM109を4551/4751で共有する正式例外。';
        }

        if (str_contains($rawNCode, '→')) {
            $notes[] = '変更表記は監査用に保持し、現状運用に合わせて変更前コードを採用。';
        }

        if (str_contains($rawSchoolName, '※') || str_contains($rawSchoolName, '＊')) {
            $notes[] = '学校名セルの注記は監査行へ保持し、掲載学校名は注記を除去して保存。';
        }

        if ($notes === []) {
            return null;
        }

        return implode(' ', $notes);
    }
}

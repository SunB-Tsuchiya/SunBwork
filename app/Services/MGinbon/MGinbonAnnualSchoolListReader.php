<?php

namespace App\Services\MGinbon;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class MGinbonAnnualSchoolListReader
{
    private const HEADERS = [
        'mikuni_code' => ['みくにコード'],
        'n_code' => ['日能研コード'],
        'alpha_group' => ['α版', 'α'],
        'school_name' => ['学校名'],
        'exam_session' => ['入試回掲載用', '入試回'],
    ];

    public function read(string $path): array
    {
        if (! is_file($path)) throw new RuntimeException('Excelファイルが見つかりません。');
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $book = $reader->load($path);
        try {
            $sheet = $book->getActiveSheet();
            $header = $this->resolveHeader($sheet);
            $rows = [];
            for ($number = $header['row'] + 1; $number <= $sheet->getHighestDataRow(); $number++) {
                $raw = [];
                foreach ($header['columns'] as $key => $column) $raw[$key] = $this->cell($sheet, $column, $number);
                if (trim(implode('', $raw)) === '') continue;
                $warnings = [];
                $mCode = $this->firstToken($raw['mikuni_code']);
                $nCode = $this->nCode($raw['n_code'], $warnings);
                $school = $this->firstLine($raw['school_name']);
                $exam = $this->firstLine($raw['exam_session']);
                if ($mCode === '') $warnings[] = 'Mコードがありません';
                if ($nCode === '') $warnings[] = 'Nコードがありません';
                if ($school === '') $warnings[] = '学校名がありません';
                if (preg_match('/[／\/]/u', $raw['n_code']) || preg_match('/／/u', $school)) $warnings[] = '複数値を確認してください';
                if (preg_match('/[※＊]/u', $school)) $warnings[] = '学校名の注記を確認してください';
                $rows[] = [
                    'source_row_number' => $number,
                    'mikuni_code' => $mCode,
                    'n_code' => $nCode,
                    'alpha_group' => preg_replace('/[[:space:]　]+/u', '', $raw['alpha_group']) ?? '',
                    'school_name' => $school,
                    'exam_session' => $exam,
                    'warnings' => array_values(array_unique($warnings)),
                    'raw' => $raw,
                ];
            }
            $this->markDuplicates($rows, 'mikuni_code', 'Mコードが重複しています');
            $this->markDuplicates($rows, 'n_code', 'Nコードが重複しています');
            return [
                'sheet' => $sheet->getTitle(),
                'rows' => $rows,
                'total' => count($rows),
                'alpha_total' => count(array_filter($rows, fn ($row) => $row['alpha_group'] !== '')),
                'warning_total' => count(array_filter($rows, fn ($row) => $row['warnings'] !== [])),
            ];
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function resolveHeader(Worksheet $sheet): array
    {
        $max = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        for ($row = 1; $row <= min(5, $sheet->getHighestDataRow()); $row++) {
            $columns = [];
            for ($column = 1; $column <= $max; $column++) {
                $name = $this->normalizeHeader($sheet->getCell([$column, $row])->getFormattedValue());
                foreach (self::HEADERS as $key => $aliases) {
                    if (in_array($name, $aliases, true)) $columns[$key] ??= Coordinate::stringFromColumnIndex($column);
                }
            }
            if (count($columns) === count(self::HEADERS)) return ['row' => $row, 'columns' => $columns];
        }
        throw new RuntimeException('固定5列のExcelヘッダーを確認できません。');
    }

    private function normalizeHeader(mixed $value): string
    {
        $value = mb_convert_kana((string) $value, 'asKV', 'UTF-8');
        return trim((string) preg_replace('/[[:space:]【】\[\]（）()]/u', '', str_replace(["\r", "\n"], '', $value)));
    }

    private function cell(Worksheet $sheet, string $column, int $row): string
    {
        return trim((string) $sheet->getCell($column.$row)->getFormattedValue());
    }

    private function firstLine(string $value): string
    {
        return trim(strtok(str_replace("\r", "\n", $value), "\n") ?: '');
    }

    private function firstToken(string $value): string
    {
        preg_match('/[0-9A-Za-z]+/u', mb_convert_kana($value, 'as', 'UTF-8'), $matches);
        return strtoupper($matches[0] ?? '');
    }

    private function nCode(string $value, array &$warnings): string
    {
        if (str_contains($value, '→')) {
            $parts = explode('→', mb_convert_kana($value, 'as', 'UTF-8'));
            $warnings[] = 'Nコード変更表記：右側を候補にしました';
            return $this->firstToken((string) end($parts));
        }
        return $this->firstToken($value);
    }

    private function markDuplicates(array &$rows, string $key, string $message): void
    {
        $counts = array_count_values(array_filter(array_column($rows, $key)));
        foreach ($rows as &$row) {
            if (($counts[$row[$key]] ?? 0) > 1) $row['warnings'][] = $message;
            $row['warnings'] = array_values(array_unique($row['warnings']));
        }
        unset($row);
    }
}

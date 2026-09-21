<?php

namespace App\Services\MGinbon;

use DateTimeImmutable;

class MGinbonLegacyRowNormalizer
{
    private const SUBJECTS = [
        'japanese' => '国語',
        'math' => '算数',
        'social' => '社会',
        'science' => '理科',
    ];

    private const ACTOR_PREFIXES = [
        'みくに戻りOP' => 'client_return_operation',
        '五校OP' => 'fifth_operation',
        '再_校正１' => 'reproof_scan_check',
        '再_校正２' => 'reproof_text_proof',
        '再校OP' => 'reproof_operation',
        '作図担当' => 'drawing',
        '三_校正' => 'third_proof',
        '三校OP' => 'third_operation',
        '四_校正' => 'fourth_proof',
        '四校OP' => 'fourth_operation',
        '初_チェック' => 'initial_check',
        '初_校正' => 'initial_text_proof',
        '初校OP' => 'initial_operation',
        '文字入力担当' => 'text_input',
    ];

    private const DATE_PREFIXES = [
        '五校出' => 'fifth_shared_on',
        '校了日' => 'completed_on',
        '再_校正１納品日' => 'reproof_scan_check_completed_on',
        '再_校正１発注日' => 'reproof_scan_check_started_on',
        '再_校正２納品日' => 'reproof_text_proof_completed_on',
        '再_校正２発注日' => 'reproof_text_proof_started_on',
        '再校出' => 'reproof_shared_on',
        '再校戻り' => 'reproof_returned_on',
        '作図納品日' => 'drawing_completed_on',
        '三校出' => 'third_shared_on',
        '三校戻り' => 'third_returned_on',
        '四校出' => 'fourth_shared_on',
        '四校戻り' => 'fourth_returned_on',
        '初_校正納品日' => 'initial_text_proof_completed_on',
        '初_校正発注日' => 'initial_text_proof_started_on',
        '初校出' => 'initial_shared_on',
        '初校戻り' => 'initial_returned_on',
        '入稿指定日' => 'manuscript_due_on',
        '入稿日' => 'manuscript_received_on',
        '文字入力納品日' => 'text_input_completed_on',
    ];

    private const MEASUREMENTS = [
        '外注scan数' => ['work_type' => 'scan', 'execution_type' => 'subcontracted'],
        '外注作図数' => ['work_type' => 'drawing', 'execution_type' => 'subcontracted'],
        '社内scan' => ['work_type' => 'scan', 'execution_type' => 'internal'],
        '社内作図数' => ['work_type' => 'drawing', 'execution_type' => 'internal'],
    ];

    /**
     * @param array<string, mixed> $row
     * @return array{data: array<string, mixed>, warnings: list<string>}
     */
    public function normalize(array $row): array
    {
        $warnings = [];
        $activeSubjects = $this->parseSubjects($row['科目'] ?? null);
        $subjects = [];

        foreach (self::SUBJECTS as $subjectCode => $label) {
            $subjects[$subjectCode] = [
                'label' => $label,
                'active' => in_array($label, $activeSubjects, true),
                'actors' => $this->actorsForSubject($row, $label),
                'dates' => $this->datesForSubject($row, $label, $warnings),
                'measurements' => $this->measurementsForSubject($row, $label, $warnings),
            ];
        }

        $originalScanDate = $this->dateValue($row['原本scan納品日_国語'] ?? null, '原本scan納品日_国語', $warnings);
        $originalReceivedDate = $this->dateValue($row['入稿日_問題原本'] ?? null, '入稿日_問題原本', $warnings);

        return [
            'data' => [
                'unit_type' => empty($row['みくにコード']) && empty($row['日能研コード']) ? 'book_component' : 'exam',
                'mikuni_code' => $row['みくにコード'] ?? null,
                'n_code' => $row['日能研コード'] ?? null,
                'display_name' => $row['学校名'] ?? null,
                'school_category' => $row['学校分類'] ?? null,
                'n_category' => $row['日能研分類'] ?? null,
                'media_type' => $row['媒体分類'] ?? null,
                'publication_status' => $row['銀本掲載'] ?? null,
                'note' => $row['メモ'] ?? null,
                'original_scan_provider_raw' => $row['原本scan担当'] ?? null,
                'shared_dates' => [
                    'original_scan_completed_on' => $originalScanDate,
                    'original_received_on' => $originalReceivedDate,
                ],
                'subjects' => $subjects,
            ],
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** @return list<string> */
    private function parseSubjects(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $parts = preg_split('/[・,、\s]+/u', trim($value)) ?: [];

        return array_values(array_intersect(array_values(self::SUBJECTS), $parts));
    }

    /** @param array<string, mixed> $row */
    private function actorsForSubject(array $row, string $subject): array
    {
        $actors = [];
        foreach (self::ACTOR_PREFIXES as $prefix => $stageCode) {
            $value = $row["{$prefix}_{$subject}"] ?? null;
            if ($value !== null && $value !== '') {
                $actors[$stageCode] = ['raw_value' => $value, 'resolution' => 'unresolved'];
            }
        }

        return $actors;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $warnings
     */
    private function datesForSubject(array $row, string $subject, array &$warnings): array
    {
        $dates = [];
        foreach (self::DATE_PREFIXES as $prefix => $stageCode) {
            $field = "{$prefix}_{$subject}";
            if (array_key_exists($field, $row)) {
                $dates[$stageCode] = $this->dateValue($row[$field], $field, $warnings);
            }
        }

        return array_filter($dates, fn ($value) => $value !== null);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $warnings
     */
    private function measurementsForSubject(array $row, string $subject, array &$warnings): array
    {
        $measurements = [];
        foreach (self::MEASUREMENTS as $prefix => $definition) {
            $field = "{$prefix}_{$subject}";
            $raw = $row[$field] ?? null;
            $quantity = $raw === null || $raw === ''
                ? 0
                : (preg_match('/^\d+$/', (string) $raw) ? (int) $raw : null);
            if ($quantity === null || $quantity < 0) {
                $warnings[] = "invalid_quantity:{$field}";
                $quantity = null;
            }

            $measurements[] = [...$definition, 'quantity' => $quantity, 'raw_value' => $raw];
        }

        return $measurements;
    }

    /** @param list<string> $warnings */
    private function dateValue(mixed $raw, string $field, array &$warnings): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $value = trim((string) $raw);
        if (! preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            $warnings[] = "invalid_date:{$field}";

            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y/n/j', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            $warnings[] = "invalid_date:{$field}";

            return null;
        }

        if (strlen($matches[2]) !== 2 || strlen($matches[3]) !== 2) {
            $warnings[] = "non_padded_date:{$field}";
        }

        return $date->format('Y-m-d');
    }
}

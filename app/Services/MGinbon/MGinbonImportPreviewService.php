<?php

namespace App\Services\MGinbon;

class MGinbonImportPreviewService
{
    public function __construct(private readonly FileMakerMergeReader $reader) {}

    /**
     * @return array<string, int|string|array<string, int>>
     */
    public function preview(string $path): array
    {
        $result = $this->reader->read($path);
        $headers = $result['headers'];
        $requiredHeaders = ['みくにコード', '日能研コード', '学校名', '媒体分類'];
        $missingHeaders = array_values(array_diff($requiredHeaders, $headers));
        if ($missingHeaders !== []) {
            throw new \RuntimeException('必須ヘッダーがありません: '.implode(', ', $missingHeaders));
        }
        $counts = [
            'records' => 0,
            'school_records' => 0,
            'book_component_records' => 0,
            'missing_both_codes' => 0,
            'duplicate_identity_candidates' => 0,
        ];
        $identities = [];

        foreach ($result['rows'] as $row) {
            $counts['records']++;
            $mikuniCode = $this->first($row, ['みくにコード', 'みくにcode']);
            $nCode = $this->first($row, ['日能研コード', '日能研code']);
            $schoolName = $this->first($row, ['学校名']);
            $mediaType = $this->first($row, ['媒体分類']);

            if ($mikuniCode === null && $nCode === null) {
                $counts['missing_both_codes']++;
                $counts['book_component_records']++;
            } else {
                $counts['school_records']++;
            }

            $identity = implode('|', [
                $mikuniCode ?? '', $nCode ?? '', $schoolName ?? '', $mediaType ?? '',
            ]);
            $identities[$identity] = ($identities[$identity] ?? 0) + 1;
        }

        foreach ($identities as $occurrences) {
            if ($occurrences > 1) {
                $counts['duplicate_identity_candidates'] += $occurrences - 1;
            }
        }

        return [
            'source_file' => basename($path),
            'sha256' => hash_file('sha256', $path),
            'columns' => count($headers),
            ...$counts,
        ];
    }

    /** @param array<string, string|null> $row */
    private function first(array $row, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $row)) {
                return $row[$candidate];
            }
        }

        return null;
    }
}

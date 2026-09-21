<?php

namespace App\Services\MGinbon;

use Generator;
use RuntimeException;

class FileMakerMergeReader
{
    /**
     * @return array{headers: list<string>, rows: Generator<int, array<string, string|null>>}
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Mergeファイルを読み取れません: {$path}");
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Mergeファイルを開けません: {$path}");
        }

        $headers = fgetcsv($handle, null, ',', '"', '');
        if ($headers === false) {
            fclose($handle);
            throw new RuntimeException('Mergeファイルにヘッダーがありません。');
        }

        $headers = array_map(fn ($value) => $this->normalize((string) $value), $headers);
        if ($headers !== array_values(array_unique($headers))) {
            fclose($handle);
            throw new RuntimeException('Mergeファイルのヘッダー名が重複しています。');
        }

        return [
            'headers' => $headers,
            'rows' => $this->rows($handle, $headers),
        ];
    }

    /**
     * @param resource $handle
     * @param list<string> $headers
     * @return Generator<int, array<string, string|null>>
     */
    private function rows($handle, array $headers): Generator
    {
        try {
            $rowNumber = 1;
            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;
                if (count($values) === 1 && $values[0] === null) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw new RuntimeException(
                        "Mergeファイル{$rowNumber}行目の列数が不正です（期待: ".count($headers).', 実際: '.count($values).'）。'
                    );
                }

                $normalized = array_map(fn ($value) => $this->normalizeNullable($value), $values);
                yield $rowNumber => array_combine($headers, $normalized);
            }
        } finally {
            fclose($handle);
        }
    }

    private function normalizeNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalize((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalize(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        if (! mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'SJIS-win');
        }

        return str_replace(["\r\n", "\r"], "\n", $value);
    }
}

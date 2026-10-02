<?php

namespace Tests\Unit\MGinbon;

use App\Services\MGinbon\MGinbonFilenameBulkService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MGinbonFilenameBulkServiceTest extends TestCase
{
    #[DataProvider('filenames')]
    public function test_it_parses_fixed_filename_codes(string $filename, string $media, string $subject): void
    {
        $parsed = (new MGinbonFilenameBulkService)->parse($filename);

        $this->assertSame('3081', $parsed['n_code']);
        $this->assertSame(2026, $parsed['year']);
        $this->assertSame($media, $parsed['media_name']);
        $this->assertSame($subject, $parsed['subject_name']);
    }

    public static function filenames(): array
    {
        return [
            ['30812026__QKo.pdf', '問題', '国語'],
            ['30812026__ASa.PDF', '解答のみ', '算数'],
            ['C:\\incoming\\30812026__AASh.pdf', '解説解答', '社会'],
            ['/tmp/30812026__YRi.pdf', '解答用紙', '理科'],
            ['30812026__TKo.pdf', '傾向と対策', '国語'],
        ];
    }

    public function test_it_rejects_old_problem_symbol_and_partial_names(): void
    {
        $service = new MGinbonFilenameBulkService;
        $this->assertNull($service->parse('30812026__mKo.pdf'));
        $this->assertNull($service->parse('copy_30812026__QKo.pdf'));
        $this->assertNull($service->parse('30812026__AXx.pdf'));
    }
}

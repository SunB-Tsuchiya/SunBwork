<?php

namespace Tests\Unit\NSystem;

use App\Services\NSystem\NQuestionSearchService;
use PHPUnit\Framework\TestCase;

class NQuestionSearchServiceTest extends TestCase
{
    private NQuestionSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NQuestionSearchService;
    }

    public function test_exact_mode_keeps_the_query_as_one_term(): void
    {
        $this->assertSame(['平安 時代'], $this->service->terms('平安 時代', 'exact'));
    }

    public function test_keyword_modes_split_and_deduplicate_terms(): void
    {
        $this->assertSame(['平安', '時代'], $this->service->terms('平安　時代 平安', 'all'));
    }

    public function test_like_wildcards_are_escaped_as_literals(): void
    {
        $this->assertSame('100!%!_!!', $this->service->escapeLike('100%_!'));
    }

    public function test_snippet_is_centered_on_a_multibyte_match(): void
    {
        $text = str_repeat('前', 120) . '平安時代' . str_repeat('後', 120);
        $snippet = $this->service->snippet($text, ['平安時代'], 20);

        $this->assertSame(str_repeat('前', 20), $snippet['before']);
        $this->assertSame('平安時代', $snippet['match']);
        $this->assertSame(str_repeat('後', 20), $snippet['after']);
        $this->assertTrue($snippet['leading_ellipsis']);
        $this->assertTrue($snippet['trailing_ellipsis']);
    }

    public function test_snippet_uses_the_earliest_matching_term(): void
    {
        $snippet = $this->service->snippet('江戸時代の後に明治時代が続く', ['明治時代', '江戸時代'], 10);

        $this->assertSame('江戸時代', $snippet['match']);
        $this->assertFalse($snippet['leading_ellipsis']);
    }
}

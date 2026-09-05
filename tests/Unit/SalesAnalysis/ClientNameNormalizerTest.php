<?php

namespace Tests\Unit\SalesAnalysis;

use App\Services\SalesAnalysis\ClientNameNormalizer;
use PHPUnit\Framework\TestCase;

class ClientNameNormalizerTest extends TestCase
{
    public function test_full_width_and_half_width_spaces_are_removed()
    {
        $this->assertSame(
            ClientNameNormalizer::normalize('株式会社サンプル'),
            ClientNameNormalizer::normalize('株式会社 サンプル')
        );
        $this->assertSame(
            ClientNameNormalizer::normalize('株式会社サンプル'),
            ClientNameNormalizer::normalize('株式会社　サンプル')
        );
    }

    public function test_half_width_katakana_is_converted_to_full_width()
    {
        $this->assertSame(
            ClientNameNormalizer::normalize('株式会社サンプル'),
            ClientNameNormalizer::normalize('株式会社ｻﾝﾌﾟﾙ')
        );
    }

    public function test_full_width_alphanumeric_is_converted_to_half_width()
    {
        $this->assertSame(
            ClientNameNormalizer::normalize('ABC商事123'),
            ClientNameNormalizer::normalize('ＡＢＣ商事１２３')
        );
    }

    public function test_bracket_width_is_unified_without_removing_contents()
    {
        $this->assertSame(
            ClientNameNormalizer::normalize('A商事(東京)'),
            ClientNameNormalizer::normalize('A商事（東京）')
        );
    }

    public function test_different_bracket_contents_are_not_treated_as_identical()
    {
        // PLAN 2.7: 括弧内の区分が違う名称を勝手に統合しない（正規化候補も別扱いのままにする）
        $this->assertNotSame(
            ClientNameNormalizer::normalize('A商事(東京)'),
            ClientNameNormalizer::normalize('A商事(大阪)')
        );
    }

    public function test_legal_entity_notation_is_not_stripped()
    {
        // 株式会社の有無は自動で同一視しない（別法人の可能性があるため）
        $this->assertNotSame(
            ClientNameNormalizer::normalize('サンプル工業'),
            ClientNameNormalizer::normalize('株式会社サンプル工業')
        );
    }
}

<?php

namespace Tests\Unit\SalesAnalysis;

use App\Services\SalesAnalysis\ProductNameNormalizer;
use PHPUnit\Framework\TestCase;

class ProductNameNormalizerTest extends TestCase
{
    public function test_year_with_nendo_you_suffix_is_removed()
    {
        $this->assertSame(
            ProductNameNormalizer::normalize('2026年度用中学入試問題集組版代 銀本α版通常校データ'),
            ProductNameNormalizer::normalize('2027年度用中学入試問題集組版代 銀本α版通常校データ')
        );
    }

    public function test_year_with_nendo_suffix_is_removed()
    {
        $this->assertSame(
            ProductNameNormalizer::normalize('2025年度前期テキスト WEB教室版 制作代'),
            ProductNameNormalizer::normalize('2026年度前期テキスト WEB教室版 制作代')
        );
    }

    public function test_year_with_nen_suffix_is_removed()
    {
        $this->assertSame(
            ProductNameNormalizer::normalize('2025年夏期特別講習テスト 3.4.5.6年 制作代'),
            ProductNameNormalizer::normalize('2026年夏期特別講習テスト 3.4.5.6年 制作代')
        );
    }

    public function test_bare_year_with_no_suffix_is_removed()
    {
        $this->assertSame(
            ProductNameNormalizer::normalize('2025夏期特別講習テスト 3.4.5.6年 制作代'),
            ProductNameNormalizer::normalize('2026夏期特別講習テスト 3.4.5.6年 制作代')
        );
    }

    public function test_trailing_year_is_removed()
    {
        $this->assertSame(
            ProductNameNormalizer::normalize('鶴見大学附属中学高等学校 中学校案内 2026'),
            ProductNameNormalizer::normalize('鶴見大学附属中学高等学校 中学校案内 2027')
        );
    }

    public function test_grade_level_digits_are_not_removed()
    {
        // 「3.4.5.6年」は学年（1桁）であり、2000〜2040年の範囲外なので除去されない
        $this->assertStringContainsString('3.4.5.6年', ProductNameNormalizer::normalize('2026年夏期特別講習テスト 3.4.5.6年 制作代'));
    }

    public function test_three_digit_number_is_not_removed()
    {
        // 「150周年」の150は2000〜2040の範囲外なので除去されない
        $this->assertStringContainsString('150周年', ProductNameNormalizer::normalize('日本医科大学 150周年 記念誌・DVDケース・ブックケース 制作代'));
    }

    public function test_different_products_are_not_treated_as_identical()
    {
        $this->assertNotSame(
            ProductNameNormalizer::normalize('2026年度用中学入試問題集組版代 銀本α版通常校データ'),
            ProductNameNormalizer::normalize('2026年度前期テキスト WEB教室版 制作代')
        );
    }
}

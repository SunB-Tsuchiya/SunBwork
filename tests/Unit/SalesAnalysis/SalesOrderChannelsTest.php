<?php

namespace Tests\Unit\SalesAnalysis;

use App\Models\Company;
use App\Services\SalesAnalysis\SalesOrderChannels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderChannelsTest extends TestCase
{
    use RefreshDatabase;

    private const LABELS = ['企画', '制作', 'オンデマンド'];

    public function test_parses_monthly_standard_filename()
    {
        $result = SalesOrderChannels::parseFilename('企画_2026年08月.xlsx', self::LABELS);

        $this->assertSame([
            'department_label' => '企画',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 8,
            'source_month_end' => null,
            'order_channel' => 'standard',
        ], $result);
    }

    public function test_parses_monthly_direct_filename()
    {
        $result = SalesOrderChannels::parseFilename('企画_2026年08月_独自.xlsx', self::LABELS);

        $this->assertSame('direct', $result['order_channel']);
        $this->assertSame('monthly', $result['source_type']);
        $this->assertSame(8, $result['source_month']);
    }

    public function test_parses_annual_filename_and_direct_variant()
    {
        $standard = SalesOrderChannels::parseFilename('制作_2025年.xlsx', self::LABELS);
        $direct = SalesOrderChannels::parseFilename('制作_2025年_独自.xlsx', self::LABELS);

        $this->assertSame('annual', $standard['source_type']);
        $this->assertNull($standard['source_month']);
        $this->assertSame('standard', $standard['order_channel']);

        $this->assertSame('annual', $direct['source_type']);
        $this->assertSame('direct', $direct['order_channel']);
    }

    public function test_parses_range_filename_and_direct_variant()
    {
        $standard = SalesOrderChannels::parseFilename('オンデマンド_2026年01-06月.xlsx', self::LABELS);
        $direct = SalesOrderChannels::parseFilename('オンデマンド_2026年01-06月_独自.xlsx', self::LABELS);

        $this->assertSame('range', $standard['source_type']);
        $this->assertSame(1, $standard['source_month']);
        $this->assertSame(6, $standard['source_month_end']);
        $this->assertSame('standard', $standard['order_channel']);

        $this->assertSame('direct', $direct['order_channel']);
    }

    /** @dataProvider malformedFilenameProvider */
    public function test_rejects_malformed_filenames(string $filename)
    {
        $this->assertNull(SalesOrderChannels::parseFilename($filename, self::LABELS));
    }

    public static function malformedFilenameProvider(): array
    {
        return [
            'wrong suffix wording' => ['企画_2026年08月_独自版.xlsx'],
            'extra space before suffix' => ['企画_2026年08月_ 独自.xlsx'],
            'parenthesized suffix' => ['企画_2026年08月（独自）.xlsx'],
            'wrong extension' => ['企画_2026年08月.xls'],
            'unknown department label' => ['情報出版_2026年08月.xlsx'],
            'missing underscore' => ['企画2026年08月.xlsx'],
            'trailing whitespace' => ['企画_2026年08月.xlsx '],
            // Codexレビュー指摘（2026-09-07）: 正規表現は\d{1,2}のため1〜99を受理してしまう。
            // 実在しない月・開始>終了の範囲はここで拒否する
            'month zero' => ['企画_2026年00月.xlsx'],
            'month thirteen' => ['企画_2026年13月.xlsx'],
            'range end month out of bounds' => ['企画_2026年01-99月.xlsx'],
            'range start month out of bounds' => ['企画_2026年00-06月.xlsx'],
            'range start after end' => ['企画_2026年08-03月.xlsx'],
        ];
    }

    public function test_supports_channels_for_returns_true_only_for_sunbrain_code()
    {
        // company_idごとにキャッシュされるため、companyIdを跨いで一度ずつだけ検証する
        // （同一companyIdをcode更新の前後で問い合わせるとキャッシュにより誤判定するため避ける）
        $other = Company::create(['name' => '他社', 'code' => 'OTHER_' . uniqid(), 'company_type' => 'general', 'active' => true]);
        $this->assertFalse(SalesOrderChannels::supportsChannelsFor($other->id));

        $sunbrain = Company::create(['name' => 'サン・ブレーン', 'code' => 'SUNBRAIN', 'company_type' => 'sunbrain', 'active' => true]);
        $this->assertTrue(SalesOrderChannels::supportsChannelsFor($sunbrain->id));
    }
}

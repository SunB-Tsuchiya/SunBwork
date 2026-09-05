<?php

namespace Tests\Feature\SalesAnalysis;

use App\Models\Sales\SalesActiveMonth;
use App\Models\Sales\SalesImport;
use App\Models\Sales\SalesOrder;
use App\Services\SalesAnalysis\SalesImportValidator;
use App\Services\SalesAnalysis\SalesWorkbookReader;
use Tests\Concerns\BuildsSalesWorkbook;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\TestCase;

class SalesImportValidatorTest extends TestCase
{
    use RefreshesSalesDatabase;
    use BuildsSalesWorkbook;

    private function validator(): SalesImportValidator
    {
        return new SalesImportValidator(new SalesWorkbookReader());
    }

    /** invalid_orders内の全エラーメッセージをフラットな配列にする（受注単位エラーの検証用） */
    private function flattenInvalidOrderErrors(array $result): array
    {
        if (empty($result['invalid_orders'])) {
            return [];
        }

        return array_merge(...array_map(fn ($o) => $o['errors'], $result['invalid_orders']));
    }

    /**
     * サンプル（架空データ）は途中で切られた不完全なデータであり、
     * 受注No 4602841 で M合計とN合計が意図的に不一致になっている。
     * 本番データではこの状態は起こり得ないため、検証は正しくエラーを返すべき。
     */
    public function test_sample_workbook_is_rejected_for_amount_mismatch()
    {
        $path = base_path('tests/Fixtures/SalesAnalysis/sanbrain_meisai_sample.xlsx');

        $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 8);

        $this->assertFalse($result['valid']);
        $this->assertNotEmpty(array_filter(
            $this->flattenInvalidOrderErrors($result),
            fn ($e) => str_contains($e, '4602841')
        ));
    }

    public function test_valid_workbook_produces_correct_orders_and_summary()
    {
        $rows = [
            $this->row('1000001', 'A社', '商品A', 10000, 10000, '2026/09/05'),
            // 複数明細の受注: 途中行は受注金額0、最終行に合計
            $this->row('1000002', 'B社', '商品B', 3000, 0, '2026/09/10'),
            $this->row('1000002', 'B社', '商品B', 2000, 5000, '2026/09/10'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $this->assertSame(2, $result['summary']['order_count']);
            $this->assertSame(3, $result['summary']['detail_count']);
            $this->assertSame(15000.0, $result['summary']['total_amount']);

            $order2 = collect($result['orders'])->firstWhere('order_number', '1000002');
            $this->assertSame(5000.0, $order2['order_amount']);
            $this->assertCount(2, $order2['details']);
        } finally {
            @unlink($path);
        }
    }

    public function test_half_width_katakana_department_label_is_accepted()
    {
        $rows = [$this->row('2000005', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        // 帳票ソフトの出力で半角カナ「ｵﾝﾃﾞﾏﾝﾄﾞ」になっているファイルが実在する
        $path = $this->makeSalesWorkbook($this->monthlyTitle('ｵﾝﾃﾞﾏﾝﾄﾞ', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'ondemand', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
        } finally {
            @unlink($path);
        }
    }

    public function test_department_label_mismatch_is_rejected()
    {
        $rows = [$this->row('2000001', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        // タイトルは「制作」なのに department_key は planning（企画）で検証する
        $path = $this->makeSalesWorkbook($this->monthlyTitle('制作', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
        } finally {
            @unlink($path);
        }
    }

    public function test_row_month_mismatch_is_rejected()
    {
        $rows = [$this->row('2000002', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            // 行のSB下版日は9月だが、フォーム指定は10月 → 行データとの不一致でエラー。
            // タイトル行の年月は出力側の設定で常に開始月固定のため検証には使わない
            // （半期でも年次でも常に「1月」等と表示され得るため）。
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 10);

            $this->assertFalse($result['valid']);
        } finally {
            @unlink($path);
        }
    }

    public function test_negative_amount_single_row_order_is_now_allowed()
    {
        // 2026-09-04変更: 以前は受注金額（N列）の負数を一律拒否していたが、事故・刷り直し等で
        // 受注全体がマイナスになるケースを許容する方針に変更した（単価・金額は2026-09-03から許容済み）
        $rows = [$this->row('2000003', 'A社', '商品A', -1000, -1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']) . implode(' / ', $this->flattenInvalidOrderErrors($result)));
            $this->assertSame(-1000.0, $result['orders'][0]['order_amount']);
        } finally {
            @unlink($path);
        }
    }

    public function test_annual_workbook_derives_month_from_plate_date()
    {
        $rows = [
            $this->row('3000001', 'A社', '商品A', 1000, 1000, '2026/04/10'),
            $this->row('3000002', 'A社', '商品B', 2000, 2000, '2026/12/20'),
        ];
        $path = $this->makeSalesWorkbook($this->annualTitle('企画', 2026), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'annual', 2026, null);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $order1 = collect($result['orders'])->firstWhere('order_number', '3000001');
            $order2 = collect($result['orders'])->firstWhere('order_number', '3000002');
            $this->assertSame(4, $order1['sales_month']);
            $this->assertSame(12, $order2['sales_month']);
        } finally {
            @unlink($path);
        }
    }

    public function test_range_workbook_accepts_rows_within_month_span()
    {
        $rows = [
            $this->row('6000001', 'A社', '商品A', 1000, 1000, '2026/01/10'),
            $this->row('6000002', 'A社', '商品B', 2000, 2000, '2026/06/20'),
        ];
        // タイトルには開始月（1月）のみが記載される実仕様
        $path = $this->makeSalesWorkbook($this->rangeTitle('企画', 2026, 1), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'range', 2026, 1, 6);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $order1 = collect($result['orders'])->firstWhere('order_number', '6000001');
            $order2 = collect($result['orders'])->firstWhere('order_number', '6000002');
            $this->assertSame(1, $order1['sales_month']);
            $this->assertSame(6, $order2['sales_month']);
        } finally {
            @unlink($path);
        }
    }

    public function test_range_workbook_rejects_row_outside_month_span()
    {
        $rows = [
            $this->row('6000003', 'A社', '商品A', 1000, 1000, '2026/07/10'), // 範囲(1-6月)外
        ];
        $path = $this->makeSalesWorkbook($this->rangeTitle('企画', 2026, 1), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'range', 2026, 1, 6);

            $this->assertFalse($result['valid']);
        } finally {
            @unlink($path);
        }
    }

    public function test_range_workbook_ignores_title_start_month_mismatch()
    {
        $rows = [$this->row('6000004', 'A社', '商品A', 1000, 1000, '2026/03/10')];
        // タイトルは2月開始と記載されているが、フォーム指定は3月開始（範囲3-6月）。
        // 出力側の設定でタイトルの月は常に不正確なため検証には使わない。行データ(3月)が
        // フォーム指定範囲内であれば、タイトルの開始月と食い違っていても有効とする。
        $path = $this->makeSalesWorkbook($this->rangeTitle('企画', 2026, 2), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'range', 2026, 3, 6);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
        } finally {
            @unlink($path);
        }
    }

    public function test_order_number_already_active_in_another_month_is_rejected()
    {
        $import = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 8,
            'version' => 1,
            'original_filename' => 'existing.xlsx',
            'file_sha256' => str_repeat('a', 64),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => 1000,
        ]);
        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => '4000001',
            'client_name' => 'A社',
            'product_name' => '商品A',
            'plate_date' => '2026-08-15',
            'sales_year' => 2026,
            'sales_month' => 8,
            'order_amount' => 1000,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2026,
            'sales_month' => 8,
            'sales_import_id' => $import->id,
            'activated_by' => 1,
            'activated_at' => now(),
        ]);

        // 同じ受注Noを別月（9月）で取り込もうとする
        $rows = [$this->row('4000001', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $this->assertNotEmpty(array_filter(
                $this->flattenInvalidOrderErrors($result),
                fn ($e) => str_contains($e, '4000001')
            ));
        } finally {
            @unlink($path);
        }
    }

    public function test_optional_fields_blank_do_not_block_import()
    {
        $row = $this->row('8000001', 'A社', '商品A', 1000, 1000, '2026/09/05');
        // 得意先名・品名・分類・項目・判型・色数・台数・単価はすべて空欄でも取込を継続する
        // （Codexレビュー6.2 High-1: 空欄のある行を丸ごと除外していた問題への対応）
        $row['client_name'] = null;
        $row['product_name'] = null;
        $row['category'] = null;
        $row['item_name'] = null;
        $row['format_size'] = null;
        $row['color_count'] = null;
        $row['quantity'] = null;
        $row['unit_price'] = null;
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), [$row]);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $this->assertSame(1, $result['summary']['order_count']);
            $order = $result['orders'][0];
            $this->assertNull($order['client_name']);
            $this->assertNull($order['product_name']);
            $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, '得意先名が空欄')));
            // 項目・判型・分類の空欄はデジタル案件で常態化しているため警告も出さない
            $this->assertEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, '項目が空欄') || str_contains($w, '判型が空欄') || str_contains($w, '分類が空欄')));
        } finally {
            @unlink($path);
        }
    }

    public function test_middle_row_null_order_amount_component_is_treated_as_zero()
    {
        $rows = [
            $this->row('8000002', 'A社', '商品A', 1000, 0, '2026/09/05'),
            $this->row('8000002', 'A社', '商品A', 2000, 0, '2026/09/05'),
            $this->row('8000002', 'A社', '商品A', 1500, 4500, '2026/09/05'),
        ];
        // 途中行の受注金額（N列）が未入力（NULL）でも0として扱い、最終行の正値のみで確定する
        $rows[1]['order_amount_component'] = null;
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $order = $result['orders'][0];
            $this->assertSame(4500.0, $order['order_amount']);
            $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, '受注金額（N列）が空欄')));
        } finally {
            @unlink($path);
        }
    }

    public function test_zero_order_amount_component_is_rejected_with_detailed_counts()
    {
        $rows = [
            $this->row('8000003', 'A社', '商品A', 1000, 0, '2026/09/05'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $errors = $this->flattenInvalidOrderErrors($result);
            // 2026-09-04変更: 「正の値がありません」ではなく空欄/0円の内訳が分かるメッセージにする
            $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, '0以外の値（正または負）がありません') && str_contains($e, '0円1行')));
        } finally {
            @unlink($path);
        }
    }

    public function test_null_order_amount_component_shows_blank_count_in_error()
    {
        // ユーザー報告（受注No 4304133相当）: N列が空欄（NULL）で関連行も無い孤立データのケース。
        // 「正の値がありません」だけでは空欄なのか0円なのか区別できなかったため詳細化した
        $row = $this->row('4304133', 'A社', '商品A', 1000, 0, '2026/09/05');
        $row['order_amount_component'] = null;
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), [$row]);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $errors = $this->flattenInvalidOrderErrors($result);
            $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, '空欄1行') && str_contains($e, '4304133')));
        } finally {
            @unlink($path);
        }
    }

    public function test_multiple_nonzero_order_amount_components_is_rejected()
    {
        $rows = [
            $this->row('8000004', 'A社', '商品A', 1000, 1000, '2026/09/05'),
            $this->row('8000004', 'A社', '商品A', 2000, 2000, '2026/09/05'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $this->assertNotEmpty(array_filter($this->flattenInvalidOrderErrors($result), fn ($e) => str_contains($e, '0以外の値を持つ行が複数')));
        } finally {
            @unlink($path);
        }
    }

    public function test_negative_order_amount_in_last_row_is_now_allowed()
    {
        // 2026-09-04変更（ユーザー確認）: 事故・刷り直し等で受注全体の合計がマイナスになるケースを許容する
        $rows = [
            $this->row('8000020', 'A社', '商品A', 1000, 0, '2026/09/05'),
            $this->row('8000020', 'A社', '商品A', -1500, -500, '2026/09/05'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']) . implode(' / ', $this->flattenInvalidOrderErrors($result)));
            $order = $result['orders'][0];
            $this->assertSame(-500.0, $order['order_amount']);
        } finally {
            @unlink($path);
        }
    }

    public function test_negative_order_amount_not_in_last_row_is_still_rejected()
    {
        $rows = [
            $this->row('8000021', 'A社', '商品A', -500, -500, '2026/09/05'),
            $this->row('8000021', 'A社', '商品A', 500, 0, '2026/09/05'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $errors = $this->flattenInvalidOrderErrors($result);
            $this->assertNotEmpty(array_filter($errors, fn ($e) => str_contains($e, '負の値') && str_contains($e, '最後の行にありません')));
        } finally {
            @unlink($path);
        }
    }

    public function test_positive_order_amount_component_not_in_last_row_is_rejected()
    {
        $rows = [
            $this->row('8000005', 'A社', '商品A', 1000, 1000, '2026/09/05'),
            $this->row('8000005', 'A社', '商品A', 2000, 0, '2026/09/05'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $this->assertNotEmpty(array_filter($this->flattenInvalidOrderErrors($result), fn ($e) => str_contains($e, '最後の行にありません')));
        } finally {
            @unlink($path);
        }
    }

    public function test_amount_mismatch_between_line_and_order_is_warning_not_error()
    {
        // M列合計（1000）とN列受注金額（1500）が食い違う → エラーにせず未配賦額として警告する
        $rows = [
            $this->row('8000006', 'A社', '商品A', 1000, 1500, '2026/09/05'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']));
            $order = $result['orders'][0];
            $this->assertSame(500.0, $order['unallocated_amount']);
            $this->assertSame(500.0, $result['summary']['total_unallocated_amount']);
            $this->assertNotEmpty(array_filter($result['warnings'], fn ($w) => str_contains($w, '未配賦額')));
        } finally {
            @unlink($path);
        }
    }

    public function test_negative_line_amount_and_unit_price_are_allowed()
    {
        // 事故損金等の値引き・調整行を想定。金額（M列）・単価がマイナスでも受注金額（N列）は正しく計算できる
        $row = $this->row('8000010', 'A社', '商品A', -500, 500, '2026/09/05');
        $row['unit_price'] = -500;
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), [$row]);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertTrue($result['valid'], implode(' / ', $result['errors']) . implode(' / ', $this->flattenInvalidOrderErrors($result)));
            $order = $result['orders'][0];
            $this->assertSame(500.0, $order['order_amount']);
            $this->assertSame(1000.0, $order['unallocated_amount']);
        } finally {
            @unlink($path);
        }
    }

    public function test_negative_quantity_is_still_rejected()
    {
        $row = $this->row('8000011', 'A社', '商品A', 1000, 1000, '2026/09/05');
        $row['quantity'] = -1;
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), [$row]);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);

            $this->assertFalse($result['valid']);
            $this->assertNotEmpty(array_filter($this->flattenInvalidOrderErrors($result), fn ($e) => str_contains($e, '台数が負数')));
        } finally {
            @unlink($path);
        }
    }

    public function test_excluding_invalid_order_allows_remaining_orders_to_import()
    {
        $rows = [
            $this->row('8000012', 'A社', '商品A', 1000, 1000, '2026/09/05'), // 正常
            $this->row('8000013', 'B社', '商品B', 2000, 0, '2026/09/05'), // N列に正の値がない＝Excel側のエラー
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $first = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9);
            $this->assertFalse($first['valid']);
            $this->assertCount(1, $first['invalid_orders']);
            $this->assertSame('8000013', $first['invalid_orders'][0]['order_number']);

            $second = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9, null, ['8000013']);
            $this->assertTrue($second['valid'], implode(' / ', $this->flattenInvalidOrderErrors($second)));
            $this->assertCount(1, $second['orders']);
            $this->assertSame('8000012', $second['orders'][0]['order_number']);
            $this->assertSame(['8000013'], $second['excluded_orders']);
            $this->assertNotEmpty(array_filter($second['warnings'], fn ($w) => str_contains($w, '8000013')));
        } finally {
            @unlink($path);
        }
    }

    public function test_excluding_a_normal_order_is_rejected()
    {
        // 8000015は検証エラーの無い正常な受注。除外指定しても検証をスキップさせて
        // 不正に取込対象外にはできない（Codexレビュー2回目 High-1対応）
        $rows = [$this->row('8000015', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9, null, ['8000015']);

            $this->assertFalse($result['valid']);
            $this->assertEmpty($result['excluded_orders']);
            $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, '除外対象として認められない受注No') && str_contains($e, '8000015')));
        } finally {
            @unlink($path);
        }
    }

    public function test_excluding_a_nonexistent_order_number_is_rejected()
    {
        $rows = [$this->row('8000016', 'A社', '商品A', 1000, 1000, '2026/09/05')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            // ファイルに存在しない受注No（誤入力・別ファイルの番号混入等）を除外指定した場合も拒否する
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9, null, ['9999999']);

            $this->assertFalse($result['valid']);
            $this->assertEmpty($result['excluded_orders']);
            $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, '除外対象として認められない受注No') && str_contains($e, '9999999')));
        } finally {
            @unlink($path);
        }
    }

    public function test_excluding_cross_month_duplicate_order_is_allowed()
    {
        $import = SalesImport::create([
            'department_key' => 'planning',
            'source_type' => 'monthly',
            'source_year' => 2026,
            'source_month' => 8,
            'version' => 1,
            'original_filename' => 'existing.xlsx',
            'file_sha256' => str_repeat('b', 64),
            'status' => 'completed',
            'imported_by' => 1,
            'imported_at' => now(),
            'order_count' => 1,
            'detail_count' => 1,
            'total_amount' => 1000,
        ]);
        SalesOrder::create([
            'sales_import_id' => $import->id,
            'order_number' => '8000017',
            'client_name' => 'A社',
            'product_name' => '商品A',
            'plate_date' => '2026-08-15',
            'sales_year' => 2026,
            'sales_month' => 8,
            'order_amount' => 1000,
        ]);
        SalesActiveMonth::create([
            'department_key' => 'planning',
            'sales_year' => 2026,
            'sales_month' => 8,
            'sales_import_id' => $import->id,
            'activated_by' => 1,
            'activated_at' => now(),
        ]);

        // 同じ受注Noを別月（9月）で取り込もうとする（他月重複エラー）
        $rows = [
            $this->row('8000017', 'A社', '商品A', 1000, 1000, '2026/09/05'),
            $this->row('8000018', 'B社', '商品B', 1000, 1000, '2026/09/05'),
        ];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), $rows);

        try {
            // 他月重複エラーの受注も、通常のinvalid_orders同様に除外対象として認められる
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9, null, ['8000017']);

            $this->assertTrue($result['valid'], implode(' / ', $this->flattenInvalidOrderErrors($result)));
            $this->assertSame(['8000017'], $result['excluded_orders']);
            $this->assertCount(1, $result['orders']);
            $this->assertSame('8000018', $result['orders'][0]['order_number']);
        } finally {
            @unlink($path);
        }
    }

    public function test_row_with_unreadable_order_number_still_blocks_entire_file_even_with_exclusions()
    {
        $row = $this->row('8000014', 'A社', '商品A', 1000, 1000, '2026/09/05');
        $row['order_number'] = null;
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 9), [$row]);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 9, null, ['8000014']);

            $this->assertFalse($result['valid']);
            $this->assertNotEmpty(array_filter($result['errors'], fn ($e) => str_contains($e, '受注No')));
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_calendar_date_is_rejected()
    {
        // 2026/02/31 は実在しない日付
        $rows = [$this->row('8000007', 'A社', '商品A', 1000, 1000, '2026/02/31')];
        $path = $this->makeSalesWorkbook($this->monthlyTitle('企画', 2026, 2), $rows);

        try {
            $result = $this->validator()->validate($path, 'planning', 'monthly', 2026, 2);

            $this->assertFalse($result['valid']);
            $this->assertNotEmpty(array_filter($this->flattenInvalidOrderErrors($result), fn ($e) => str_contains($e, 'SB下版日')));
        } finally {
            @unlink($path);
        }
    }

    private function row(string $orderNumber, string $client, string $product, float $lineAmount, float $orderAmount, string $plateDate): array
    {
        return [
            'order_number' => $orderNumber,
            'client_name' => $client,
            'product_name' => $product,
            'category' => '組版',
            'item_name' => '新規',
            'format_size' => 'A4',
            'color_count' => 1,
            'quantity' => 1,
            'unit_price' => $lineAmount,
            'line_amount' => $lineAmount,
            'order_amount_component' => $orderAmount,
            'plate_date' => $plateDate,
        ];
    }
}

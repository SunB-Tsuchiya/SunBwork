<?php

namespace Tests\Unit\SunA;

use App\Services\SunA\Sales\ClientSalesV1Reader;
use App\Services\SunA\Sales\ClientSalesWorkbookWriter;
use App\Services\SunA\Sales\SunaSalesImportException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/** 営業部「得意先別」Excel（形式 v1）の読み取り。ダミーの得意先名だけを使う（DB は使わない） */
class ClientSalesV1ReaderTest extends TestCase
{
    /** @var array<int, string> */
    private array $tmp = [];

    protected function tearDown(): void
    {
        foreach ($this->tmp as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function path(): string
    {
        return $this->tmp[] = tempnam(sys_get_temp_dir(), 'sl') . '.xlsx';
    }

    private function workbook(array $rows, array $options = []): string
    {
        return (new ClientSalesWorkbookWriter())->write($rows, $this->path(), $options);
    }

    private function read(array $rows, array $options = []): array
    {
        return (new ClientSalesV1Reader())->read($this->workbook($rows, $options));
    }

    private function row(string $client, int $year, string $dept, array $months): array
    {
        return ['client' => $client, 'year' => $year, 'department' => $dept, 'months' => $months];
    }

    public function test_reads_clients_years_months_and_departments()
    {
        $result = $this->read([
            $this->row('テスト得意先001', 2024, '営業第一部', [1 => [1000.0, 2], 2 => [2500.0, 3]]),
            $this->row('テスト得意先001', 2025, '営業第三部第一課', [1 => [300.0, 1]]),
            $this->row('テスト得意先002', 2025, '営業第三部', [12 => [900.0, 4]]),
        ]);

        $this->assertSame(ClientSalesV1Reader::FORMAT, $result['format']);
        $this->assertSame([], $result['errors']);
        $this->assertSame([], $result['warnings']);
        $this->assertSame(3, $result['row_count']);
        $this->assertSame(2024, $result['year_min']);
        $this->assertSame(2025, $result['year_max']);

        [$r1, $r2, $r3] = $result['rows'];
        $this->assertSame('テスト得意先001', $r1['client_name']); // A列から取る（B列は先頭行だけ）
        $this->assertSame('テスト得意先001', $r2['client_name']);
        $this->assertSame(2024, $r1['year']);
        $this->assertSame('営業第一部', $r1['department_label']);
        $this->assertSame([1 => ['amount' => 1000.0, 'orders' => 2], 2 => ['amount' => 2500.0, 'orders' => 3]], $r1['months']);
        $this->assertSame('営業第三部第一課', $r2['department_label']);
        $this->assertSame('営業第三部', $r3['department_label']);
        $this->assertSame([12 => ['amount' => 900.0, 'orders' => 4]], $r3['months']);
    }

    public function test_unregistered_months_are_skipped_and_zero_is_registered()
    {
        $result = $this->read([
            $this->row('テスト得意先001', 2025, '営業第一部', [
                1 => [0.0, 0],        // 0円・0件 → 登録済み
                2 => [null, 5],       // 件数だけ → 登録済み（金額0）
                3 => [700.0, null],   // 金額だけ → 登録済み（件数0）
                // 4月以降は空欄 → 未登録
            ]),
        ]);

        $months = $result['rows'][0]['months'];
        $this->assertSame([1, 2, 3], array_keys($months));
        $this->assertSame(['amount' => 0.0, 'orders' => 0], $months[1]);
        $this->assertSame(['amount' => 0.0, 'orders' => 5], $months[2]);
        $this->assertSame(['amount' => 700.0, 'orders' => 0], $months[3]);
    }

    public function test_year_labels_with_and_without_the_nendo_suffix_and_fullwidth_digits()
    {
        $withoutSuffix = $this->read([$this->row('テスト得意先001', 2024, '営業第一部', [1 => [1.0, 1]])], ['omit_year_suffix' => true]);
        $this->assertSame(2024, $withoutSuffix['rows'][0]['year']);

        $path = $this->workbook([$this->row('テスト得意先001', 2024, '営業第一部', [1 => [1.0, 1]])]);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $book->getActiveSheet()->setCellValue('C4', '２０２４年度'); // 全角数字
        (new Xlsx($book))->save($path);
        $this->assertSame(2024, (new ClientSalesV1Reader())->read($path)['rows'][0]['year']);
    }

    public function test_duplicate_client_and_year_is_an_error_with_row_numbers_and_normalized_names_match()
    {
        $result = $this->read([
            $this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1]]),
            $this->row('テスト得意先002', 2025, '営業第一部', [1 => [1.0, 1]]),
            $this->row('テスト　得意先００１', 2025, '営業第一部', [2 => [1.0, 1]]), // 全角空白・全角数字だけ違う同じ得意先
        ]);

        $this->assertCount(2, $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('6行目', $result['errors'][0]);
        $this->assertStringContainsString('4行目と同じ', $result['errors'][0]);
    }

    public function test_missing_department_and_bad_year_are_errors()
    {
        $result = $this->read([
            $this->row('テスト得意先001', 2025, '', [1 => [1.0, 1]]),
            $this->row('テスト得意先002', 2025, '営業第一部', [1 => [1.0, 1]]),
        ]);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('4行目: 担当部門が空です', $result['errors'][0]);

        $path = $this->workbook([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1]])]);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $book->getActiveSheet()->setCellValue('C4', '令和7年');
        (new Xlsx($book))->save($path);
        $bad = (new ClientSalesV1Reader())->read($path);
        $this->assertStringContainsString('年度「令和7年」を読み取れません', $bad['errors'][0]);
    }

    public function test_number_formats_commas_triangles_and_invalid_text()
    {
        $path = $this->workbook([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1.0, 1], 2 => [1.0, 1], 3 => [1.0, 1], 4 => [1.0, 1]])], ['total_row' => false]);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $ws = $book->getActiveSheet();
        $ws->setCellValue('D4', '1,234,567');   // 1月 金額: カンマ区切り文字列
        $ws->setCellValue('F4', '▲5,000');      // 2月 金額: マイナス
        $ws->setCellValue('H4', '¥8,000円');    // 3月 金額: 記号つき
        $ws->setCellValue('J4', 'あり');        // 4月 金額: 数値でない
        (new Xlsx($book))->save($path);

        $result = (new ClientSalesV1Reader())->read($path);

        $this->assertSame(1234567.0, $result['rows'][0]['months'][1]['amount']);
        $this->assertSame(-5000.0, $result['rows'][0]['months'][2]['amount']);
        $this->assertSame(8000.0, $result['rows'][0]['months'][3]['amount']);
        $this->assertCount(1, array_filter($result['errors'], fn ($e) => str_contains($e, '4行目 4月') && str_contains($e, '数値として読み取れません')));
    }

    public function test_negative_amounts_are_allowed()
    {
        $result = $this->read([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [-1500.0, 1]])]);

        $this->assertSame([], $result['errors']);
        $this->assertSame(-1500.0, $result['rows'][0]['months'][1]['amount']);
    }

    public function test_total_row_checks_are_warnings_and_the_total_row_is_not_imported()
    {
        $ok = $this->read([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1000.0, 2]])]);
        $this->assertSame([], $ok['warnings']);
        $this->assertSame(1, $ok['row_count']); // 総計行は取り込まない

        $mismatch = $this->read([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1000.0, 2]])], ['total_amount' => 9999.0]);
        $this->assertCount(1, $mismatch['warnings']);
        $this->assertStringContainsString('総計行の売上金額', $mismatch['warnings'][0]);
        $this->assertSame([], $mismatch['errors']); // 警告であって、取込は止めない

        $none = $this->read([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1000.0, 2]])], ['total_row' => false]);
        $this->assertSame(['総計行が見つかりませんでした（検算ができません）。'], $none['warnings']);
    }

    public function test_row_total_columns_that_disagree_with_the_months_are_warnings()
    {
        $path = $this->workbook([$this->row('テスト得意先001', 2025, '営業第一部', [1 => [1000.0, 2]])]);
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $book->getActiveSheet()->setCellValue('AB4', 5000);
        (new Xlsx($book))->save($path);

        $result = (new ClientSalesV1Reader())->read($path);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, array_filter($result['warnings'], fn ($w) => str_contains($w, '4行目: 合計列の売上金額')));
    }

    public function test_a_workbook_in_another_format_is_rejected()
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setCellValue('A1', '別の表');
        $path = $this->path();
        (new Xlsx($spreadsheet))->save($path);

        $this->expectException(SunaSalesImportException::class);
        $this->expectExceptionMessage('「得意先別」の売上Excelの形式ではありません');
        (new ClientSalesV1Reader())->read($path);
    }

    public function test_a_file_that_is_not_an_excel_workbook_is_rejected()
    {
        $path = $this->path();
        file_put_contents($path, 'これはExcelではありません');

        $this->expectException(SunaSalesImportException::class);
        (new ClientSalesV1Reader())->read($path);
    }

    public function test_a_workbook_without_data_rows_is_an_error()
    {
        $result = $this->read([]);

        $this->assertSame([], $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('取り込めるデータ行がありません', $result['errors'][0]);
    }
}

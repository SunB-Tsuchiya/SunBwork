<?php

namespace Tests\Unit\MGinbon;

use App\Services\MGinbon\MGinbonAnnualSchoolListReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class MGinbonAnnualSchoolListReaderTest extends TestCase
{
    private string $path;

    protected function tearDown(): void
    {
        if (isset($this->path)) {
            @unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_it_reads_fixed_columns_and_uses_the_right_side_of_an_arrow_code(): void
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['みくにコード', '日能研コード', 'α版', '学校名', '入試回【掲載用】'],
            ['101', '4331 → 4335', '①', '青山学院中等部※要確認', '第1回'],
            ['102', '3331', '　', '青山学院横浜英和中学校', 'A'],
        ]);
        $this->path = tempnam(sys_get_temp_dir(), 'mginbon_annual_');
        (new Xlsx($book))->save($this->path);
        $book->disconnectWorksheets();

        $result = (new MGinbonAnnualSchoolListReader())->read($this->path);

        $this->assertSame(2, $result['total']);
        $this->assertSame(1, $result['alpha_total']);
        $this->assertSame('4335', $result['rows'][0]['n_code']);
        $this->assertSame('', $result['rows'][1]['alpha_group']);
        $this->assertContains('Nコード変更表記：右側を候補にしました', $result['rows'][0]['warnings']);
        $this->assertContains('学校名の注記を確認してください', $result['rows'][0]['warnings']);
        $this->assertSame('4331 → 4335', $result['rows'][0]['raw']['n_code']);
    }
}

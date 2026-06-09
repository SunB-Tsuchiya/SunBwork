<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\LabelSchoolMaster;
use App\Models\LabelTestName;
use App\Models\LabelSubject;
use App\Models\LabelItemType;

class LabelMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSchools();
        $this->seedTestNames();
        $this->seedSubjects();
        $this->seedItemTypes();
    }

    // ----------------------------------------------------------------
    // 教室マスタ（school_master_draft.csv）
    // ----------------------------------------------------------------
    private function seedSchools(): void
    {
        $csvPath = base_path('Shimizu_Seihan/school_master_draft.csv');
        if (!file_exists($csvPath)) {
            $this->command->warn("school_master_draft.csv が見つかりません: {$csvPath}");
            return;
        }

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle); // ヘッダースキップ

        $now = now();
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) continue;
            [$code, $display_name, $area, $route, $stop, $notes] = array_pad($row, 6, '');

            $code         = trim($code);
            $display_name = trim($display_name);
            $area         = trim($area);
            $route        = trim($route) ?: null;
            $stop_order   = is_numeric(trim($stop)) ? (int) trim($stop) : null;
            $notes        = trim($notes) ?: null;

            if (!$code || !$display_name) continue;

            // AS コード → AS_1（渋谷校）/ AS_2（表参道校）に分割済みのケースは notes に従う
            LabelSchoolMaster::updateOrCreate(
                ['code' => $code],
                compact('display_name', 'area', 'route', 'stop_order', 'notes')
            );
        }
        fclose($handle);

        // 特殊合成コード追加（$始まり）
        $specials = [
            ['code' => '$tokai',  'display_name' => '日能研東海本部',   'area' => '東海',       'notes' => '特殊行（Excelの東海本部行）'],
            ['code' => '$julius', 'display_name' => 'ユリウス・アトラス分', 'area' => '関東',   'notes' => '特殊行（Excelのユリウス/アトラス行）'],
            ['code' => '$yobi',   'display_name' => '予備',             'area' => '関東',       'notes' => '特殊行（Excelの予備行）'],
            // ASコード重複対応（渋谷・表参道）
            ['code' => 'AS_1', 'display_name' => '渋谷校',   'area' => '関東', 'route' => 'B1', 'stop_order' => 4, 'notes' => '元コードAS（担当確認要：渋谷）'],
            ['code' => 'AS_2', 'display_name' => '表参道校', 'area' => '関東', 'route' => 'B1', 'stop_order' => 4, 'notes' => '元コードAS（担当確認要：表参道）'],
        ];

        foreach ($specials as $s) {
            LabelSchoolMaster::updateOrCreate(
                ['code' => $s['code']],
                array_merge(['route' => null, 'stop_order' => null], $s)
            );
        }

        // 元の AS レコード（重複あり）を非アクティブ化
        LabelSchoolMaster::where('code', 'AS')->update(['is_active' => false]);

        $this->command->info('教室マスタ: ' . LabelSchoolMaster::count() . ' 件');
    }

    // ----------------------------------------------------------------
    // テスト名（テスト名.txt）
    // ----------------------------------------------------------------
    private function seedTestNames(): void
    {
        $path = base_path('Shimizu_Seihan/filemakerファイル_forClaude/テスト名.txt');
        $names = $this->readLines($path);

        foreach ($names as $i => $name) {
            LabelTestName::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }
        $this->command->info('テスト名マスタ: ' . LabelTestName::count() . ' 件');
    }

    // ----------------------------------------------------------------
    // 科目（科目.txt）
    // ----------------------------------------------------------------
    private function seedSubjects(): void
    {
        $path = base_path('Shimizu_Seihan/filemakerファイル_forClaude/科目.txt');
        $names = $this->readLines($path);

        foreach ($names as $i => $name) {
            LabelSubject::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }
        $this->command->info('科目マスタ: ' . LabelSubject::count() . ' 件');
    }

    // ----------------------------------------------------------------
    // 内容（内容.txt）
    // ----------------------------------------------------------------
    private function seedItemTypes(): void
    {
        $path = base_path('Shimizu_Seihan/filemakerファイル_forClaude/内容.txt');
        $names = $this->readLines($path);

        foreach ($names as $i => $name) {
            LabelItemType::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }
        $this->command->info('内容マスタ: ' . LabelItemType::count() . ' 件');
    }

    private function readLines(string $path): array
    {
        if (!file_exists($path)) {
            $this->command->warn("ファイルが見つかりません: {$path}");
            return [];
        }
        $raw = mb_convert_encoding(file_get_contents($path), 'UTF-8', 'UTF-8,SJIS-win,EUC-JP');
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        return array_values(array_filter(array_map('trim', $lines), fn($l) => $l !== ''));
    }
}

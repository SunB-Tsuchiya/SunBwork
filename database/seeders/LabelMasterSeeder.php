<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\LabelSchoolMaster;
use App\Models\LabelTestName;
use App\Models\LabelSubject;
use App\Models\LabelItemType;
use App\Models\LabelRoute;
use App\Models\LabelRouteStop;

class LabelMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSchools();
        $this->seedTestNames();
        $this->seedSubjects();
        $this->seedItemTypes();
        $this->seedRoutes();
    }

    // ----------------------------------------------------------------
    // 教室マスタ（z_shimizu_seihan/教室マスタ.xlsx）
    // ----------------------------------------------------------------
    private function seedSchools(): void
    {
        $xlsxPath = base_path('z_shimizu_seihan/教室マスタ.xlsx');
        if (!file_exists($xlsxPath)) {
            $this->command->warn("教室マスタ.xlsx が見つかりません: {$xlsxPath}");
            return;
        }

        $this->command->info('教室マスタ.xlsx を読み込み中...');
        $reader = IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($xlsxPath);
        $sheet       = $spreadsheet->getActiveSheet();

        LabelSchoolMaster::truncate();

        $count = 0;
        $seen  = [];
        $rowIterator = $sheet->getRowIterator(2);

        foreach ($rowIterator as $row) {
            $cells = [];
            foreach ($row->getCellIterator('A', 'F') as $cell) {
                $cells[] = $cell->getValue();
            }

            $rawCode = trim((string)($cells[0] ?? ''));
            if (!$rawCode) continue;

            $code = mb_convert_kana($rawCode, 'a', 'UTF-8');
            $code = strtoupper(preg_replace('/\s+/', '', $code));
            if (!preg_match('/^[A-Z0-9]{2,3}$/', $code)) continue;
            if (isset($seen[$code])) continue;
            $seen[$code] = true;

            $route       = trim(mb_convert_kana((string)($cells[1] ?? ''), 'a', 'UTF-8'));
            $printName   = trim((string)($cells[2] ?? ''));
            $name        = trim((string)($cells[3] ?? ''));
            $stopOrder   = is_numeric($cells[4] ?? '') ? (int)$cells[4] : null;
            $defaultQty  = is_numeric($cells[5] ?? '') ? (int)$cells[5] : 0;
            $displayName = $printName ?: ($name ?: $code);

            LabelSchoolMaster::create([
                'code'         => $code,
                'display_name' => $displayName,
                'area'         => $this->deriveArea($code, $route),
                'route'        => $route ?: null,
                'stop_order'   => $stopOrder,
                'default_qty'  => $defaultQty,
                'is_active'    => true,
            ]);
            $count++;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        $specials = [
            ['code' => '$tokai',  'display_name' => '日能研東海本部',      'area' => '東海', 'notes' => '特殊行（Excelの東海本部行）'],
            ['code' => '$julius', 'display_name' => 'ユリウス・アトラス分', 'area' => '関東', 'notes' => '特殊行（Excelのユリウス/アトラス行）'],
            ['code' => '$yobi',   'display_name' => '予備',                'area' => '関東', 'notes' => '特殊行（Excelの予備行）'],
        ];
        foreach ($specials as $s) {
            LabelSchoolMaster::updateOrCreate(
                ['code' => $s['code']],
                array_merge(['route' => null, 'stop_order' => null, 'default_qty' => 0, 'is_active' => true], $s)
            );
        }

        $this->command->info("教室マスタ: {$count} 件 + 特殊 " . count($specials) . " 件登録完了");
    }

    private function deriveArea(string $code, string $route): string
    {
        static $kantoRoutes = ['A1','B1','C1','D1','E1','F1','G1','H1','I1',
                               'A2','B2','C2','D2','E2','F2','G2','H2','I2'];
        if ($code === 'SS')             return '北海道';
        if (in_array($route, $kantoRoutes)) return '関東';
        if (str_starts_with($code, 'T')) return '東海';
        if (preg_match('/^[HKLM]/', $code)) return '関西';
        if (str_starts_with($code, 'P')) return '四国';
        if (str_starts_with($code, 'R')) return '九州・沖縄';
        return '関東';
    }

    // ----------------------------------------------------------------
    // テスト名
    // ----------------------------------------------------------------
    private function seedTestNames(): void
    {
        $path = base_path('z_shimizu_seihan/test名一覧.txt');
        $names = $this->readLines($path);

        $existing = LabelTestName::pluck('name')->flip()->toArray();
        $added = 0;
        foreach ($names as $i => $name) {
            if (isset($existing[$name])) continue;
            LabelTestName::create(['name' => $name, 'sort_order' => $i + 1, 'is_active' => true]);
            $added++;
        }
        $this->command->info('テスト名マスタ: ' . LabelTestName::count() . ' 件（新規追加: ' . $added . ' 件）');
    }

    // ----------------------------------------------------------------
    // 科目
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
    // 内容
    // ----------------------------------------------------------------
    private function seedItemTypes(): void
    {
        $path = base_path('z_shimizu_seihan/アイテムマスタ.txt');
        $names = $this->readLines($path);

        foreach ($names as $i => $name) {
            LabelItemType::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }
        $this->command->info('内容マスタ: ' . LabelItemType::count() . ' 件');
    }

    // ----------------------------------------------------------------
    // 社内便ルートマスタ（z_shimizu_seihan/社内便_ルート一覧_2025.1001～.xlsx）
    // ----------------------------------------------------------------

    // ルート別の列位置 (0-indexed, [nameCol, codeCol])
    private const ROUTE_COL_MAP = [
        'A' => [1, 2], 'B' => [3, 4],  'C' => [5, 6],   'D' => [7, 8],
        'E' => [9, 10], 'F' => [11, 12], 'G' => [13, 14], 'H' => [15, 16],
        'I' => [17, 18],
        'EXTRA' => [21, 22], // G水便 / G土便
    ];

    // セル背景色 → カテゴリー
    private const COLOR_MAP = [
        'FFCC66' => 'honbu',   // 本部系教室（オレンジ）
        '99FF66' => 'kanto',   // 関東系教室（緑）
        'FFFF00' => 'busho',   // 部署等（黄）
        'FF66FF' => 'henkou',  // 変更（ピンク）
        'FF3300' => 'kakunin', // 確認（赤）
        '00B0F0' => 'ng',      // NG便（水色）
    ];

    // ソート順: A1=1, B1=2, ... I1=9, EXTRA1=10, A2=11, ... I2=19, EXTRA2=20
    private const ROUTE_SORT = [
        'A1'=>1,'B1'=>2,'C1'=>3,'D1'=>4,'E1'=>5,'F1'=>6,'G1'=>7,'H1'=>8,'I1'=>9,
        'G水便'=>10,
        'A2'=>11,'B2'=>12,'C2'=>13,'D2'=>14,'E2'=>15,'F2'=>16,'G2'=>17,'H2'=>18,'I2'=>19,
        'G土便'=>20,
    ];

    private function seedRoutes(): void
    {
        $path = base_path('z_shimizu_seihan/社内便_ルート一覧_2025.1001～.xlsx');
        if (!file_exists($path)) {
            $this->command->warn("社内便ルート一覧が見つかりません: {$path}");
            return;
        }

        $this->command->info('社内便ルート一覧を読み込み中...');

        // スタイルあり（色情報取得のため setReadDataOnly しない）
        $reader = IOFactory::createReader('Xlsx');
        $wb    = $reader->load($path);
        $sheet = $wb->getActiveSheet();

        // シート全体を2次元配列（値・色）に変換
        $allRows   = [];
        $allColors = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells  = [];
            $colors = [];
            foreach ($row->getCellIterator('A', 'Z') as $cell) {
                $cells[] = $cell->getValue();
                $fill    = $cell->getStyle()->getFill();
                $rgb     = $fill->getStartColor()->getRGB();
                $type    = $fill->getFillType();
                $colors[] = ($type === 'solid' && $rgb !== 'FFFFFF' && $rgb !== '000000') ? $rgb : null;
            }
            $allRows[]   = $cells;
            $allColors[] = $colors;
        }

        $wb->disconnectWorksheets();
        unset($wb);

        // コース1・コース2 の開始行を検索
        $courseStarts = [];
        foreach ($allRows as $i => $row) {
            $a = (string)($row[0] ?? '');
            if (preg_match('/●(\d)コース/', $a, $m)) {
                $courseStarts[(int)$m[1]] = $i;
            }
        }

        // 既存データを全削除して再作成
        LabelRouteStop::query()->delete();
        LabelRoute::query()->delete();

        $routeCount = 0;
        $stopCount  = 0;

        foreach ([1, 2] as $course) {
            if (!isset($courseStarts[$course])) continue;

            $start = $courseStarts[$course];
            $end   = isset($courseStarts[$course + 1]) ? $courseStarts[$course + 1] : count($allRows);

            $section = array_slice($allRows, $start, $end - $start);

            $sectionColors = array_slice($allColors, $start, $end - $start);
            ['routes' => $routes, 'stops' => $stops] = $this->parseCourseSection($section, $course, $sectionColors);

            foreach ($routes as $routeCode => $rData) {
                $sort = self::ROUTE_SORT[$routeCode] ?? 99;
                $route = LabelRoute::create([
                    'code'       => $routeCode,
                    'course'     => $course,
                    'area'       => $rData['area'],
                    'day1'       => $rData['day1'],
                    'day1_start' => $rData['day1_start'],
                    'day2'       => $rData['day2'],
                    'day2_start' => $rData['day2_start'],
                    'sort_order' => $sort,
                ]);
                $routeCount++;

                foreach ($stops[$routeCode] ?? [] as $stop) {
                    if (!$stop['school_name'] && !$stop['school_code']) continue;
                    LabelRouteStop::create([
                        'route_id'       => $route->id,
                        'stop_order'     => $stop['stop_order'],
                        'school_code'    => $stop['school_code'],
                        'school_name'    => $stop['school_name'],
                        'arrival_time'   => $stop['arrival_time'],
                        'notes'          => $stop['notes'],
                        'color_category' => $stop['color_category'] ?? null,
                    ]);
                    $stopCount++;
                }
            }
        }

        $this->command->info("社内便ルートマスタ: {$routeCount} ルート / {$stopCount} 停留所 登録完了");

        // 教室マスタの stop_order をルート順に合わせて更新
        $this->syncSchoolStopOrder();
    }

    private function parseCourseSection(array $rows, int $course, array $colors = []): array
    {
        $routes = [];
        $stops  = [];

        // ルートキー一覧（EXTRA は水便/土便）
        $extraCode = $course === 1 ? 'G水便' : 'G土便';
        $routeKeys = [];
        foreach (['A','B','C','D','E','F','G','H','I'] as $letter) {
            $routeKeys[$letter . $course] = self::ROUTE_COL_MAP[$letter];
        }
        $routeKeys[$extraCode] = self::ROUTE_COL_MAP['EXTRA'];

        foreach ($routeKeys as $code => $_) {
            $routes[$code] = ['area'=>'', 'day1'=>'', 'day1_start'=>'', 'day2'=>'', 'day2_start'=>''];
            $stops[$code]  = [];
        }

        $headerFound = false;
        $dayFound    = false;
        $i = 0;
        $n = count($rows);

        while ($i < $n) {
            $row    = $rows[$i];
            $colA   = trim(mb_convert_kana((string)($row[0] ?? ''), 'a', 'UTF-8'));
            $colB   = trim((string)($row[1] ?? ''));

            // ルートヘッダー行の検出（Ａ－１ / Ａ－２ 形式）
            if (!$headerFound) {
                $normalized = mb_convert_kana((string)($row[1] ?? ''), 'a', 'UTF-8');
                if (preg_match('/A[-－][12]/', $normalized)) {
                    // エリア情報を抽出（codeCol に area が入っている）
                    foreach ($routeKeys as $code => [$nameCol, $codeCol]) {
                        $area = trim(mb_convert_kana((string)($row[$codeCol] ?? ''), 'a', 'UTF-8'));
                        $routes[$code]['area'] = $area;
                    }
                    $headerFound = true;
                }
                $i++;
                continue;
            }

            // 曜日行（stop_num=1 の行が day1、その次が day2）
            if (!$dayFound && $colA === '1') {
                foreach ($routeKeys as $code => [$nameCol, $codeCol]) {
                    $routes[$code]['day1']       = trim((string)($row[$nameCol] ?? ''));
                    $routes[$code]['day1_start'] = trim((string)($row[$codeCol] ?? ''));
                }
                // 次行が day2
                $i++;
                if ($i < $n) {
                    $row2 = $rows[$i];
                    foreach ($routeKeys as $code => [$nameCol, $codeCol]) {
                        $routes[$code]['day2']       = trim((string)($row2[$nameCol] ?? ''));
                        $routes[$code]['day2_start'] = trim((string)($row2[$codeCol] ?? ''));
                    }
                }
                $dayFound = true;
                $i++;
                continue;
            }

            // 停留所行（stop_num >= 2）
            if ($dayFound && is_numeric($colA) && (int)$colA >= 2) {
                $stopOrder = (int)$colA;
                // 次行が時刻行
                $timeRow = ($i + 1 < $n) ? $rows[$i + 1] : [];

                foreach ($routeKeys as $code => [$nameCol, $codeCol]) {
                    $schoolName = trim((string)($row[$nameCol] ?? ''));
                    $rawCode    = trim(mb_convert_kana((string)($row[$codeCol] ?? ''), 'a', 'UTF-8'));
                    $schoolCode = preg_match('/^[A-Z]{2,3}$/', strtoupper($rawCode)) ? strtoupper($rawCode) : null;

                    $arrivalTime = $this->parseTime($timeRow[$nameCol] ?? null);
                    $notes = trim((string)($timeRow[$codeCol] ?? '')) ?: null;

                    $rgb      = $colors[$i][$nameCol] ?? null;
                    $category = $rgb ? (self::COLOR_MAP[$rgb] ?? null) : null;

                    $stops[$code][] = [
                        'stop_order'     => $stopOrder,
                        'school_name'    => $schoolName,
                        'school_code'    => $schoolCode,
                        'arrival_time'   => $arrivalTime,
                        'notes'          => $notes,
                        'color_category' => $category,
                    ];
                }
                $i += 2; // stop行 + time行 を消費
                continue;
            }

            $i++;
        }

        return compact('routes', 'stops');
    }

    private function parseTime(mixed $val): ?string
    {
        if ($val === null || $val === '') return null;
        // Excel 数値時刻（0.0〜1.0）
        if (is_numeric($val) && (float)$val > 0 && (float)$val < 1) {
            $minutes = (int)round((float)$val * 24 * 60);
            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
        // 文字列時刻（13:00 / 13：30 など）
        $str = (string)$val;
        if (preg_match('/(\d{1,2})[：:](\d{2})/', $str, $m)) {
            return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        }
        return null;
    }

    // ----------------------------------------------------------------
    // ルートの停留所順を教室マスタの stop_order に反映
    // ----------------------------------------------------------------
    private function syncSchoolStopOrder(): void
    {
        // ルート順（sort_order順）に並べた全停留所を取得
        $stops = LabelRouteStop::with('route')
            ->join('label_routes', 'label_routes.id', '=', 'label_route_stops.route_id')
            ->orderBy('label_routes.sort_order')
            ->orderBy('label_route_stops.stop_order')
            ->select('label_route_stops.*')
            ->get();

        $updated = 0;
        $seen    = []; // 同じコードが複数ルートに出ても最初の出現のみ更新

        foreach ($stops as $s) {
            $code = $s->school_code;
            if (!$code || isset($seen[$code])) continue;
            $seen[$code] = true;

            // stop_order = route_sort_order * 100 + excel_stop_label
            $newOrder = ($s->route->sort_order * 100) + $s->stop_order;

            $affected = LabelSchoolMaster::where('code', $code)->update(['stop_order' => $newOrder]);
            if ($affected) $updated++;
        }

        $this->command->info("教室マスタ stop_order 更新: {$updated} 件");
    }

    // ----------------------------------------------------------------

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

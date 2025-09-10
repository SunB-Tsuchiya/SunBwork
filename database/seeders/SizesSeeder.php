<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SizesSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            ['name' => 'A3超', 'label' => 'A3超', 'width' => 450, 'height' => 320, 'unit' => 'mm'],
            ['name' => 'A3', 'label' => 'A3', 'width' => 297, 'height' => 420, 'unit' => 'mm'],
            ['name' => 'A4', 'label' => 'A4', 'width' => 210, 'height' => 297, 'unit' => 'mm'],
            ['name' => 'A5', 'label' => 'A5', 'width' => 148, 'height' => 210, 'unit' => 'mm'],
            ['name' => 'B4', 'label' => 'B4', 'width' => 257, 'height' => 364, 'unit' => 'mm'],
            ['name' => 'B5', 'label' => 'B5', 'width' => 182, 'height' => 257, 'unit' => 'mm'],
            ['name' => 'B6', 'label' => 'B6', 'width' => 128, 'height' => 182, 'unit' => 'mm'],
            ['name' => '四六判', 'label' => '四六判', 'width' => 127, 'height' => 188, 'unit' => 'mm'],
            // Typical web sizes in px
            ['name' => 'Full HD', 'label' => '1920x1080', 'width' => 1920, 'height' => 1080, 'unit' => 'px'],
            ['name' => 'HD', 'label' => '1280x720', 'width' => 1280, 'height' => 720, 'unit' => 'px'],
            ['name' => 'iPhone 14', 'label' => '1170x2532', 'width' => 1170, 'height' => 2532, 'unit' => 'px'],
            ['name' => 'iPad', 'label' => '768x1024', 'width' => 768, 'height' => 1024, 'unit' => 'px'],
        ];

        $companyId = DB::table('companies')->where('code', 'SUNBRAIN')->value('id');
        $departmentId = DB::table('departments')->where('code', 'INFO')->value('id');

        foreach ($sizes as $s) {
            $insert = $s;
            // ensure width/height are numeric and unit present
            $insert['width'] = isset($s['width']) ? $s['width'] : (isset($s['width_mm']) ? $s['width_mm'] : null);
            $insert['height'] = isset($s['height']) ? $s['height'] : (isset($s['height_mm']) ? $s['height_mm'] : null);
            $insert['unit'] = $s['unit'] ?? 'mm';
            $insert['company_id'] = $companyId;
            $insert['department_id'] = $departmentId;
            DB::table('sizes')->updateOrInsert(['name' => $s['name']], $insert);
        }
    }
}

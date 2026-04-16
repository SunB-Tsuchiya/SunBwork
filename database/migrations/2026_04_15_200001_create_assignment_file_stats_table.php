<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_file_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_assignment_id')->unique();
            $table->foreign('project_job_assignment_id')
                  ->references('id')->on('project_job_assignments')
                  ->onDelete('cascade');

            // 合計
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('total_pages')->default(0);
            $table->unsignedBigInteger('total_size_bytes')->default(0);

            // ファイル種別ごとの件数・ページ数
            $table->unsignedInteger('pdf_files')->default(0);
            $table->unsignedInteger('pdf_pages')->default(0);
            $table->unsignedInteger('ai_files')->default(0);
            $table->unsignedInteger('ai_pages')->default(0);
            $table->unsignedInteger('docx_files')->default(0);
            $table->unsignedInteger('docx_pages')->default(0);
            $table->unsignedInteger('indd_files')->default(0);
            $table->unsignedInteger('indd_pages')->default(0);
            $table->unsignedInteger('eps_files')->default(0);
            $table->unsignedInteger('psd_files')->default(0);
            $table->unsignedInteger('image_files')->default(0); // jpg/png/tiff/gif
            $table->unsignedInteger('other_files')->default(0);

            $table->timestamps();
        });

        // 既存の file_info を持つレコードをバックフィル
        $rows = DB::table('project_job_assignments')
            ->whereNotNull('file_info')
            ->select('id', 'file_info')
            ->get();

        foreach ($rows as $row) {
            $info = is_string($row->file_info)
                ? json_decode($row->file_info, true)
                : (array) $row->file_info;

            if (!is_array($info)) continue;

            $groups = $info['groups'] ?? [];

            DB::table('assignment_file_stats')->insertOrIgnore([
                'project_job_assignment_id' => $row->id,
                'total_files'  => $info['total_files']      ?? 0,
                'total_pages'  => $info['total_pages']      ?? 0,
                'total_size_bytes' => $info['total_size_bytes'] ?? 0,
                'pdf_files'    => $groups['pdf']['count']   ?? 0,
                'pdf_pages'    => $groups['pdf']['pages']   ?? 0,
                'ai_files'     => $groups['ai']['count']    ?? 0,
                'ai_pages'     => $groups['ai']['pages']    ?? 0,
                'docx_files'   => $groups['docx']['count']  ?? 0,
                'docx_pages'   => $groups['docx']['pages']  ?? 0,
                'indd_files'   => $groups['indd']['count']  ?? 0,
                'indd_pages'   => $groups['indd']['pages']  ?? 0,
                'eps_files'    => $groups['eps']['count']   ?? 0,
                'psd_files'    => $groups['psd']['count']   ?? 0,
                'image_files'  => ($groups['jpg']['count']  ?? 0)
                                + ($groups['png']['count']  ?? 0)
                                + ($groups['tiff']['count'] ?? 0)
                                + ($groups['gif']['count']  ?? 0),
                'other_files'  => $groups['other']['count'] ?? 0,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_file_stats');
    }
};

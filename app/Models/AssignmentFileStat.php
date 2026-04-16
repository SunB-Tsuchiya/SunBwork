<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssignmentFileStat extends Model
{
    protected $fillable = [
        'project_job_assignment_id',
        'total_files',
        'total_pages',
        'total_size_bytes',
        'pdf_files',
        'pdf_pages',
        'ai_files',
        'ai_pages',
        'docx_files',
        'docx_pages',
        'indd_files',
        'indd_pages',
        'eps_files',
        'psd_files',
        'image_files',
        'other_files',
    ];

    public function projectJobAssignment()
    {
        return $this->belongsTo(ProjectJobAssignment::class);
    }

    /**
     * file_info 配列から stats データを構築して upsert する。
     */
    public static function upsertFromFileInfo(int $assignmentId, array $fileInfo): void
    {
        $groups = $fileInfo['groups'] ?? [];

        $data = [
            'project_job_assignment_id' => $assignmentId,
            'total_files'      => $fileInfo['total_files']       ?? 0,
            'total_pages'      => $fileInfo['total_pages']       ?? 0,
            'total_size_bytes' => $fileInfo['total_size_bytes']  ?? 0,
            'pdf_files'        => $groups['pdf']['count']        ?? 0,
            'pdf_pages'        => $groups['pdf']['pages']        ?? 0,
            'ai_files'         => $groups['ai']['count']         ?? 0,
            'ai_pages'         => $groups['ai']['pages']         ?? 0,
            'docx_files'       => $groups['docx']['count']       ?? 0,
            'docx_pages'       => $groups['docx']['pages']       ?? 0,
            'indd_files'       => $groups['indd']['count']       ?? 0,
            'indd_pages'       => $groups['indd']['pages']       ?? 0,
            'eps_files'        => $groups['eps']['count']        ?? 0,
            'psd_files'        => $groups['psd']['count']        ?? 0,
            'image_files'      => ($groups['jpg']['count']       ?? 0)
                                + ($groups['png']['count']       ?? 0)
                                + ($groups['tiff']['count']      ?? 0)
                                + ($groups['gif']['count']       ?? 0),
            'other_files'      => $groups['other']['count']      ?? 0,
            'updated_at'       => now(),
        ];

        $existing = static::where('project_job_assignment_id', $assignmentId)->first();
        if ($existing) {
            $existing->update($data);
        } else {
            $data['created_at'] = now();
            static::create($data);
        }
    }
}

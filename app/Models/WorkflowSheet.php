<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowSheet extends Model
{
    protected $fillable = [
        'project_job_id',
        'template_id',
        'name',
        'stage_config',
        'column_config',
        'share_token',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'stage_config'  => 'array',
        'column_config' => 'array',
    ];

    public function projectJob()
    {
        return $this->belongsTo(ProjectJob::class);
    }

    public function template()
    {
        return $this->belongsTo(WorkflowTemplate::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rows()
    {
        return $this->hasMany(WorkflowRow::class, 'sheet_id')->orderBy('sort_order');
    }

    public function favorites()
    {
        return $this->hasMany(\App\Models\CoordinatorWorkflowSheetFavorite::class, 'workflow_sheet_id');
    }

    /**
     * column_config を返す（なければ stage_config から変換して返す）
     */
    public function getEffectiveColumnConfig(): array
    {
        if (!empty($this->column_config)) {
            return $this->column_config;
        }
        $typeMap = ['coordinator' => 'worker', 'proof_worker' => 'proof_v2'];
        $stages  = $this->stage_config['stages'] ?? [];
        return array_map(fn($s) => [
            'key'   => $s['key']   ?? ('col_' . uniqid()),
            'label' => $s['label'] ?? '',
            'type'  => $typeMap[$s['type'] ?? ''] ?? ($s['type'] ?? 'worker'),
        ], $stages);
    }
}

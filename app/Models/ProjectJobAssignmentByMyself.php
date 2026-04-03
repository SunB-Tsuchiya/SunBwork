<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * ProjectJobAssignmentByMyself は ProjectJobAssignment の自己割当エイリアス（後方互換）。
 *
 * migration 2026_04_03_300001 により project_job_assignment_by_myself テーブルの
 * 全データは project_job_assignments へ移行済み。
 * sender_id = user_id のレコードが自己割当を示す。
 *
 * 新コードでは ProjectJobAssignment::selfAssigned() スコープを使うこと。
 */
class ProjectJobAssignmentByMyself extends ProjectJobAssignment
{
    // $table はデフォルトの 'project_job_assignments' を継承

    protected static function booted(): void
    {
        // 自己割当（sender_id = user_id）のみを対象とするグローバルスコープ
        static::addGlobalScope('self_assigned', function (Builder $query) {
            $query->whereColumn('sender_id', 'user_id');
        });
    }

    /**
     * Build a standardized array for pre-filling Event creation forms.
     */
    public function toEventPrefill(): array
    {
        $this->loadMissing(['projectJob.client', 'user', 'size', 'stage', 'workItemType', 'statusModel']);

        return [
            'id'                 => $this->id,
            'project_job_id'     => $this->project_job_id,
            'title'              => $this->title ?: ($this->projectJob->name ?? null),
            'details'            => $this->detail ?? ($this->projectJob->detail ?? null),
            'assigned_user_name' => $this->user?->name,
            'assigned_user_id'   => $this->user?->id,
            'user'               => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null,
            'project_job_name'   => $this->projectJob->name ?? null,
            'project_job_detail' => $this->projectJob->detail ?? null,
            'difficulty'         => $this->difficulty ?? ($this->projectJob->difficulty ?? null),
            'desired_end_date'   => $this->desired_end_date
                ? (method_exists($this->desired_end_date, 'format')
                    ? $this->desired_end_date->format('Y-m-d')
                    : (string) $this->desired_end_date)
                : null,
            'desired_time'       => $this->desired_time,
            'estimated_hours'    => $this->estimated_hours,
            'scheduled'          => (bool) ($this->scheduled ?? false),
            'scheduled_at'       => $this->scheduled_at
                ? (method_exists($this->scheduled_at, 'format')
                    ? $this->scheduled_at->format('Y-m-d H:i:s')
                    : (string) $this->scheduled_at)
                : null,
            'accepted'           => (bool) ($this->accepted ?? false),
            'completed'          => (bool) ($this->completed ?? false),
            'preferred_date'     => null,
            'size_label'         => $this->size?->name ?? $this->size?->label,
            'stage_label'        => $this->stage?->name ?? $this->stage?->label,
            'type_label'         => $this->workItemType?->name ?? $this->workItemType?->label,
            'status_label'       => $this->statusModel?->name ?? $this->statusModel?->label,
            'client'             => ($this->projectJob && isset($this->projectJob->client))
                ? ['id' => $this->projectJob->client->id ?? null, 'name' => $this->projectJob->client->name ?? null]
                : null,
            'client_name'        => $this->projectJob->client->name ?? null,
        ];
    }
}

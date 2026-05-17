<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoordinatorWorkflowSheetFavorite extends Model
{
    protected $fillable = ['user_id', 'workflow_sheet_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function workflowSheet()
    {
        return $this->belongsTo(WorkflowSheet::class, 'workflow_sheet_id');
    }
}

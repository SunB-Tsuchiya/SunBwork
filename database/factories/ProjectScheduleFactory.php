<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ProjectSchedule;

class ProjectScheduleFactory extends Factory
{
    protected $model = ProjectSchedule::class;

    public function definition()
    {
        return [
            'project_job_id' => 1,
            'parent_id' => null,
            'name' => 'Test Schedule',
            'description' => null,
            'start_date' => now(),
            'end_date' => now()->addDay(),
            'progress' => 0,
            'status' => 'planned',
            'order' => 0,
            'metadata' => null,
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}

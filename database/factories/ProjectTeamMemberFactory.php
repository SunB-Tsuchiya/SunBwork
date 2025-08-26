<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\ProjectTeamMember;

class ProjectTeamMemberFactory extends Factory
{
    protected $model = ProjectTeamMember::class;

    public function definition()
    {
        return [
            'project_job_id' => 1,
            'user_id' => 1,
        ];
    }
}

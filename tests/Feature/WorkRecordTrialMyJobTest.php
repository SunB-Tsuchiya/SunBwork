<?php

namespace Tests\Feature;

use App\Http\Controllers\Schedule\WorkRecordTrialController;
use App\Models\User;
use App\Models\WorkRecordTrial;
use App\Services\PersonalWorkSchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WorkRecordTrialMyJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This test uses only an in-memory SQLite database; it never runs migrations or resets shared databases.
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        DB::table('users')->insert([['id' => 7], ['id' => 8]]);
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('project_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
        });
        Schema::create('project_job_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('title')->nullable();
            $table->boolean('completed')->default(false);
        });
        Schema::create('work_record_trials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->date('work_date');
            $table->time('recorded_time')->nullable();
            $table->unsignedBigInteger('project_job_id')->nullable();
            $table->unsignedBigInteger('project_job_assignment_id')->nullable();
            $table->string('project_name_text')->nullable();
            $table->string('work_category');
            $table->string('activity_type');
            $table->string('title')->nullable();
            $table->string('progress_state');
            $table->unsignedSmallInteger('approximate_minutes')->nullable();
            $table->text('note')->nullable();
            $table->uuid('series_id');
            $table->timestamps();
        });

        (require database_path('migrations/2026_10_12_110000_make_work_record_trial_progress_optional.php'))->up();
        (require database_path('migrations/2026_10_12_120000_create_work_record_trial_days_table.php'))->up();
    }

    public function test_owned_my_job_sets_project_from_existing_job_without_modifying_it(): void
    {
        DB::table('project_jobs')->insert([
            ['id' => 11, 'title' => '本人の案件', 'company_id' => 99],
            ['id' => 12, 'title' => '送信された別の案件', 'company_id' => 1],
        ]);
        DB::table('project_job_assignments')->insert([
            'id' => 21, 'project_job_id' => 11, 'user_id' => 7, 'sender_id' => 7, 'title' => '本人のマイジョブ',
        ]);

        $response = $this->controller()->store($this->request(7, [
            'project_job_assignment_id' => 21,
            'project_job_id' => 12,
            'project_name_text' => '無関係な仮名',
        ]));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertDatabaseHas('work_record_trials', [
            'user_id' => 7, 'project_job_assignment_id' => 21, 'project_job_id' => 11,
            'project_name_text' => null,
        ]);
        $this->assertDatabaseHas('project_job_assignments', [
            'id' => 21, 'title' => '本人のマイジョブ', 'completed' => 0,
        ]);
    }

    public function test_other_users_my_job_cannot_be_linked(): void
    {
        DB::table('project_jobs')->insert(['id' => 11, 'title' => '他人の案件']);
        DB::table('project_job_assignments')->insert([
            'id' => 21, 'project_job_id' => 11, 'user_id' => 8, 'sender_id' => 8, 'title' => '他人のマイジョブ',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        try {
            $this->controller()->store($this->request(7, ['project_job_assignment_id' => 21]));
        } finally {
            $this->assertDatabaseCount('work_record_trials', 0);
        }
    }

    public function test_search_returns_only_own_my_jobs_even_when_client_names_match(): void
    {
        DB::table('clients')->insert(['id' => 4, 'name' => '旭教材']);
        DB::table('project_jobs')->insert(['id' => 11, 'title' => '2026年度 実力チェック', 'client_id' => 4]);
        DB::table('project_job_assignments')->insert([
            ['id' => 21, 'project_job_id' => 11, 'user_id' => 7, 'sender_id' => 7, 'title' => '校正'],
            ['id' => 22, 'project_job_id' => 11, 'user_id' => 8, 'sender_id' => 8, 'title' => '非公開の作業'],
            ['id' => 23, 'project_job_id' => 11, 'user_id' => 7, 'sender_id' => 8, 'title' => '未転換の依頼'],
        ]);

        $response = $this->controller()->myJobOptions($this->request(7, ['q' => '旭教材'], 'GET'));
        $jobs = json_decode($response->getContent(), true)['my_jobs'];

        $this->assertCount(1, $jobs);
        $this->assertSame(21, $jobs[0]['id']);
        $this->assertSame('旭教材', $jobs[0]['client_name']);
    }

    public function test_unregistered_project_name_can_be_recorded_and_continued(): void
    {
        $controller = $this->controller();
        $first = $controller->store($this->request(7, [
            'project_name_text' => '仮：2026年度 実力チェック',
        ]));
        $firstId = json_decode($first->getContent(), true)['id'];

        $second = $controller->store($this->request(7, [
            'project_name_text' => '仮：2026年度 実力チェック',
            'progress_state' => 'handed_off',
            'continue_from_id' => $firstId,
        ]));
        $secondId = json_decode($second->getContent(), true)['id'];

        $this->assertDatabaseCount('work_record_trials', 2);
        $this->assertDatabaseHas('work_record_trials', [
            'id' => $secondId, 'project_job_id' => null, 'project_job_assignment_id' => null,
            'project_name_text' => '仮：2026年度 実力チェック', 'progress_state' => 'handed_off',
        ]);
        $this->assertSame(
            DB::table('work_record_trials')->where('id', $firstId)->value('series_id'),
            DB::table('work_record_trials')->where('id', $secondId)->value('series_id'),
        );
    }

    public function test_ten_different_jobs_can_be_entered_without_progress_or_time(): void
    {
        $controller = $this->controller();
        for ($number = 1; $number <= 10; $number++) {
            $controller->store($this->request(7, [
                'title' => '作業'.$number,
                'progress_state' => null,
            ]));
        }

        $response = $controller->index($this->request(7, [
            'date' => now('Asia/Tokyo')->toDateString(),
        ], 'GET'));
        $rows = json_decode($response->getContent(), true)['records'];

        $this->assertCount(10, $rows);
        $this->assertSame('作業1', $rows[0]['title']);
        $this->assertSame('作業10', $rows[9]['title']);
        $this->assertNull($rows[0]['progress_state']);
        $this->assertNull($rows[0]['recorded_time']);
    }

    public function test_day_review_warns_about_missing_time_and_reopens_after_changes(): void
    {
        $controller = $this->controller();
        $first = $controller->store($this->request(7, ['title' => '校正', 'progress_state' => null]));
        $firstId = json_decode($first->getContent(), true)['id'];
        $second = $controller->store($this->request(7, ['title' => '教材制作', 'approximate_minutes' => 45]));
        $secondId = json_decode($second->getContent(), true)['id'];

        $before = $this->dayReview($controller);
        $this->assertFalse($before['reviewed']);
        $this->assertSame(1, $before['missing_minutes_count']);
        $this->assertSame(45, $before['entered_minutes_total']);

        $controller->reviewDay($this->request(7, []));
        $this->assertTrue($this->dayReview($controller)['reviewed']);

        $controller->update($this->request(7, [
            'title' => '校正', 'approximate_minutes' => 30, 'progress_state' => null,
        ]), WorkRecordTrial::findOrFail($firstId));
        $afterEdit = $this->dayReview($controller);
        $this->assertFalse($afterEdit['reviewed']);
        $this->assertSame(0, $afterEdit['missing_minutes_count']);
        $this->assertSame(75, $afterEdit['entered_minutes_total']);

        $controller->reviewDay($this->request(7, []));
        $controller->destroy($this->request(7, []), WorkRecordTrial::findOrFail($secondId));
        $this->assertFalse($this->dayReview($controller)['reviewed']);
    }

    public function test_empty_day_cannot_be_marked_reviewed(): void
    {
        $this->expectException(ValidationException::class);
        try {
            $this->controller()->reviewDay($this->request(7, []));
        } finally {
            $this->assertDatabaseCount('work_record_trial_days', 0);
        }
    }

    private function dayReview(WorkRecordTrialController $controller): array
    {
        $response = $controller->index($this->request(7, [
            'date' => now('Asia/Tokyo')->toDateString(),
        ], 'GET'));

        return json_decode($response->getContent(), true)['day_review'];
    }

    private function controller(): WorkRecordTrialController
    {
        return new WorkRecordTrialController(new class extends PersonalWorkSchedule {
            public function available(User $user): bool
            {
                return true;
            }
        });
    }

    private function request(int $userId, array $values, string $method = 'POST'): Request
    {
        $user = new User();
        $user->id = $userId;
        $user->company_id = 1;
        $user->department_id = 2;
        $request = Request::create('/schedule/work-record-trial/records', $method, array_merge([
            'work_date' => now('Asia/Tokyo')->toDateString(),
            'work_category' => 'dtp',
            'activity_type' => 'revision',
            'progress_state' => 'worked',
        ], $values));
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}

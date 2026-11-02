<?php

namespace Tests\Integration;

use App\Http\Controllers\Schedule\DepartmentWorkController;
use App\Http\Controllers\User\UnifiedMyJobBoxController;
use App\Models\User;
use App\Services\UnifiedMyJobBox;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Standalone synthetic integration: never uses Tests\TestCase or database-reset traits. */
class UnifiedMyJobCreateTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== ':memory:') {
            throw new \RuntimeException('Run only with explicit synthetic SQLite memory configuration');
        }
        $this->app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        self::assertSame('sqlite', config('database.default'));
        self::assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('companies', function (Blueprint $t) { $t->id(); $t->string('company_type'); });
        Schema::create('departments', function (Blueprint $t) { $t->id(); $t->integer('company_id'); $t->string('code'); });
        Schema::create('project_jobs', function (Blueprint $t) { $t->id(); $t->string('title')->nullable(); $t->integer('company_id')->nullable(); $t->integer('broad_project_id')->nullable(); });
        Schema::create('project_job_assignments', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('user_id'); $t->integer('company_id')->nullable(); $t->integer('department_id')->nullable(); });
        Schema::create('events', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('project_job_assignment_id')->nullable(); });
        Schema::create('workflow_cells', function (Blueprint $t) { $t->id(); $t->integer('assignment_id')->nullable(); });
        Schema::create('broad_projects', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->integer('department_id'); $t->string('name'); $t->boolean('is_active'); $t->integer('parent_id')->nullable(); $t->integer('fiscal_year')->nullable();
        });
        Schema::create('personal_work_entries', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('company_id'); $t->integer('department_id'); $t->date('work_date');
            $t->string('work_category'); $t->string('activity_type')->nullable(); $t->string('plan_slot');
            $t->string('planned_start_time'); $t->string('planned_end_time'); $t->string('title')->nullable();
            $t->string('project_name_text')->nullable(); $t->integer('project_job_id')->nullable(); $t->integer('broad_project_id')->nullable();
            $t->string('entry_visibility'); $t->decimal('actual_quantity')->nullable(); $t->string('actual_unit')->nullable();
            $t->integer('actual_minutes'); $t->text('result_note')->nullable(); $t->integer('source_event_id')->nullable();
            $t->integer('project_job_assignment_id')->nullable(); $t->integer('diary_id')->nullable(); $t->timestamps();
        });
        foreach (['user_monthly_schedules', 'user_monthly_breaks'] as $name) {
            Schema::create($name, function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('year_month'); $t->json('schedule'); });
        }
        DB::table('companies')->insert([['id' => 1, 'company_type' => 'sunbrain'], ['id' => 2, 'company_type' => 'general']]);
        DB::table('departments')->insert([['id' => 1, 'company_id' => 1, 'code' => 'INFO'], ['id' => 2, 'company_id' => 1, 'code' => 'OTHER'], ['id' => 3, 'company_id' => 2, 'code' => 'INFO']]);
        DB::table('broad_projects')->insert([['id' => 1, 'company_id' => 1, 'department_id' => 1, 'name' => '承認済み分類', 'is_active' => true], ['id' => 2, 'company_id' => 2, 'department_id' => 3, 'name' => '別会社', 'is_active' => true]]);
        DB::table('events')->insert(['id' => 99, 'user_id' => 1]);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        DB::disconnect();
        $this->app->flush();
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
    }

    private function user(array $data = []): User
    {
        return (new User)->forceFill($data + ['id' => 1, 'user_role' => 'superadmin', 'company_id' => 1, 'department_id' => 1])->setRelation('userSetting', null);
    }

    private function request(array $payload = [], ?User $user = null, ?int $context = null, string $method = 'POST'): Request
    {
        $request = Request::create('/myjobbox/unified-preview', $method, $payload);
        $session = new \Illuminate\Session\Store('test', new \Illuminate\Session\ArraySessionHandler(120));
        if ($context !== null) $session->put('superadmin_context.company_id', $context);
        $request->setLaravelSession($session);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $user ?? $this->user());
        return $request;
    }

    private function payload(array $data = []): array
    {
        return $data + ['work_date' => '2026-12-01', 'work_category' => 'other', 'plan_slot' => 'hour',
            'planned_start_time' => '09:00', 'planned_end_time' => '09:30', 'title' => 'BOX入力',
            'entry_visibility' => 'department', 'actual_quantity' => 2, 'actual_unit' => 'ページ', 'broad_project_id' => 1];
    }

    private function save(Request $request)
    {
        return app(UnifiedMyJobBoxController::class)->store($request, app(UnifiedMyJobBox::class), app(DepartmentWorkController::class));
    }

    public function test_new_route_rejects_roles_company_department_and_changed_context_without_writing(): void
    {
        foreach ([['user_role' => 'user'], ['user_role' => 'admin'], ['user_role' => 'coordinator'], ['user_role' => 'leader'], ['department_id' => 2], ['company_id' => 2, 'department_id' => 3]] as $data) {
            $request = $this->request($this->payload(), $this->user($data));
            self::assertSame('user.myjobbox.unified_store', app('router')->getRoutes()->match($request)->getName());
            try { $this->save($request); self::fail('Unauthorized save'); }
            catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        }
        try { $this->save($this->request($this->payload(), $this->user(), 2)); self::fail('Changed context accepted'); }
        catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        self::assertSame(0, DB::table('personal_work_entries')->count());
    }

    public function test_saved_work_is_one_owner_fixed_entry_visible_in_box_and_existing_calendar(): void
    {
        $response = $this->save($this->request($this->payload(['user_id' => 9, 'company_id' => 2, 'department_id' => 3, 'source_event_id' => 99, 'project_job_assignment_id' => 123])));
        self::assertSame(201, $response->getStatusCode());
        $entry = DB::table('personal_work_entries')->first();
        self::assertSame(1, DB::table('personal_work_entries')->count());
        self::assertSame(1, $entry->user_id); self::assertSame(1, $entry->company_id); self::assertSame(1, $entry->department_id);
        self::assertNull($entry->source_event_id); self::assertNull($entry->project_job_assignment_id);
        self::assertSame(30, $entry->actual_minutes);
        $box = app(UnifiedMyJobBox::class)->listing($this->user(), 1, ['period' => '2026-12']);
        self::assertSame(1, $box['pagination']['total']);
        self::assertSame('entry:'.$entry->id, $box['rows'][0]['key']);
        self::assertSame('承認済み分類', $box['rows'][0]['classification']);
        $calendar = app(DepartmentWorkController::class)->mine($this->request(['start' => '2026-12-01', 'end' => '2026-12-01'], null, null, 'GET'))->getData(true)['events'];
        self::assertCount(1, $calendar);
        self::assertSame('personal-work-'.$entry->id, $calendar[0]['id']);
        self::assertSame('2026-12-01T09:00:00', $calendar[0]['starts_at']);
        self::assertSame('2026-12-01T09:30:00', $calendar[0]['ends_at']);
        self::assertSame(1, DB::table('events')->count()); self::assertSame(0, DB::table('project_job_assignments')->count());
        self::assertSame(0, DB::table('project_jobs')->count());
    }

    public function test_existing_validation_still_rejects_missing_time_invalid_unit_and_foreign_classification(): void
    {
        foreach ([['planned_start_time' => ''], ['planned_end_time' => '08:30'], ['actual_unit' => ''], ['work_date' => '2026-02-30']] as $data) {
            try { $this->save($this->request($this->payload($data))); self::fail('Invalid input accepted'); }
            catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
        }
        try { $this->save($this->request($this->payload(['broad_project_id' => 2]))); self::fail('Foreign classification accepted'); }
        catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        self::assertSame(0, DB::table('personal_work_entries')->count());
    }
}

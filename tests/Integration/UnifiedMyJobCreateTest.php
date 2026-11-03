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
        Schema::create('project_job_assignments', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('user_id'); $t->integer('company_id')->nullable(); $t->integer('department_id')->nullable(); $t->integer('sender_id')->nullable(); $t->integer('source_assignment_id')->nullable(); $t->string('title')->nullable(); $t->boolean('completed')->default(false); });
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
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_03_120001_add_work_job_core_fields.php')->up();
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
        self::assertSame('#4F46E5', $calendar[0]['_custom_color']);
        self::assertSame('#FFFFFF', $calendar[0]['_custom_text_color']);
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
    public function test_detail_route_and_edit_keep_identity_and_reject_cross_context_or_other_owner(): void
    {
        $id = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        $request = $this->request(['from' => 'box', 'q' => 'before', 'period' => 'all'], null, null, 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = app(\App\Http\Controllers\User\WorkJobDetailController::class)->show($request, $id, app(\App\Services\WorkJobReader::class));
        $page = $response->toResponse($request)->getData(true);
        self::assertSame('MyJobBox/WorkDetail', $page['component']); self::assertSame('before', $page['props']['back']['q']);
        self::assertSame($id, $page['props']['work']['id']); self::assertSame(0, $page['props']['time']['recorded']);
        $update = $this->request($this->payload(['work_date' => '2099-12-02', 'title' => 'Edited', 'source_entry_id' => 999, 'completed_at' => '2026-11-01']));
        $result = app(\App\Http\Controllers\User\WorkJobDetailController::class)->update($update, $id, app(\App\Services\WorkJobReader::class), app(DepartmentWorkController::class));
        self::assertSame($id, $result->getData(true)['id']); self::assertSame(1, DB::table('personal_work_entries')->count());
        $row = DB::table('personal_work_entries')->first();
        self::assertSame('Edited', $row->title); self::assertSame('2099-12-02', $row->work_date);
        self::assertNull($row->source_entry_id); self::assertNull($row->completed_at);
        foreach ([[$this->user(), 2], [$this->user(['user_role' => 'user']), null]] as [$user, $context]) {
            try { app(\App\Services\WorkJobReader::class)->detail($user, $id, $context); self::fail('Invalid access'); }
            catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertTrue(true); }
        }
        try { app(\App\Services\WorkJobReader::class)->detail($this->user(['id' => 2]), $id); self::fail('Other owner'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        self::assertSame(0, DB::table('project_job_assignments')->count()); self::assertSame(1, DB::table('events')->count());
    }

    public function test_mixed_parent_reader_preserves_ids_and_rejects_cycles_and_invalid_parent_pairs(): void
    {
        $root = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        $child = $this->save($this->request($this->payload(['work_date' => '2099-12-02'])))->getData(true)['id'];
        DB::table('project_jobs')->insert(['id' => 1, 'company_id' => 1]);
        DB::table('project_job_assignments')->insert(['id' => 1, 'project_job_id' => 1, 'user_id' => 1, 'sender_id' => 1, 'source_personal_work_entry_id' => $root]);
        DB::table('personal_work_entries')->where('id', $child)->update(['source_assignment_id' => 1]);
        $reader = app(\App\Services\WorkJobReader::class);
        $series = $reader->ancestors($this->user(), 'entry', $child);
        self::assertSame(['entry', 'assignment', 'entry'], array_column($series, 'type'));
        self::assertSame([$root, 1, $child], array_column($series, 'id'));
        try { DB::table('personal_work_entries')->where('id', $root)->delete(); self::fail('Referenced parent deleted'); }
        catch (\Illuminate\Database\QueryException $e) { self::assertSame(2, DB::table('personal_work_entries')->count()); }
        DB::table('personal_work_entries')->where('id', $root)->update(['source_entry_id' => $child]);
        try { $reader->ancestors($this->user(), 'entry', $child); self::fail('Cycle accepted'); }
        catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
        DB::table('personal_work_entries')->where('id', $child)->update(['source_entry_id' => $root]);
        try { $reader->ancestors($this->user(), 'entry', $child); self::fail('Double parent accepted'); }
        catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
    }

    public function test_detail_delete_keeps_child_and_diary_protection_and_can_delete_an_independent_leaf(): void
    {
        $id = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        $controller = app(\App\Http\Controllers\User\WorkJobDetailController::class);
        $reader = app(\App\Services\WorkJobReader::class);
        DB::table('personal_work_entries')->where('id', $id)->update(['diary_id' => 99]);
        try { $controller->destroy($this->request(), $id, $reader, app(DepartmentWorkController::class)); self::fail('Diary entry deleted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        DB::table('personal_work_entries')->where('id', $id)->update(['diary_id' => null]);
        $child = $this->save($this->request($this->payload(['work_date' => '2099-12-02'])))->getData(true)['id'];
        DB::table('personal_work_entries')->where('id', $child)->update(['source_entry_id' => $id]);
        try { $controller->destroy($this->request(), $id, $reader, app(DepartmentWorkController::class)); self::fail('Parent deleted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        $response = $controller->destroy($this->request(), $child, $reader, app(DepartmentWorkController::class));
        self::assertSame(204, $response->getStatusCode());
        self::assertSame([$id], DB::table('personal_work_entries')->pluck('id')->all());
        self::assertSame(1, DB::table('events')->count()); self::assertSame(0, DB::table('project_job_assignments')->count());
    }

}

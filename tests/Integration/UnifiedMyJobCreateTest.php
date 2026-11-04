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
        Schema::create('project_jobs', function (Blueprint $t) { $t->id(); $t->string('title')->nullable(); $t->integer('company_id')->nullable(); $t->integer('broad_project_id')->nullable(); $t->boolean('show_on_department_schedule')->default(true); $t->integer('user_id')->nullable(); $t->integer('client_id')->nullable(); });
        Schema::create('clients', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('company_id')->nullable(); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->integer('company_id'); $t->integer('department_id'); $t->boolean('is_ghost')->default(false); });
        Schema::create('project_job_coordinators', function (Blueprint $t) { $t->integer('project_job_id'); $t->integer('user_id'); });
        Schema::create('project_team_members', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('user_id'); });
        Schema::create('project_job_assignments', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('user_id'); $t->integer('company_id')->nullable(); $t->integer('department_id')->nullable(); $t->integer('sender_id')->nullable(); $t->integer('source_assignment_id')->nullable(); $t->integer('supersedes_assignment_id')->nullable(); $t->string('title')->nullable(); $t->boolean('completed')->default(false); $t->integer('status_id')->nullable(); $t->timestamp('proof_completed_at')->nullable(); $t->string('job_type')->nullable(); $t->date('desired_end_date')->nullable(); $t->string('amounts_unit')->nullable(); $t->timestamps(); });
        Schema::create('events', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('project_job_assignment_id')->nullable(); $t->dateTime('starts_at')->nullable(); $t->dateTime('ends_at')->nullable(); $t->dateTime('cancelled_at')->nullable(); $t->string('visibility')->nullable(); $t->string('title')->nullable(); $t->timestamps(); });
        Schema::create('workflow_cells', function (Blueprint $t) { $t->id(); $t->integer('assignment_id')->nullable(); });
        Schema::create('broad_projects', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->integer('department_id'); $t->string('name'); $t->boolean('is_active'); $t->integer('parent_id')->nullable(); $t->integer('fiscal_year')->nullable(); $t->integer('client_id')->nullable();
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
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_03_150001_create_work_job_continuations_table.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_03_180001_support_legacy_work_continuation_sources.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_05_120001_create_work_job_completion_syncs.php')->up();
        Schema::create('statuses', function (Blueprint $t) { $t->id(); $t->string('key'); $t->string('slug'); });
        DB::table('statuses')->insert(['id' => 1, 'key' => 'completed', 'slug' => 'completed']);
        Schema::create('progress_cells', function (Blueprint $t) { $t->id(); $t->integer('assignment_id'); $t->timestamp('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('proof_requests', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('proofreader_id'); $t->integer('project_job_assignment_id')->nullable(); $t->integer('requester_id'); $t->string('title'); $t->string('status'); $t->timestamp('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('job_notifications', function (Blueprint $t) { $t->id(); $t->string('type'); $t->integer('sender_id'); $t->integer('recipient_id'); $t->integer('project_job_id'); $t->integer('assignment_id')->nullable(); $t->text('message'); $t->timestamps(); });
        config(['database.connections.mginbon.database' => '']);
        foreach (['user_monthly_schedules', 'user_monthly_breaks'] as $name) {
            Schema::create($name, function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('year_month'); $t->json('schedule'); });
        }
        DB::table('companies')->insert([['id' => 1, 'company_type' => 'sunbrain'], ['id' => 2, 'company_type' => 'general']]);
        DB::table('departments')->insert([['id' => 1, 'company_id' => 1, 'code' => 'INFO'], ['id' => 2, 'company_id' => 1, 'code' => 'OTHER'], ['id' => 3, 'company_id' => 2, 'code' => 'INFO']]);
        DB::table('broad_projects')->insert([['id' => 1, 'company_id' => 1, 'department_id' => 1, 'name' => '承認済み分類', 'is_active' => true], ['id' => 2, 'company_id' => 2, 'department_id' => 3, 'name' => '別会社', 'is_active' => true]]);
        DB::table('users')->insert([['id' => 1, 'company_id' => 1, 'department_id' => 1], ['id' => 2, 'company_id' => 1, 'department_id' => 1]]);
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

    private function continueWork(int $source, array $data = [], ?User $user = null, ?int $context = null)
    {
        return app(\App\Http\Controllers\User\WorkJobDetailController::class)->continueEntry(
            $this->request($this->payload($data + ['request_token' => 'de305d54-75b4-431b-adb2-eb6b9e546014']), $user, $context),
            $source, app(\App\Services\WorkJobMutation::class), app(DepartmentWorkController::class));
    }

    public function test_continuation_lunch_next_day_replay_and_completion_keep_siblings_quantities_and_legacy_tables(): void
    {
        $root = $this->save($this->request($this->payload(['work_date' => '2099-12-01', 'planned_start_time' => '09:00', 'planned_end_time' => '12:00', 'actual_quantity' => 90, 'result_note' => '元の結果'])))->getData(true)['id'];
        $before = (array) DB::table('personal_work_entries')->find($root);
        $args = ['work_date' => '2099-12-01', 'planned_start_time' => '13:00', 'planned_end_time' => '15:00', 'actual_quantity' => null, 'actual_unit' => null, 'result_note' => null,
            'source_entry_id' => 999, 'source_assignment_id' => 999, 'completed_at' => '2000-01-01', 'user_id' => 999];
        $first = $this->continueWork($root, $args); self::assertSame(201, $first->getStatusCode());
        $child = $first->getData(true)['id'];
        self::assertSame(200, $this->continueWork($root, $args)->getStatusCode());
        self::assertSame(2, DB::table('personal_work_entries')->count());
        self::assertSame(1, DB::table('work_job_continuations')->count());
        $new = DB::table('personal_work_entries')->find($child);
        self::assertSame($root, $new->source_entry_id); self::assertNull($new->source_assignment_id);
        self::assertNull($new->completed_at); self::assertNull($new->actual_quantity); self::assertNull($new->result_note); self::assertSame(1, $new->user_id);
        self::assertSame($before, (array) DB::table('personal_work_entries')->find($root));
        $series = app(\App\Services\WorkJobReader::class)->series($this->user(), $child);
        self::assertSame(300, $series['totals']['afterLunch']); self::assertSame(0.0, $series['totals']['recorded']);
        self::assertTrue($series['operationsEnabled']); self::assertFalse($series['allCompleted']);
        self::assertSame(['entry:'.$root, 'entry:'.$child], array_column($series['items'], 'key'));
        $sibling = $this->continueWork($root, $args + ['unused' => true] + [])->getData(true)['id'];
        self::assertSame($child, $sibling); // Arbitrary unvalidated fields do not alter replay identity.
        $tomorrow = $this->continueWork($root, array_replace($args, ['request_token' => 'fe305d54-75b4-431b-adb2-eb6b9e546014', 'work_date' => '2099-12-02']))->getData(true)['id'];
        $mutation = app(\App\Services\WorkJobMutation::class);
        self::assertSame([$root, $child], $mutation->completeEntry($this->user(), $child, null)['completed_ids']);
        $completedAt = DB::table('personal_work_entries')->find($root)->completed_at;
        self::assertNotNull($completedAt); self::assertNull(DB::table('personal_work_entries')->find($tomorrow)->completed_at);
        $mutation->completeEntry($this->user(), $child, null);
        self::assertSame($completedAt, DB::table('personal_work_entries')->find($root)->completed_at);
        self::assertSame(90, (int) DB::table('personal_work_entries')->find($root)->actual_quantity);
        self::assertFalse(app(\App\Services\WorkJobReader::class)->series($this->user(), $root)['allCompleted']);
        $mutation->completeEntry($this->user(), $tomorrow, null);
        self::assertTrue(app(\App\Services\WorkJobReader::class)->series($this->user(), $child)['allCompleted']);
        $reopened = $this->continueWork($root, array_replace($args, ['request_token' => 'ae305d54-75b4-431b-adb2-eb6b9e546014']))->getData(true)['id'];
        self::assertNull(DB::table('personal_work_entries')->find($reopened)->completed_at);
        self::assertFalse(app(\App\Services\WorkJobReader::class)->series($this->user(), $root)['allCompleted']);
        self::assertSame(0.0, app(\App\Services\WorkJobReader::class)->series($this->user(), $root)['totals']['recorded']);
        $calendar = collect(app(DepartmentWorkController::class)->mine($this->request(['start' => '2099-12-01', 'end' => '2099-12-02'], null, null, 'GET'))->getData(true)['events'])->keyBy('id');
        foreach ([$root, $child, $tomorrow] as $done) {
            self::assertTrue($calendar['personal-work-'.$done]['completed']);
            self::assertStringStartsWith('【完了】', $calendar['personal-work-'.$done]['title']);
        }
        self::assertFalse($calendar['personal-work-'.$reopened]['completed']);
        self::assertStringStartsNotWith('【完了】', $calendar['personal-work-'.$reopened]['title']);
        self::assertSame('2099-12-01T09:00:00', $calendar['personal-work-'.$root]['starts_at']);
        self::assertSame('2099-12-01T12:00:00', $calendar['personal-work-'.$root]['ends_at']);
        self::assertSame($before['title'], DB::table('personal_work_entries')->find($root)->title);
        self::assertSame(1, DB::table('events')->count()); self::assertSame(0, DB::table('project_job_assignments')->count());
    }

    public function test_continuation_rejects_wrong_context_owner_retired_classification_and_changed_replay_and_does_not_resurrect_deleted_result(): void
    {
        $root = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        $result = $this->continueWork($root, ['work_date' => '2099-12-02']);
        $child = $result->getData(true)['id'];
        try { $this->continueWork($root, ['work_date' => '2099-12-03']); self::fail('Changed replay accepted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        self::assertSame(2, DB::table('personal_work_entries')->count());
        foreach ([[$this->user(['user_role' => 'user']), null], [$this->user(), 2]] as [$user, $context]) {
            try { $this->continueWork($root, [], $user, $context); self::fail('Unauthorized continuation'); }
            catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertTrue(true); }
            try { app(\App\Services\WorkJobMutation::class)->completeEntry($user, $root, $context); self::fail('Unauthorized completion'); }
            catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertTrue(true); }
        }
        try { $this->continueWork($root, [], $this->user(['id' => 2])); self::fail('Other owner continuation'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        DB::table('broad_projects')->where('id', 1)->update(['is_active' => false]);
        try { $this->continueWork($root, ['request_token' => 'fe305d54-75b4-431b-adb2-eb6b9e546014']); self::fail('Retired classification accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('broad_project_id', $e->errors()); }
        DB::table('broad_projects')->where('id', 1)->update(['is_active' => true]);
        app(\App\Http\Controllers\User\WorkJobDetailController::class)->destroy($this->request(), $child, app(\App\Services\WorkJobReader::class), app(DepartmentWorkController::class));
        try { $this->continueWork($root, ['work_date' => '2099-12-02']); self::fail('Deleted result recreated'); }
        catch (HttpException $e) { self::assertSame(410, $e->getStatusCode()); }
        self::assertSame(1, DB::table('personal_work_entries')->count());
    }

    public function test_series_rejects_unauthorized_children_and_completes_authorized_mixed_ancestors(): void
    {
        $root = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        $child = $this->continueWork($root, ['work_date' => '2099-12-02'])->getData(true)['id'];
        DB::table('personal_work_entries')->where('id', $child)->update(['user_id' => 2]);
        try { app(\App\Services\WorkJobReader::class)->series($this->user(), $root); self::fail('Foreign child exposed'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        DB::table('personal_work_entries')->where('id', $child)->update(['user_id' => 1, 'broad_project_id' => 2]);
        try { app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $root, null); self::fail('Foreign classification child accepted'); }
        catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertTrue(true); }
        self::assertSame(0, DB::table('personal_work_entries')->whereNotNull('completed_at')->count());
        DB::table('personal_work_entries')->where('id', $child)->update(['broad_project_id' => 1]);
        DB::table('project_jobs')->insert(['id' => 1, 'company_id' => 1]);
        DB::table('project_job_assignments')->insert(['id' => 1, 'project_job_id' => 1, 'user_id' => 1, 'sender_id' => 1, 'source_personal_work_entry_id' => $root]);
        DB::table('personal_work_entries')->where('id', $child)->update(['source_entry_id' => null, 'source_assignment_id' => 1]);
        $series = app(\App\Services\WorkJobReader::class)->series($this->user(), $child);
        self::assertCount(3, $series['items']); self::assertSame(60, $series['totals']['planned']); self::assertTrue($series['operationsEnabled']); self::assertTrue($series['canContinue']);
        app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $child, null);
        self::assertSame(2, DB::table('personal_work_entries')->whereNotNull('completed_at')->count());
        self::assertTrue((bool) DB::table('project_job_assignments')->find(1)->completed);
        self::assertSame(1, DB::table('work_job_completion_syncs')->whereNotNull('synced_at')->count());
    }

    public function test_invalid_continuation_input_and_large_tree_stop_without_partial_creation(): void
    {
        $root = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        foreach ([['request_token' => 'invalid'], ['planned_end_time' => '08:00']] as $invalid) {
            try { $this->continueWork($root, $invalid); self::fail('Invalid continuation accepted'); }
            catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
        }
        self::assertSame(1, DB::table('personal_work_entries')->count());
        self::assertSame(0, DB::table('work_job_continuations')->count());
        $template = (array) DB::table('personal_work_entries')->find($root);
        unset($template['id']);
        for ($i = 0; $i < 99; $i++) DB::table('personal_work_entries')->insert(array_replace($template, ['source_entry_id' => $root]));
        self::assertCount(100, app(\App\Services\WorkJobReader::class)->series($this->user(), $root)['items']);
        try { $this->continueWork($root); self::fail('Tree exceeded limit'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertSame(100, DB::table('personal_work_entries')->count());
        self::assertSame(0, DB::table('work_job_continuations')->count());
        DB::table('personal_work_entries')->where('id', $root)->update(['source_entry_id' => $root]);
        try { app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $root, null); self::fail('Cyclic completion'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertSame(0, DB::table('personal_work_entries')->whereNotNull('completed_at')->count());
    }

    public function test_legacy_source_creates_one_simple_continuation_without_mutating_parent_and_with_typed_replay(): void
    {
        DB::table('project_jobs')->insert(['id' => 1, 'company_id' => 1, 'title' => '同じ案件']);
        DB::table('project_job_assignments')->insert(['id' => 1, 'project_job_id' => 1, 'user_id' => 1, 'sender_id' => 1, 'title' => '旧形式', 'amounts_unit' => 'file']);
        $reader = app(\App\Services\WorkJobReader::class);
        $prefill = $reader->continuationPrefill($this->user(), 1, null);
        self::assertSame('旧形式', $prefill['title']); self::assertSame('ファイル', $prefill['actual_unit']);
        self::assertSame('department', $prefill['entry_visibility']);
        DB::table('events')->insert(['id' => 100, 'user_id' => 1, 'project_job_assignment_id' => 1, 'visibility' => 'private']);
        self::assertSame('private', $reader->continuationPrefill($this->user(), 1, null)['entry_visibility']);
        self::assertArrayNotHasKey('actual_quantity', $prefill); self::assertArrayNotHasKey('planned_start_time', $prefill);
        $before = (array) DB::table('project_job_assignments')->find(1);
        $request = $this->request($this->payload(['work_date' => '2099-12-02', 'actual_quantity' => null, 'actual_unit' => 'ファイル', 'request_token' => 'ae305d54-75b4-431b-adb2-eb6b9e546014']));
        $mutation = app(\App\Services\WorkJobMutation::class);
        $created = $mutation->continueEntry($request, 1, null, app(DepartmentWorkController::class), 'assignment');
        $child = $created['id']; self::assertFalse($created['replayed']);
        self::assertSame($child, $mutation->continueEntry($request, 1, null, app(DepartmentWorkController::class), 'assignment')['id']);
        $entry = DB::table('personal_work_entries')->find($child);
        self::assertNull($entry->source_entry_id); self::assertSame(1, $entry->source_assignment_id); self::assertNull($entry->actual_quantity);
        self::assertSame($before, (array) DB::table('project_job_assignments')->find(1));
        self::assertSame(1, DB::table('work_job_continuations')->count());
        self::assertSame(1, DB::table('personal_work_entries')->count());
        $receipt = DB::table('work_job_continuations')->first(); self::assertNull($receipt->source_entry_id); self::assertSame(1, $receipt->source_assignment_id);
        // Entry and assignment ID 1 must not alias even with the same request UUID.
        try { $mutation->continueEntry($request, $child, null, app(DepartmentWorkController::class)); self::fail('Typed source mismatch accepted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        $series = $reader->series($this->user(), 1, null, true, 'assignment');
        self::assertSame(['assignment:1', 'entry:'.$child], array_column($series['items'], 'key'));
        self::assertTrue($series['items'][0]['current']); self::assertTrue($series['canContinue']); self::assertTrue($series['operationsEnabled']);
        self::assertSame(30, $series['totals']['afterLunch']);
        try { app(\App\Http\Controllers\User\MyProjectJobController::class)->destroyAssignment($this->request(), \App\Models\ProjectJobAssignmentByMyself::findOrFail(1)); self::fail('Legacy parent deleted before checking continuation'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        self::assertSame($before, (array) DB::table('project_job_assignments')->find(1));
        foreach ([[$this->user(['user_role' => 'user']), null], [$this->user(), 2]] as [$user, $context]) {
            try { $mutation->continueEntry($this->request($request->all(), $user, $context), 1, $context, app(DepartmentWorkController::class), 'assignment'); self::fail('Legacy unauthorized continuation'); }
            catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertTrue(true); }
        }
    }

    public function test_legacy_null_company_project_is_allowed_only_through_authorized_source_and_old_page_trial_prop_is_scoped(): void
    {
        DB::table('project_jobs')->insert([['id' => 1, 'title' => '過去案件', 'company_id' => null], ['id' => 2, 'title' => '他社案件', 'company_id' => 2]]);
        DB::table('project_job_assignments')->insert(['id' => 1, 'project_job_id' => 1, 'user_id' => 1, 'sender_id' => 1, 'title' => '旧形式']);
        $payload = $this->payload(['work_date' => '2099-12-02', 'project_job_id' => 1, 'broad_project_id' => null, 'request_token' => 'be305d54-75b4-431b-adb2-eb6b9e546014']);
        try { $this->save($this->request($payload)); self::fail('Unscoped NULL company accepted'); }
        catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        $request = $this->request($payload);
        $created = app(\App\Services\WorkJobMutation::class)->continueEntry($request, 1, null, app(DepartmentWorkController::class), 'assignment');
        self::assertSame(1, DB::table('personal_work_entries')->find($created['id'])->project_job_id);
        try { app(\App\Services\WorkJobMutation::class)->continueEntry($this->request(array_replace($payload, ['project_job_id' => 2])), 1, null, app(DepartmentWorkController::class), 'assignment'); self::fail('Foreign company allowed'); }
        catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        $sync = $this->createMock(\App\Services\MGinbon\MGinbonAssignmentSyncService::class);
        $sync->method('detailContext')->willReturn(null); $this->app->instance(\App\Services\MGinbon\MGinbonAssignmentSyncService::class, $sync);
        $request = $this->request([], null, null, 'GET'); $request->headers->set('X-Inertia', 'true');
        $controller = app(\App\Http\Controllers\User\MyProjectJobController::class);
        $assignment = \App\Models\ProjectJobAssignmentByMyself::findOrFail(1);
        $props = $controller->showAssignment($assignment, $request)->toResponse($request)->getData(true)['props'];
        self::assertSame(['type' => 'assignment', 'id' => 1], $props['workJobContinuation']['source']);
        self::assertCount(2, $props['workJobContinuation']['series']['items']);
        $ordinary = $this->request([], $this->user(['user_role' => 'user']), null, 'GET'); $ordinary->headers->set('X-Inertia', 'true');
        self::assertArrayNotHasKey('workJobContinuation', $controller->showAssignment($assignment, $ordinary)->toResponse($ordinary)->getData(true)['props']);
    }

    public function test_source_schema_extension_preserves_existing_simple_continuation_receipts(): void
    {
        $root = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        $this->continueWork($root, ['work_date' => '2099-12-02']);
        $before = (array) DB::table('work_job_continuations')->first();
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_03_180001_support_legacy_work_continuation_sources.php';
        // Reconstruct the former schema solely in this test's explicitly verified memory database.
        self::assertSame('sqlite', DB::getDefaultConnection());
        self::assertSame(':memory:', DB::connection()->getDatabaseName());
        $migration->down();
        self::assertFalse(Schema::hasColumn('work_job_continuations', 'source_assignment_id'));
        $migration->up();
        self::assertSame($before, (array) DB::table('work_job_continuations')->first());
        self::assertSame(200, $this->continueWork($root, ['work_date' => '2099-12-02'])->getStatusCode());
        self::assertSame(2, DB::table('personal_work_entries')->count());
    }

    public function test_past_picker_includes_simple_work_with_typed_ids_filters_and_trial_scope(): void
    {
        \Carbon\Carbon::setTestNow('2026-11-04 10:00:00');
        try {
            $simple = $this->save($this->request($this->payload(['work_date' => '2026-11-03', 'title' => null])))->getData(true)['id'];
            DB::table('project_jobs')->insert(['id' => 1, 'company_id' => 1, 'title' => '旧案件']);
            DB::table('project_job_assignments')->insert(['id' => $simple, 'project_job_id' => 1, 'user_id' => 1, 'sender_id' => 1, 'title' => '旧ジョブ', 'desired_end_date' => '2026-11-03', 'created_at' => '2026-11-03 09:00:00']);
            $past = fn ($data = [], $user = null, $context = null) => app(\App\Http\Controllers\User\MyProjectJobController::class)->pastData($this->request($data, $user, $context, 'GET'))->getData(true);
            $rows = collect($past()['records'])->keyBy('key');
            self::assertCount(2, $rows); self::assertArrayHasKey('entry:'.$simple, $rows); self::assertArrayHasKey('assignment:'.$simple, $rows);
            self::assertSame('承認済み分類', $rows['entry:'.$simple]['title']);
            self::assertNull($rows['entry:'.$simple]['project_job_id']);
            self::assertArrayNotHasKey('actual_quantity', $rows['entry:'.$simple]['work_entry']);
            self::assertArrayNotHasKey('result_note', $rows['entry:'.$simple]['work_entry']);
            self::assertArrayNotHasKey('planned_start_time', $rows['entry:'.$simple]['work_entry']);
            foreach ([[$this->user(['user_role' => 'user']), null], [$this->user(), 2]] as [$user, $context]) {
                $ordinary = $past([], $user, $context)['records']; self::assertCount(1, $ordinary); self::assertArrayNotHasKey('source_type', $ordinary[0]);
            }
            DB::table('personal_work_entries')->where('id', $simple)->update(['completed_at' => '2026-11-03 18:00:00']);
            self::assertCount(1, $past(['hide_completed' => 1])['records']);
            DB::table('personal_work_entries')->where('id', $simple)->update(['user_id' => 2]);
            self::assertCount(1, $past()['records']);
            DB::table('personal_work_entries')->where('id', $simple)->update(['user_id' => 1, 'company_id' => 2]);
            self::assertCount(1, $past()['records']);
            DB::table('personal_work_entries')->where('id', $simple)->update(['company_id' => 1, 'source_event_id' => 99]);
            self::assertCount(1, $past()['records']);
            DB::table('personal_work_entries')->where('id', $simple)->update(['source_event_id' => null, 'created_at' => '2026-09-01', 'work_date' => '2026-09-01']);
            self::assertCount(1, $past()['records']);
            self::assertCount(2, $past(['mode' => 'project'])['records']);
            self::assertSame(1, DB::table('personal_work_entries')->count());
        } finally { \Carbon\Carbon::setTestNow(); }
    }

    public function test_simple_history_client_and_project_filters_and_independent_reuse_keep_source_unmodified(): void
    {
        \Carbon\Carbon::setTestNow('2026-11-04 10:00:00');
        try {
            DB::table('clients')->insert(['id' => 1, 'name' => '取引先', 'company_id' => 1]);
            DB::table('project_jobs')->insert(['id' => 1, 'title' => '旧NULL会社案件', 'company_id' => null, 'client_id' => 1]);
            $source = $this->save($this->request($this->payload(['work_date' => '2026-11-03'])))->getData(true)['id'];
            DB::table('personal_work_entries')->where('id', $source)->update(['project_job_id' => 1, 'broad_project_id' => null]);
            $data = app(\App\Http\Controllers\User\MyProjectJobController::class)->pastData($this->request(['mode' => 'project', 'client_id' => 1], null, null, 'GET'))->getData(true);
            self::assertCount(1, $data['records']); self::assertSame(1, $data['clients'][0]['id']); self::assertSame(1, $data['projects'][0]['id']);
            self::assertSame('旧NULL会社案件', $data['records'][0]['work_entry']['project_label']);
            $before = (array) DB::table('personal_work_entries')->find($source);
            $copy = $this->save($this->request($this->payload(['reuse_entry_id' => $source, 'project_job_id' => 1, 'broad_project_id' => null, 'source_entry_id' => $source, 'actual_quantity' => null])))->getData(true)['id'];
            self::assertNotSame($source, $copy); self::assertSame($before, (array) DB::table('personal_work_entries')->find($source));
            self::assertNull(DB::table('personal_work_entries')->find($copy)->source_entry_id);
            self::assertNull(DB::table('personal_work_entries')->find($copy)->source_assignment_id);
            self::assertSame(0, DB::table('work_job_continuations')->count());
            try { $this->save($this->request($this->payload(['reuse_entry_id' => $source]), $this->user(['id' => 2]))); self::fail('Foreign history reused'); }
            catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        } finally { \Carbon\Carbon::setTestNow(); }
    }

    public function test_simple_history_continuation_keeps_selected_calendar_window_and_does_not_copy_results(): void
    {
        \Carbon\Carbon::setTestNow('2026-11-04 10:00:00');
        try {
            $source = $this->save($this->request($this->payload(['work_date' => '2026-11-03', 'actual_quantity' => 90, 'result_note' => '元の結果'])))->getData(true)['id'];
            $before = (array) DB::table('personal_work_entries')->find($source);
            $history = app(\App\Http\Controllers\User\MyProjectJobController::class)->pastData($this->request([], null, null, 'GET'))->getData(true)['records'][0];
            self::assertSame('entry', $history['source_type']);
            $body = $history['work_entry'] + ['request_token' => 'ce305d54-75b4-431b-adb2-eb6b9e546014', 'work_date' => '2026-11-04', 'planned_start_time' => '13:15', 'planned_end_time' => '14:15', 'plan_slot' => 'hour', 'actual_quantity' => null, 'result_note' => null];
            $result = app(\App\Services\WorkJobMutation::class)->continueEntry($this->request($body), $source, null, app(DepartmentWorkController::class));
            $entry = DB::table('personal_work_entries')->find($result['id']);
            self::assertSame('2026-11-04', $entry->work_date); self::assertSame('13:15', $entry->planned_start_time); self::assertSame('14:15', $entry->planned_end_time);
            self::assertSame($source, $entry->source_entry_id); self::assertNull($entry->actual_quantity); self::assertNull($entry->result_note); self::assertNull($entry->completed_at);
            self::assertSame($before, (array) DB::table('personal_work_entries')->find($source));
            self::assertSame(0, DB::table('project_job_assignments')->count());
        } finally { \Carbon\Carbon::setTestNow(); }
    }

    public function test_simple_form_can_independently_quote_authorized_old_job_and_reject_ambiguous_or_received_sources(): void
    {
        DB::table('project_jobs')->insert(['id' => 1, 'title' => '旧案件', 'company_id' => null]);
        DB::table('project_job_assignments')->insert(['id' => 1, 'project_job_id' => 1, 'user_id' => 1, 'sender_id' => 1, 'title' => '【完了】旧ジョブ', 'amounts_unit' => 'file', 'created_at' => '2026-11-04 09:00:00']);
        $history = app(\App\Http\Controllers\User\MyProjectJobController::class)->pastData($this->request(['mode' => 'project'], null, null, 'GET'))->getData(true)['records'][0];
        self::assertSame('旧ジョブ', $history['simple_prefill']['title']); self::assertSame('ファイル', $history['simple_prefill']['actual_unit']);
        self::assertArrayNotHasKey('actual_quantity', $history['simple_prefill']); self::assertArrayNotHasKey('planned_start_time', $history['simple_prefill']);
        $before = (array) DB::table('project_job_assignments')->find(1);
        $body = $this->payload(['reuse_assignment_id' => 1, 'project_job_id' => 1, 'broad_project_id' => null, 'work_date' => '2026-12-04', 'planned_start_time' => '13:15', 'planned_end_time' => '14:15', 'actual_quantity' => null]);
        $id = $this->save($this->request($body))->getData(true)['id'];
        $entry = DB::table('personal_work_entries')->find($id);
        self::assertNull($entry->source_entry_id); self::assertNull($entry->source_assignment_id); self::assertNull($entry->completed_at);
        self::assertSame('13:15', $entry->planned_start_time); self::assertSame('14:15', $entry->planned_end_time);
        self::assertSame($before, (array) DB::table('project_job_assignments')->find(1));
        try { $this->save($this->request($body + ['reuse_entry_id' => $id])); self::fail('Two reuse sources accepted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        try { $this->save($this->request($body, $this->user(['id' => 2]))); self::fail('Other owner reused'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        DB::table('project_job_assignments')->insert(['id' => 2, 'project_job_id' => 1, 'user_id' => 1, 'sender_id' => 2, 'title' => '受信依頼']);
        try { $this->save($this->request(array_replace($body, ['reuse_assignment_id' => 2]))); self::fail('Request treated as self history'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        self::assertSame(1, DB::table('personal_work_entries')->count()); self::assertSame(1, DB::table('events')->count());
    }

    public function test_existing_request_picker_lists_only_own_unconverted_requests_without_writing_work(): void
    {
        DB::table('project_jobs')->insert(['id' => 1, 'title' => '依頼案件', 'company_id' => 1]);
        foreach ([['id' => 1, 'user_id' => 1, 'sender_id' => 2], ['id' => 2, 'user_id' => 2, 'sender_id' => 1], ['id' => 3, 'user_id' => 1, 'sender_id' => 2], ['id' => 4, 'user_id' => 1, 'sender_id' => 1, 'supersedes_assignment_id' => 3]] as $row) {
            DB::table('project_job_assignments')->insert($row + ['project_job_id' => 1, 'title' => '依頼', 'created_at' => '2026-11-04 09:00:00']);
        }
        $rows = app(\App\Http\Controllers\User\MyProjectJobController::class)->pendingRequests($this->request([], null, null, 'GET'))->getData(true)['records'];
        self::assertCount(1, $rows); self::assertSame(1, $rows[0]['id']);
        self::assertSame(0, DB::table('personal_work_entries')->count()); self::assertSame(4, DB::table('project_job_assignments')->count()); self::assertSame(1, DB::table('events')->count());
    }


    private function mixedFixture(bool $proof = false): array
    {
        DB::table('project_jobs')->insert(['id' => 10, 'company_id' => 1, 'title' => '合成案件', 'user_id' => 2]);
        DB::table('project_job_assignments')->insert(['id' => 10, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'title' => '旧元', 'job_type' => $proof ? 'proof' : 'work']);
        $entry = $this->save($this->request($this->payload(['work_date' => '2099-12-01'])))->getData(true)['id'];
        $sibling = $this->save($this->request($this->payload(['work_date' => '2099-12-02'])))->getData(true)['id'];
        DB::table('personal_work_entries')->whereIn('id', [$entry, $sibling])->update(['source_assignment_id' => 10]);
        DB::table('events')->insert(['id' => 100, 'user_id' => 1, 'project_job_assignment_id' => 10, 'title' => '旧予定', 'starts_at' => '2099-12-01 09:00:00', 'ends_at' => '2099-12-01 10:00:00']);
        DB::table('progress_cells')->insert(['assignment_id' => 10]);
        return [$entry, $sibling];
    }

    public function test_mixed_completion_updates_legacy_progress_events_and_notifies_once_without_changing_siblings(): void
    {
        [$entry, $sibling] = $this->mixedFixture();
        $before = (array) DB::table('personal_work_entries')->find($sibling);
        $mutation = app(\App\Services\WorkJobMutation::class);
        $result = $mutation->completeEntry($this->user(), $entry, null);
        self::assertSame([['type' => 'assignment', 'id' => 10], ['type' => 'entry', 'id' => $entry]], $result['completed_targets']);
        self::assertSame(0, $result['pending_syncs']);
        self::assertSame(1, DB::table('project_job_assignments')->find(10)->status_id);
        self::assertNotNull(DB::table('progress_cells')->first()->completed_at);
        self::assertSame('【完了】旧予定', DB::table('events')->find(100)->title);
        self::assertSame('2099-12-01 09:00:00', DB::table('events')->find(100)->starts_at);
        self::assertSame($before, (array) DB::table('personal_work_entries')->find($sibling));
        self::assertSame(1, DB::table('job_notifications')->where('type', 'progress_completed')->count());
        $at = DB::table('personal_work_entries')->find($entry)->completed_at;
        $mutation->completeEntry($this->user(), $entry, null);
        self::assertSame($at, DB::table('personal_work_entries')->find($entry)->completed_at);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertSame(1, DB::table('work_job_completion_syncs')->count());
        self::assertFalse(app(\App\Services\WorkJobReader::class)->series($this->user(), $entry)['allCompleted']);
    }

    public function test_legacy_completion_entrypoint_handles_alternating_types_and_keeps_descendants_pending(): void
    {
        [$entry, $sibling] = $this->mixedFixture();
        DB::table('project_job_assignments')->insert(['id' => 11, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'title' => '旧続き', 'source_personal_work_entry_id' => $entry]);
        $child = $this->save($this->request($this->payload()))->getData(true)['id'];
        DB::table('personal_work_entries')->where('id', $child)->update(['source_assignment_id' => 11]);
        $result = app(\App\Http\Controllers\User\MyProjectJobController::class)->completeAssignment($this->request(), \App\Models\ProjectJobAssignment::findOrFail(11))->getData(true);
        self::assertTrue($result['success']); self::assertCount(3, $result['completed_targets']);
        self::assertSame(2, DB::table('project_job_assignments')->where('completed', true)->count());
        self::assertNotNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        foreach ([$sibling, $child] as $pending) self::assertNull(DB::table('personal_work_entries')->find($pending)->completed_at);
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_mixed_proof_completion_is_atomic_and_replay_does_not_duplicate_notifications(): void
    {
        [$entry] = $this->mixedFixture(true);
        DB::table('project_job_assignments')->insert(['id' => 20, 'project_job_id' => 10, 'user_id' => 2, 'sender_id' => 2]);
        DB::table('proof_requests')->insert(['id' => 1, 'project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '合成校正', 'status' => 'in_progress', 'project_job_assignment_id' => 20]);
        $mutation = app(\App\Services\WorkJobMutation::class);
        $mutation->completeEntry($this->user(), $entry, null);
        self::assertSame('completed', DB::table('proof_requests')->find(1)->status);
        self::assertNotNull(DB::table('proof_requests')->find(1)->completed_at);
        self::assertNotNull(DB::table('project_job_assignments')->find(20)->proof_completed_at);
        self::assertFalse((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
        $mutation->completeEntry($this->user(), $entry, null);
        self::assertSame(2, DB::table('job_notifications')->count());
    }

    public function test_ambiguous_proof_and_notification_failure_roll_back_every_default_database_change(): void
    {
        [$entry] = $this->mixedFixture(true);
        $data = ['project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '合成校正', 'status' => 'assigned'];
        DB::table('proof_requests')->insert([$data, $data]);
        try { app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null); self::fail('Ambiguous proof accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertFalse((bool) DB::table('project_job_assignments')->find(10)->completed);
        self::assertNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        self::assertNull(DB::table('progress_cells')->first()->completed_at);
        self::assertSame('旧予定', DB::table('events')->find(100)->title);
        self::assertSame(0, DB::table('work_job_completion_syncs')->count());
        DB::table('proof_requests')->where('id', 2)->update(['status' => 'cancelled']);
        DB::statement("CREATE TRIGGER fail_synthetic_notification BEFORE INSERT ON job_notifications BEGIN SELECT RAISE(ABORT, 'synthetic notification failure'); END");
        try { app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null); self::fail('Notification failure swallowed'); }
        catch (\Illuminate\Database\QueryException $e) { self::assertStringContainsString('synthetic notification failure', $e->getMessage()); }
        self::assertSame('assigned', DB::table('proof_requests')->find(1)->status);
        self::assertFalse((bool) DB::table('project_job_assignments')->find(10)->completed);
        self::assertNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        self::assertNull(DB::table('progress_cells')->first()->completed_at);
        self::assertSame('旧予定', DB::table('events')->find(100)->title);
        self::assertSame(0, DB::table('job_notifications')->count()); self::assertSame(0, DB::table('work_job_completion_syncs')->count());
    }

    public function test_mginbon_failure_leaves_durable_pending_receipt_and_retry_is_idempotent(): void
    {
        [$entry] = $this->mixedFixture();
        $sync = new class extends \App\Services\MGinbon\MGinbonAssignmentSyncService {
            public bool $fail = true;
            public int $calls = 0;
            public function complete(\App\Models\ProjectJobAssignment $assignment, ?int $actorId = null, ?string $occurredOn = null, bool $includeAncestors = true): void {
                $this->calls++;
                if ($this->fail) throw new \RuntimeException('Synthetic separate DB unavailable');
            }
        };
        $this->app->instance(\App\Services\MGinbon\MGinbonAssignmentSyncService::class, $sync);
        $result = app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null);
        self::assertSame(1, $result['pending_syncs']); self::assertSame(1, $sync->calls);
        self::assertNotNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        $receipt = DB::table('work_job_completion_syncs')->first();
        self::assertSame(1, $receipt->attempts); self::assertNull($receipt->synced_at);
        self::assertTrue(app(\App\Services\WorkJobReader::class)->series($this->user(), $entry)['syncPending']);
        $sync->fail = false;
        self::assertSame(0, \Illuminate\Support\Facades\Artisan::call('work-jobs:sync-completions'));
        self::assertSame(2, $sync->calls); self::assertNotNull(DB::table('work_job_completion_syncs')->first()->synced_at);
        self::assertFalse(app(\App\Services\WorkJobReader::class)->series($this->user(), $entry)['syncPending']);
        app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null);
        self::assertSame(2, $sync->calls); self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_mixed_completion_rejects_foreign_ancestor_before_any_write(): void
    {
        [$entry] = $this->mixedFixture();
        DB::table('project_job_assignments')->where('id', 10)->update(['user_id' => 2, 'sender_id' => 2]);
        try { app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null); self::fail('Foreign ancestor completed'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        self::assertNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        self::assertFalse((bool) DB::table('project_job_assignments')->find(10)->completed);
        self::assertSame(0, DB::table('work_job_completion_syncs')->count());
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_actual_separate_memory_mginbon_sync_preserves_milestones_and_is_idempotent(): void
    {
        [$entry] = $this->mixedFixture();
        config(['database.connections.mginbon' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('mginbon');
        $db = DB::connection('mginbon');
        self::assertSame(':memory:', $db->getDatabaseName());
        foreach ([
            'mginbon_work_packages' => 'id integer primary key, project_job_assignment_id integer, mginbon_item_id integer, mginbon_project_id integer, mginbon_stage_definition_id integer, status text, completed_at text, updated_at text',
            'mginbon_work_package_tasks' => 'mginbon_work_package_id integer, mginbon_stage_task_id integer',
            'mginbon_stage_tasks' => 'id integer primary key, project_job_assignment_id integer, mginbon_item_subject_id integer, status text, updated_at text',
            'mginbon_stage_definitions' => 'id integer primary key, code text',
            'mginbon_milestones' => 'id integer primary key, mginbon_item_id integer, mginbon_item_subject_id integer, code text, occurred_on text, source text, created_at text, updated_at text',
            'mginbon_change_logs' => 'id integer primary key, mginbon_project_id integer, mginbon_item_id integer, mginbon_item_subject_id integer, changed_by integer, field_path text, old_value text, new_value text, source text, created_at text, updated_at text',
        ] as $table => $columns) $db->statement("create table $table ($columns)");
        $db->table('mginbon_stage_definitions')->insert(['id' => 1, 'code' => 'text_input']);
        $db->table('mginbon_work_packages')->insert(['id' => 1, 'project_job_assignment_id' => 10, 'mginbon_item_id' => 1, 'mginbon_project_id' => 1, 'mginbon_stage_definition_id' => 1, 'status' => 'in_progress']);
        $db->table('mginbon_stage_tasks')->insert(['id' => 1, 'project_job_assignment_id' => 10, 'mginbon_item_subject_id' => 1, 'status' => 'assigned']);
        $db->table('mginbon_work_package_tasks')->insert(['mginbon_work_package_id' => 1, 'mginbon_stage_task_id' => 1]);
        $result = app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null);
        self::assertSame(0, $result['pending_syncs']);
        self::assertSame('completed', $db->table('mginbon_work_packages')->find(1)->status);
        self::assertSame('completed', $db->table('mginbon_stage_tasks')->find(1)->status);
        $receipt = DB::table('work_job_completion_syncs')->first();
        self::assertSame($receipt->occurred_on, $db->table('mginbon_milestones')->first()->occurred_on);
        self::assertSame('text_input_completed_on', $db->table('mginbon_milestones')->first()->code);
        self::assertSame(2, $db->table('mginbon_change_logs')->count());
        // Simulate the main-DB acknowledgement being lost after the separate DB committed.
        DB::table('work_job_completion_syncs')->where('id', $receipt->id)->update(['synced_at' => null]);
        self::assertTrue(app(\App\Services\WorkJobCompletionSync::class)->run($receipt->id));
        self::assertSame(2, $db->table('mginbon_change_logs')->count());
        self::assertSame(1, $db->table('mginbon_milestones')->count());
        DB::purge('mginbon');
    }

    public function test_replaying_completed_proof_ancestor_does_not_complete_a_later_unrelated_request(): void
    {
        [$entry] = $this->mixedFixture(true);
        DB::table('proof_requests')->insert(['project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '初回', 'status' => 'assigned']);
        $mutation = app(\App\Services\WorkJobMutation::class);
        $mutation->completeEntry($this->user(), $entry, null);
        DB::table('proof_requests')->insert(['project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '後の依頼', 'status' => 'assigned']);
        $mutation->completeEntry($this->user(), $entry, null);
        self::assertSame('assigned', DB::table('proof_requests')->find(2)->status);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
    }

    public function test_superseded_proof_request_is_completed_only_for_the_same_owner_and_project(): void
    {
        [$entry] = $this->mixedFixture();
        DB::table('project_job_assignments')->insert(['id' => 20, 'project_job_id' => 10, 'user_id' => 2, 'sender_id' => 2, 'job_type' => 'proof']);
        DB::table('project_job_assignments')->where('id', 10)->update(['supersedes_assignment_id' => 20]);
        DB::table('proof_requests')->insert(['project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '合成依頼', 'status' => 'assigned']);
        try { app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null); self::fail('Foreign superseded request accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        self::assertSame(0, DB::table('work_job_completion_syncs')->count());
        DB::table('project_job_assignments')->where('id', 20)->update(['user_id' => 1]);
        app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null);
        self::assertTrue((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertSame('completed', DB::table('proof_requests')->first()->status);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
    }

    public function test_pending_sync_protects_legacy_deletion_before_any_separate_database_release(): void
    {
        DB::table('project_jobs')->insert(['id' => 10, 'company_id' => 1]);
        DB::table('project_job_assignments')->insert(['id' => 10, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'completed' => true]);
        DB::table('work_job_completion_syncs')->insert(['assignment_id' => 10, 'user_id' => 1, 'company_id' => 1, 'department_id' => 1, 'occurred_on' => '2026-11-05']);
        try { app(\App\Http\Controllers\User\MyProjectJobController::class)->destroyAssignment($this->request(), \App\Models\ProjectJobAssignmentByMyself::findOrFail(10)); self::fail('Pending legacy job deleted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); self::assertStringContainsString('再試行待ち', $e->getMessage()); }
        self::assertSame(1, DB::table('project_job_assignments')->count());
        self::assertSame(1, DB::table('work_job_completion_syncs')->count());
    }
}

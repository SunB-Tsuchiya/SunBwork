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
    private array $completionToastLevels = [];

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
        Schema::create('company_clients', function (Blueprint $t) { $t->integer('company_id'); $t->integer('client_id'); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name')->nullable(); $t->string('user_role')->nullable(); $t->integer('company_id'); $t->integer('department_id'); $t->boolean('is_ghost')->default(false); });
        Schema::create('project_job_coordinators', function (Blueprint $t) { $t->integer('project_job_id'); $t->integer('user_id'); });
        Schema::create('project_team_members', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('user_id'); });
        Schema::create('project_job_assignments', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('user_id'); $t->integer('company_id')->nullable(); $t->integer('department_id')->nullable(); $t->integer('sender_id')->nullable(); $t->integer('source_assignment_id')->nullable(); $t->integer('supersedes_assignment_id')->nullable(); $t->string('title')->nullable(); $t->boolean('completed')->default(false); $t->integer('status_id')->nullable(); $t->timestamp('proof_completed_at')->nullable(); $t->string('job_type')->nullable(); $t->date('desired_end_date')->nullable(); $t->string('amounts_unit')->nullable(); $t->timestamps(); });
        Schema::create('events', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('project_job_assignment_id')->nullable(); $t->dateTime('starts_at')->nullable(); $t->dateTime('ends_at')->nullable(); $t->dateTime('cancelled_at')->nullable(); $t->string('visibility')->nullable(); $t->string('title')->nullable(); $t->timestamps(); });
        Schema::create('workflow_cells', function (Blueprint $t) { $t->id(); $t->integer('assignment_id')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('broad_projects', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->integer('department_id'); $t->string('name'); $t->boolean('is_active'); $t->integer('parent_id')->nullable(); $t->integer('fiscal_year')->nullable(); $t->integer('client_id')->nullable();
        });
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_06_150001_create_broad_project_clients_table.php')->up();
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
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_05_150001_add_personal_work_detail_fields.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_06_120001_support_legacy_continuation_results.php')->up();
        Schema::create('statuses', function (Blueprint $t) { $t->id(); $t->string('key'); $t->string('slug'); });
        DB::table('statuses')->insert(['id' => 1, 'key' => 'completed', 'slug' => 'completed']);
        Schema::create('progress_cells', function (Blueprint $t) { $t->id(); $t->integer('assignment_id'); $t->timestamp('completed_at')->nullable(); $t->timestamps(); });
        Schema::create('proof_requests', function (Blueprint $t) { $t->id(); $t->integer('project_job_id')->nullable(); $t->integer('proofreader_id')->nullable(); $t->integer('proof_coordinator_id')->nullable(); $t->integer('project_job_assignment_id')->nullable(); $t->integer('requester_id'); $t->string('title'); $t->string('status'); $t->dateTime('deadline')->nullable(); $t->text('note')->nullable(); $t->timestamp('completed_at')->nullable(); $t->timestamps(); });
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_10_180001_add_quick_work_source_to_proof_requests.php')->up();
        Schema::create('job_notifications', function (Blueprint $t) { $t->id(); $t->string('type'); $t->integer('sender_id'); $t->integer('recipient_id'); $t->integer('project_job_id')->nullable(); $t->integer('assignment_id')->nullable(); $t->text('message'); $t->timestamps(); });
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
        $after = (array) DB::table('work_job_continuations')->first(); ksort($before); ksort($after); self::assertSame($before, $after);
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

    private function receivedFixture(array $original = []): void
    {
        DB::table('project_jobs')->insert(['id' => 10, 'company_id' => 1, 'title' => '合成案件', 'user_id' => 2]);
        DB::table('project_job_assignments')->insert($original + ['id' => 20, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 2, 'title' => '元依頼', 'job_type' => 'work']);
        DB::table('project_job_assignments')->insert(['id' => 10, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'title' => '取り込み', 'supersedes_assignment_id' => 20]);
        DB::table('events')->insert(['user_id' => 1, 'project_job_assignment_id' => 20, 'title' => '元依頼予定']);
        DB::table('workflow_cells')->insert(['assignment_id' => 20]);
    }

    public function test_received_legacy_only_completion_updates_original_and_notifies_requester_leaders_once(): void
    {
        $this->receivedFixture();
        DB::table('users')->insert(['id' => 3, 'company_id' => 1, 'department_id' => 1]);
        DB::table('project_job_coordinators')->insert([['project_job_id' => 10, 'user_id' => 3], ['project_job_id' => 10, 'user_id' => 2], ['project_job_id' => 10, 'user_id' => 1]]);
        $controller = app(\App\Http\Controllers\User\MyProjectJobController::class);
        $response = $controller->completeAssignment($this->request(), \App\Models\ProjectJobAssignment::findOrFail(10))->getData(true);
        self::assertTrue($response['success']); self::assertSame(0, $response['pending_syncs']);
        foreach ([10, 20] as $id) {
            $row = DB::table('project_job_assignments')->find($id);
            self::assertTrue((bool) $row->completed); self::assertSame(1, $row->status_id);
        }
        self::assertNotNull(DB::table('workflow_cells')->first()->completed_at);
        self::assertSame('【完了】元依頼予定', DB::table('events')->where('project_job_assignment_id', 20)->value('title'));
        self::assertSame([2, 3], DB::table('job_notifications')->orderBy('recipient_id')->pluck('recipient_id')->all());
        self::assertSame(['completed', 'completed_info'], DB::table('job_notifications')->orderBy('recipient_id')->pluck('type')->all());
        self::assertSame([20], DB::table('job_notifications')->distinct()->pluck('assignment_id')->all());
        self::assertSame(2, DB::table('work_job_completion_syncs')->whereNotNull('synced_at')->count());
        $controller->completeAssignment($this->request(), \App\Models\ProjectJobAssignment::findOrFail(10));
        self::assertSame(2, DB::table('job_notifications')->count());
    }

    public function test_received_mixed_continuation_completes_ancestors_but_not_siblings_or_descendants(): void
    {
        $this->receivedFixture();
        $entry = $this->save($this->request($this->payload()))->getData(true)['id'];
        $sibling = $this->save($this->request($this->payload()))->getData(true)['id'];
        $child = $this->save($this->request($this->payload()))->getData(true)['id'];
        DB::table('personal_work_entries')->whereIn('id', [$entry, $sibling])->update(['source_assignment_id' => 10]);
        DB::table('personal_work_entries')->where('id', $child)->update(['source_entry_id' => $entry]);
        $reader = app(\App\Services\WorkJobReader::class);
        self::assertTrue($reader->series($this->user(), $entry, null, false)['completionTargets'][0]['receivedRequest']);
        app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $entry, null);
        self::assertTrue((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertNotNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        foreach ([$sibling, $child] as $id) self::assertNull(DB::table('personal_work_entries')->find($id)->completed_at);
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_received_original_already_completed_is_not_notified_again(): void
    {
        $this->receivedFixture(['completed' => true]);
        app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null);
        self::assertTrue((bool) DB::table('project_job_assignments')->find(10)->completed);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_received_null_sender_notifies_only_known_leaders_and_progress_deduplicates(): void
    {
        $this->receivedFixture(['sender_id' => null]);
        DB::table('progress_cells')->insert(['assignment_id' => 20]);
        DB::table('progress_cells')->insert(['assignment_id' => 10]);
        app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertSame('progress_completed', DB::table('job_notifications')->value('type'));
        self::assertSame(2, DB::table('job_notifications')->value('recipient_id'));
        self::assertSame(0, DB::table('progress_cells')->whereNull('completed_at')->count());
    }

    public function test_received_progress_requester_also_leader_gets_only_one_notification(): void
    {
        $this->receivedFixture();
        DB::table('progress_cells')->insert(['assignment_id' => 20]);
        DB::table('project_job_coordinators')->insert(['project_job_id' => 10, 'user_id' => 2]);
        app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertSame('completed', DB::table('job_notifications')->value('type'));
    }

    public function test_received_invalid_original_relations_roll_back_without_notifications_or_syncs(): void
    {
        $this->receivedFixture();
        foreach ([['user_id' => 2], ['project_job_id' => 99], ['company_id' => 2], ['department_id' => 2], ['sender_id' => 1]] as $change) {
            DB::table('project_job_assignments')->where('id', 20)->update($change);
            try { app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null); self::fail('Invalid original accepted'); }
            catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
            self::assertFalse((bool) DB::table('project_job_assignments')->find(10)->completed);
            self::assertFalse((bool) DB::table('project_job_assignments')->find(20)->completed);
            self::assertSame(0, DB::table('job_notifications')->count()); self::assertSame(0, DB::table('work_job_completion_syncs')->count());
            DB::table('project_job_assignments')->where('id', 20)->update(['user_id' => 1, 'project_job_id' => 10, 'company_id' => null, 'department_id' => null, 'sender_id' => 2]);
        }
        DB::table('project_job_assignments')->where('id', 10)->update(['supersedes_assignment_id' => 999]);
        try { app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null); self::fail('Missing original accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertFalse((bool) DB::table('project_job_assignments')->find(10)->completed);
    }

    public function test_received_ambiguous_imports_and_foreign_original_events_are_rejected(): void
    {
        $this->receivedFixture();
        DB::table('project_job_assignments')->insert(['id' => 11, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'supersedes_assignment_id' => 20]);
        try { app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null); self::fail('Ambiguous imports accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        DB::table('project_job_assignments')->where('id', 11)->update(['supersedes_assignment_id' => null]);
        DB::table('events')->where('project_job_assignment_id', 20)->update(['user_id' => 2]);
        try { app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null); self::fail('Foreign event accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
        self::assertSame(0, DB::table('job_notifications')->count()); self::assertSame(0, DB::table('work_job_completion_syncs')->count());
    }

    public function test_received_notification_failure_rolls_back_original_import_events_workflow_and_outbox(): void
    {
        $this->receivedFixture();
        DB::statement("CREATE TRIGGER fail_received_notification BEFORE INSERT ON job_notifications BEGIN SELECT RAISE(ABORT, 'received notification failure'); END");
        try { app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null); self::fail('Notification failure swallowed'); }
        catch (\Illuminate\Database\QueryException $e) { self::assertStringContainsString('received notification failure', $e->getMessage()); }
        self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
        self::assertNull(DB::table('workflow_cells')->first()->completed_at);
        self::assertSame('元依頼予定', DB::table('events')->where('project_job_assignment_id', 20)->value('title'));
        self::assertSame(0, DB::table('job_notifications')->count()); self::assertSame(0, DB::table('work_job_completion_syncs')->count());
    }

    public function test_received_original_pending_sync_is_visible_and_protects_import_deletion_until_retry(): void
    {
        $this->receivedFixture();
        $sync = new class extends \App\Services\MGinbon\MGinbonAssignmentSyncService {
            public bool $failOriginal = true;
            public array $ids = [];
            public function complete(\App\Models\ProjectJobAssignment $assignment, ?int $actorId = null, ?string $occurredOn = null, bool $includeAncestors = true): void {
                $this->ids[] = (int) $assignment->id;
                if ($this->failOriginal && (int) $assignment->id === 20) throw new \RuntimeException('synthetic original sync failure');
            }
        };
        app()->instance(\App\Services\MGinbon\MGinbonAssignmentSyncService::class, $sync);
        $result = app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null);
        self::assertSame(1, $result['pending_syncs']); self::assertSame([10, 20], $sync->ids);
        self::assertTrue(app(\App\Services\WorkJobReader::class)->series($this->user(), 10, null, false, 'assignment')['syncPending']);
        try { app(\App\Http\Controllers\User\MyProjectJobController::class)->destroyAssignment($this->request(), \App\Models\ProjectJobAssignmentByMyself::findOrFail(10)); self::fail('Import deleted before original sync'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        $sync->failOriginal = false;
        self::assertSame(0, \Illuminate\Support\Facades\Artisan::call('work-jobs:sync-completions'));
        self::assertFalse(app(\App\Services\WorkJobReader::class)->series($this->user(), 10, null, false, 'assignment')['syncPending']);
        self::assertSame([10, 20, 20], $sync->ids); self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_received_completed_import_can_repair_its_pending_original_only_on_explicit_completion(): void
    {
        $this->receivedFixture();
        DB::table('project_job_assignments')->where('id', 10)->update(['completed' => true]);
        self::assertFalse((bool) DB::table('project_job_assignments')->find(20)->completed);
        app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 10, null);
        self::assertTrue((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_received_distinct_originals_in_one_project_each_keep_their_notification_and_unselected_sibling(): void
    {
        $this->receivedFixture();
        foreach ([21, 22] as $id) DB::table('project_job_assignments')->insert(['id' => $id, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 2]);
        foreach ([11 => 21, 12 => 22] as $id => $original) DB::table('project_job_assignments')->insert(['id' => $id, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'source_assignment_id' => 10, 'supersedes_assignment_id' => $original]);
        app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 11, null);
        self::assertSame([20, 21], DB::table('job_notifications')->orderBy('assignment_id')->pluck('assignment_id')->all());
        foreach ([12, 22] as $id) self::assertFalse((bool) DB::table('project_job_assignments')->find($id)->completed);
        app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', 11, null);
        self::assertSame(2, DB::table('job_notifications')->count());
    }

    public function test_received_new_completion_rejects_wrong_context_or_role_before_any_write(): void
    {
        $this->receivedFixture();
        foreach ([[$this->user(), 2], [$this->user(['user_role' => 'user']), null]] as [$user, $context]) {
            try { app(\App\Services\WorkJobMutation::class)->completeWork($user, 'assignment', 10, $context); self::fail('Trial scope bypass'); }
            catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertNotNull($e); }
        }
        self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    private function receivedPrefillFixture(): void
    {
        $this->legacyContinuationFixture();
        Schema::table('project_jobs', function (Blueprint $t) { $t->integer('size_id')->nullable(); $t->integer('page_count')->nullable(); $t->timestamps(); });
        Schema::table('project_job_assignments', function (Blueprint $t) { $t->boolean('is_registered')->default(false); $t->integer('coordinator_assignment_id')->nullable(); });
        foreach (['sizes', 'stages', 'work_item_types', 'difficulties'] as $name) Schema::create($name, function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('sort_order')->default(0); $t->integer('order_index')->default(0); $t->string('group')->nullable(); $t->string('width')->nullable(); $t->string('height')->nullable(); $t->string('unit')->nullable(); $t->integer('company_id')->nullable(); $t->integer('department_id')->nullable(); });
        DB::table('sizes')->insert([['id' => 5, 'name' => 'A5'], ['id' => 6, 'name' => 'B5']]);
        foreach (['stages', 'work_item_types', 'difficulties'] as $name) DB::table($name)->insert(['id' => 1, 'name' => '合成候補']);
        DB::table('project_jobs')->where('id', 10)->update(['title' => '合成案件', 'size_id' => 5, 'page_count' => 90]);
        $info = ['total_pages' => 12, 'total_files' => 2, 'files' => [['name' => '合成.pdf', 'ext' => 'pdf', 'doc_size' => 'B5']]];
        DB::table('project_job_assignments')->insert(['id' => 200, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 2, 'title' => '合成受信', 'job_type' => 'work',
            'detail' => '合成概要', 'work_item_type_id' => 1, 'stage_id' => 1, 'difficulty_id' => 1, 'estimated_hours' => '2', 'desired_time' => '17:30',
            'desired_end_date' => '2099-12-20', 'file_info' => json_encode($info), 'amounts' => 33, 'amounts_unit' => 'file']);
        foreach (['progress', 'workflow'] as $prefix) {
            Schema::table($prefix.'_cells', function (Blueprint $t) use ($prefix) { $t->integer('row_id')->nullable(); $t->string($prefix === 'progress' ? 'col_key' : 'stage_key')->nullable(); $t->integer('value_user_id')->nullable(); if ($prefix === 'workflow') $t->integer('assigned_user_id')->nullable(); });
            Schema::create($prefix.'_rows', function (Blueprint $t) { $t->id(); $t->integer('sheet_id'); });
            Schema::create($prefix.'_sheets', function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); });
        }
    }

    private function receivedSeed(): array
    {
        return app(\App\Services\ReceivedJobPrefill::class)->seed($this->request([], null, null, 'GET'), 200);
    }

    private function saveReceived(array $changes = [], ?array $seed = null)
    {
        $seed ??= $this->receivedSeed();
        $slot = $changes + $seed['assignment'] + ['title' => '受信の作業', 'desired_start_date' => '2099-12-02', 'start_time' => '00:00'];
        // Simulate the form's work time, independent of the request deadline.
        $slot['desired_time'] = $changes['desired_time'] ?? '01:00';
        return app(\App\Http\Controllers\User\ProjectJobAssignmentController::class)->store($this->request(['myjob_modal' => 1, 'assignments' => [$slot]]), \App\Models\ProjectJob::findOrFail(10));
    }

    public function test_received_prefill_read_returns_complete_defaults_candidates_and_makes_no_changes(): void
    {
        $this->receivedPrefillFixture();
        $seed = app(\App\Http\Controllers\User\MyProjectJobController::class)->receivedPrefill($this->request([], null, null, 'GET'), 200)->getData(true);
        $a = $seed['assignment'];
        self::assertSame(10, $a['_client_id']); self::assertSame(6, $a['size_id']);
        self::assertSame(5, $seed['project']['size_id']); self::assertSame('その他', $seed['client']['name']);
        self::assertSame(12, $a['amounts']); self::assertSame('page', $a['amounts_unit']);
        foreach (['work_item_type_id', 'stage_id', 'difficulty_id'] as $field) self::assertSame(1, (int) $a[$field]);
        self::assertSame('合成概要', $a['detail']); self::assertSame('2099-12-20', $a['desired_end_date']);
        self::assertSame('合成.pdf', $a['file_info']['files'][0]['name']);
        self::assertSame(64, strlen($a['_request_revision'])); self::assertSame(200, $a['supersedes_assignment_id']);
        $props = app(\App\Services\ReceivedJobPrefill::class)->mergeProps(['userClients' => [], 'userProjects' => []], $seed);
        self::assertSame([$seed['project']], $props['userProjects']); self::assertSame([$seed['client']], $props['userClients']);
        self::assertSame(1, DB::table('project_job_assignments')->count()); self::assertFalse((bool) DB::table('project_job_assignments')->find(200)->is_registered);
        self::assertSame(1, DB::table('events')->count()); self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_received_prefill_size_quantity_fallbacks_keep_zero_and_explicit_values(): void
    {
        $this->receivedPrefillFixture();
        DB::table('project_job_assignments')->where('id', 200)->update(['size_id' => 5, 'file_info' => null, 'amounts' => 0]);
        $a = $this->receivedSeed()['assignment']; self::assertSame(5, (int) $a['size_id']); self::assertSame(0, $a['amounts']); self::assertSame('file', $a['amounts_unit']);
        DB::table('project_job_assignments')->where('id', 200)->update(['size_id' => null, 'amounts' => null]);
        $a = $this->receivedSeed()['assignment']; self::assertSame(5, (int) $a['size_id']); self::assertSame(90, $a['amounts']); self::assertSame('page', $a['amounts_unit']);
        DB::table('project_jobs')->where('id', 10)->update(['size_id' => null, 'page_count' => null]);
        $a = $this->receivedSeed()['assignment']; self::assertNull($a['size_id']); self::assertNull($a['amounts']);
    }

    public function test_received_prefill_and_save_reject_foreign_context_owner_scope_registered_or_completed(): void
    {
        $this->receivedPrefillFixture();
        foreach ([[$this->user(['user_role' => 'user']), null], [$this->user(), 2], [$this->user(['id' => 2]), null]] as [$actor, $context]) {
            try { app(\App\Services\ReceivedJobPrefill::class)->seed($this->request([], $actor, $context, 'GET'), 200); self::fail('Foreign prefill'); }
            catch (HttpException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        }
        foreach ([['company_id' => 2], ['department_id' => 2]] as $change) {
            DB::table('project_job_assignments')->where('id', 200)->update($change);
            try { $this->receivedSeed(); self::fail('Foreign scope'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
            DB::table('project_job_assignments')->where('id', 200)->update(['company_id' => null, 'department_id' => null]);
        }
        foreach (['is_registered', 'completed'] as $field) {
            DB::table('project_job_assignments')->where('id', 200)->update([$field => true]);
            try { $this->receivedSeed(); self::fail('Registered/completed accepted'); } catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
            DB::table('project_job_assignments')->where('id', 200)->update([$field => false]);
        }
        self::assertSame(1, DB::table('project_job_assignments')->count());
    }

    public function test_received_save_preserves_details_editable_values_and_dragged_work_time_and_rejects_replay(): void
    {
        $this->receivedPrefillFixture(); $seed = $this->receivedSeed();
        $response = $this->saveReceived(['detail' => '本人の追記', 'amounts' => 7], $seed);
        self::assertSame(201, $response->getStatusCode());
        $id = $response->getData(true)['assignment_id']; $a = \App\Models\ProjectJobAssignment::findOrFail($id);
        self::assertSame('本人の追記', $a->detail); self::assertSame(7, (int) $a->amounts);
        self::assertSame(6, (int) $a->size_id); self::assertSame(200, $a->supersedes_assignment_id); self::assertNull($a->source_assignment_id);
        self::assertSame($seed['assignment']['file_info'], $a->file_info);
        $event = DB::table('events')->where('project_job_assignment_id', $id)->first();
        self::assertSame('2099-12-02 00:00:00', $event->starts_at); self::assertSame('2099-12-02 01:00:00', $event->ends_at);
        self::assertTrue((bool) DB::table('project_job_assignments')->find(200)->is_registered);
        try { $this->saveReceived([], $seed); self::fail('Replay accepted'); } catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertSame(2, DB::table('project_job_assignments')->count()); self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_received_save_detects_changed_original_project_or_missing_revision_before_writing(): void
    {
        $this->receivedPrefillFixture(); $seed = $this->receivedSeed();
        DB::table('project_job_assignments')->where('id', 200)->update(['detail' => '依頼主が変更']);
        try { $this->saveReceived([], $seed); self::fail('Stale source'); } catch (ValidationException $e) { self::assertArrayHasKey('assignments.0._request_revision', $e->errors()); }
        $seed = $this->receivedSeed(); DB::table('project_jobs')->where('id', 10)->update(['size_id' => 6]);
        try { $this->saveReceived([], $seed); self::fail('Stale project'); } catch (ValidationException $e) { self::assertArrayHasKey('assignments.0._request_revision', $e->errors()); }
        try { $this->saveReceived(['_request_revision' => null]); self::fail('Missing revision'); } catch (ValidationException $e) { self::assertArrayHasKey('assignments.0._request_revision', $e->errors()); }
        self::assertSame(1, DB::table('project_job_assignments')->count()); self::assertSame(1, DB::table('events')->count());
    }

    public function test_received_cell_links_are_authoritative_and_ambiguous_or_foreign_links_fail(): void
    {
        $this->receivedPrefillFixture();
        DB::table('progress_sheets')->insert(['id' => 1, 'project_job_id' => 10]);
        DB::table('progress_rows')->insert(['id' => 1, 'sheet_id' => 1]);
        DB::table('progress_cells')->insert(['id' => 1, 'assignment_id' => 200, 'row_id' => 1, 'col_key' => 'worker']);
        $a = $this->receivedSeed()['assignment']; self::assertSame(1, $a['_progress_sheet_id']); self::assertSame('worker', $a['_col_key']);
        DB::table('progress_cells')->insert(['id' => 2, 'assignment_id' => 200, 'row_id' => 1, 'col_key' => 'worker2']);
        try { $this->receivedSeed(); self::fail('Ambiguous link'); } catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        DB::table('progress_cells')->where('id', 2)->update(['assignment_id' => 999]);
        DB::table('progress_sheets')->where('id', 1)->update(['project_job_id' => 99]);
        try { $this->receivedSeed(); self::fail('Foreign sheet'); } catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
    }

    public function test_received_event_save_failure_rolls_back_registration_and_created_job(): void
    {
        $this->receivedPrefillFixture();
        DB::statement("CREATE TRIGGER fail_received_prefill_event BEFORE INSERT ON events BEGIN SELECT RAISE(ABORT, 'received event failure'); END");
        try { $this->saveReceived(); self::fail('Event failure swallowed'); } catch (\Illuminate\Database\QueryException $e) { self::assertStringContainsString('received event failure', $e->getMessage()); }
        self::assertSame(1, DB::table('project_job_assignments')->count()); self::assertFalse((bool) DB::table('project_job_assignments')->find(200)->is_registered);
        self::assertSame(1, DB::table('events')->count());
    }

    public function test_received_legacy_detail_and_selection_endpoint_share_the_same_canonical_seed(): void
    {
        $this->receivedPrefillFixture();
        $request = $this->request(['source_job_assignment_id' => 200], null, null, 'GET');
        $request->headers->set('X-Inertia', 'true');
        $response = app(\App\Http\Controllers\ProjectJobs\ProjectJobAssignmentUserController::class)->create($request)->toResponse($request);
        $props = $response->getData(true)['props'];
        $seed = $this->receivedSeed();
        self::assertSame(json_decode(json_encode([$seed['assignment']]), true), $props['assignments']);
        self::assertSame($seed['project'], $props['projectJob']);
        self::assertTrue($props['receivedRequestPrefillEnabled']);
        self::assertSame(10, $props['userClients'][0]['id']);
        $eventRequest = $this->request(['source_job_assignment_id' => 200, 'date' => '2099-12-02', 'startHour' => 0, 'startMinute' => 0, 'endHour' => 1, 'endMinute' => 0], null, null, 'GET');
        $eventRequest->headers->set('X-Inertia', 'true');
        $eventProps = app(\App\Http\Controllers\EventController::class)->createJob($eventRequest)->toResponse($eventRequest)->getData(true)['props'];
        self::assertSame($props['assignments'], $eventProps['assignments']); self::assertSame($props['projectJob'], $eventProps['projectJob']);
        self::assertSame('00:00', $eventProps['prefillEvent']['start_time']); self::assertSame('01:00', $eventProps['prefillEvent']['desired_time']);
    }

    public function test_received_workflow_and_proof_reference_are_preserved_and_untrusted_cell_ids_are_ignored(): void
    {
        $this->receivedPrefillFixture();
        DB::table('project_job_assignments')->insert(['id' => 201, 'project_job_id' => 10, 'user_id' => 2, 'sender_id' => 2]);
        DB::table('project_job_assignments')->where('id', 200)->update(['job_type' => 'proof', 'coordinator_assignment_id' => 201]);
        DB::table('workflow_sheets')->insert(['id' => 1, 'project_job_id' => 10]);
        DB::table('workflow_rows')->insert(['id' => 1, 'sheet_id' => 1]);
        DB::table('workflow_cells')->insert(['id' => 1, 'assignment_id' => 200, 'row_id' => 1, 'stage_key' => 'proof']);
        $seed = $this->receivedSeed();
        self::assertSame(1, $seed['assignment']['_workflow_sheet_id']); self::assertSame(201, $seed['assignment']['coordinator_assignment_id']);
        $response = $this->saveReceived(['_row_id' => 999, '_col_key' => 'fake', '_workflow_sheet_id' => 999, 'coordinator_assignment_id' => 999], $seed);
        $id = $response->getData(true)['assignment_id'];
        self::assertSame($id, DB::table('workflow_cells')->find(1)->assignment_id);
        self::assertSame(1, DB::table('workflow_cells')->count());
        self::assertSame(201, DB::table('project_job_assignments')->find($id)->coordinator_assignment_id);
        self::assertSame(200, DB::table('project_job_assignments')->find($id)->supersedes_assignment_id);
    }

    public function test_received_new_token_cannot_bypass_trial_scope_when_company_context_changes(): void
    {
        $this->receivedPrefillFixture(); $seed = $this->receivedSeed();
        $slot = $seed['assignment'] + ['title' => '受信作業', 'desired_start_date' => '2099-12-02', 'start_time' => '00:00'];
        $slot['desired_time'] = '01:00';
        try { app(\App\Http\Controllers\User\ProjectJobAssignmentController::class)->store($this->request(['assignments' => [$slot]], null, 2), \App\Models\ProjectJob::findOrFail(10)); self::fail('Changed context accepted'); }
        catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        self::assertSame(1, DB::table('project_job_assignments')->count());
    }

    private function regularReceivedCompletionFixture(): \App\Models\Event
    {
        $this->receivedFixture();
        Schema::create('job_assignment_messages', function (Blueprint $t) { $t->id(); $t->integer('project_job_assignment_id'); $t->boolean('completed')->default(false); $t->integer('status_id')->nullable(); $t->timestamps(); });
        Schema::create('messages', function (Blueprint $t) { $t->id(); });
        DB::table('job_assignment_messages')->insert(['project_job_assignment_id' => 20]);
        DB::table('events')->insert(['id' => 101, 'user_id' => 1, 'project_job_assignment_id' => 10, 'title' => '合成の自己予定']);
        $this->app['auth']->setUser($this->user(['user_role' => 'user']));
        \Illuminate\Support\Facades\Event::fake([function ($name) {
            if ($name !== \App\Events\AssignmentStatusToast::class) return false;
            $this->completionToastLevels[] = DB::transactionLevel();
            return true;
        }]);
        return \App\Models\Event::findOrFail(101);
    }

    public function test_regular_worker_calendar_completion_notifies_original_requester_once_across_entrypoints(): void
    {
        $event = $this->regularReceivedCompletionFixture();
        $actor = $this->user(['user_role' => 'user']);
        $response = app(\App\Http\Controllers\EventController::class)->complete($this->request([], $actor), $event);
        self::assertSame(302, $response->getStatusCode());
        self::assertTrue((bool) DB::table('project_job_assignments')->find(10)->completed);
        self::assertTrue((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'completed')->where('recipient_id', 2)->where('assignment_id', 20)->count());
        self::assertTrue((bool) DB::table('job_assignment_messages')->first()->completed);
        self::assertSame(1, DB::table('job_assignment_messages')->first()->status_id);
        self::assertSame(0, DB::table('messages')->count());
        app(\App\Http\Controllers\User\MyProjectJobController::class)->completeAssignment($this->request([], $actor), \App\Models\ProjectJobAssignment::findOrFail(10));
        app(\App\Http\Controllers\ProjectJobs\JobBoxController::class)->completeAssignment($this->request([], $actor), \App\Models\ProjectJobAssignment::findOrFail(10));
        app(\App\Http\Controllers\EventController::class)->complete($this->request([], $actor), $event->fresh());
        self::assertSame(1, DB::table('job_notifications')->count());
        \Illuminate\Support\Facades\Event::assertDispatchedTimes(\App\Events\AssignmentStatusToast::class, 1);
        self::assertSame([0], $this->completionToastLevels);
        self::assertFalse(app(\App\Services\UnifiedMyJobBox::class)->available($actor));
    }

    public static function legacyReceivedEntrypoints(): array
    {
        return [['myjob'], ['jobbox']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('legacyReceivedEntrypoints')]
    public function test_regular_worker_legacy_json_entrypoints_notify_once_and_update_linked_cells(string $entrypoint): void
    {
        $this->regularReceivedCompletionFixture();
        DB::table('progress_cells')->insert(['assignment_id' => 10]);
        DB::table('workflow_cells')->insert(['assignment_id' => 10]);
        $actor = $this->user(['user_role' => 'user']);
        $controller = $entrypoint === 'myjob'
            ? app(\App\Http\Controllers\User\MyProjectJobController::class)
            : app(\App\Http\Controllers\ProjectJobs\JobBoxController::class);
        $result = $controller->completeAssignment($this->request([], $actor), \App\Models\ProjectJobAssignment::findOrFail(10))->getData(true);
        self::assertTrue($result['success']); self::assertSame(0, $result['pending_syncs']);
        self::assertSame(1, DB::table('job_notifications')->where('recipient_id', 2)->where('assignment_id', 20)->count());
        self::assertSame(0, DB::table('progress_cells')->whereNull('completed_at')->count());
        self::assertSame(0, DB::table('workflow_cells')->whereNull('completed_at')->count());
        self::assertTrue((bool) DB::table('job_assignment_messages')->first()->completed);
        $controller->completeAssignment($this->request([], $actor), \App\Models\ProjectJobAssignment::findOrFail(10));
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertFalse(app(\App\Services\UnifiedMyJobBox::class)->available($actor));
    }

    public function test_regular_worker_received_notification_deduplicates_requester_leader_and_excludes_actor(): void
    {
        $this->regularReceivedCompletionFixture();
        DB::table('users')->insert(['id' => 3, 'company_id' => 1, 'department_id' => 1]);
        DB::table('project_job_coordinators')->insert([['project_job_id' => 10, 'user_id' => 1], ['project_job_id' => 10, 'user_id' => 2], ['project_job_id' => 10, 'user_id' => 3]]);
        app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10);
        self::assertSame([2, 3], DB::table('job_notifications')->orderBy('recipient_id')->pluck('recipient_id')->all());
        self::assertSame(['completed', 'completed_info'], DB::table('job_notifications')->orderBy('recipient_id')->pluck('type')->all());
        self::assertSame([20], DB::table('job_notifications')->distinct()->pluck('assignment_id')->all());
    }

    public function test_regular_worker_received_continuation_completes_only_selected_and_ancestors(): void
    {
        $this->regularReceivedCompletionFixture();
        foreach ([11 => 10, 12 => 10, 13 => 11] as $id => $parent) DB::table('project_job_assignments')->insert(['id' => $id, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'source_assignment_id' => $parent]);
        $result = app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 11);
        self::assertSame([['type' => 'assignment', 'id' => 10], ['type' => 'assignment', 'id' => 11]], $result['completed_targets']);
        foreach ([10, 11, 20] as $id) self::assertTrue((bool) DB::table('project_job_assignments')->find($id)->completed);
        foreach ([12, 13] as $id) self::assertFalse((bool) DB::table('project_job_assignments')->find($id)->completed);
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_regular_worker_notification_failure_rolls_back_original_messages_events_cells_and_outbox(): void
    {
        $this->regularReceivedCompletionFixture();
        DB::table('users')->insert(['id' => 3, 'company_id' => 1, 'department_id' => 1]);
        DB::table('project_job_coordinators')->insert(['project_job_id' => 10, 'user_id' => 3]);
        DB::table('progress_cells')->insert(['assignment_id' => 10]);
        DB::statement("CREATE TRIGGER fail_second_legacy_recipient BEFORE INSERT ON job_notifications WHEN NEW.recipient_id = 3 BEGIN SELECT RAISE(ABORT, 'legacy notification failure'); END");
        try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10, null, 101); self::fail('Notification failure swallowed'); }
        catch (\Illuminate\Database\QueryException $e) { self::assertStringContainsString('legacy notification failure', $e->getMessage()); }
        self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
        self::assertFalse((bool) DB::table('job_assignment_messages')->first()->completed);
        self::assertNull(DB::table('progress_cells')->first()->completed_at);
        self::assertNull(DB::table('workflow_cells')->first()->completed_at);
        self::assertSame('合成の自己予定', DB::table('events')->find(101)->title);
        self::assertSame(0, DB::table('job_notifications')->count()); self::assertSame(0, DB::table('work_job_completion_syncs')->count());
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\AssignmentStatusToast::class);
    }

    public function test_regular_worker_already_completed_request_is_not_backfilled_or_toasted_on_replay(): void
    {
        $event = $this->regularReceivedCompletionFixture();
        DB::table('project_job_assignments')->whereIn('id', [10, 20])->update(['completed' => true, 'status_id' => 1]);
        app(\App\Http\Controllers\EventController::class)->complete($this->request([], $this->user(['user_role' => 'user'])), $event);
        self::assertSame(0, DB::table('job_notifications')->count());
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\AssignmentStatusToast::class);
        self::assertSame(0, DB::table('messages')->count());
    }

    public function test_regular_worker_standalone_and_direct_request_and_proof_keep_existing_paths(): void
    {
        $event = $this->regularReceivedCompletionFixture(); $actor = $this->user(['user_role' => 'user']);
        DB::table('project_job_assignments')->where('id', 10)->update(['supersedes_assignment_id' => null]);
        self::assertNull(app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($actor, 10));
        app(\App\Http\Controllers\EventController::class)->complete($this->request([], $actor), $event);
        self::assertSame(0, DB::table('job_notifications')->count());
        self::assertFalse((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertNull(app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($actor, 20));
        self::assertNull(app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['id' => 2, 'user_role' => 'admin']), 10));
        DB::table('project_job_assignments')->where('id', 10)->update(['supersedes_assignment_id' => 20, 'completed' => false]);
        DB::table('project_job_assignments')->where('id', 20)->update(['job_type' => 'proof']);
        DB::table('proof_requests')->insert(['id' => 1, 'project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '合成校正', 'status' => 'assigned']);
        app(\App\Http\Controllers\User\MyProjectJobController::class)->completeAssignment($this->request([], $actor), \App\Models\ProjectJobAssignment::findOrFail(10));
        self::assertSame('completed', DB::table('proof_requests')->find(1)->status);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
        self::assertSame(0, DB::table('job_notifications')->where('type', 'completed')->count());
    }

    public function test_regular_worker_original_owner_project_and_scope_mismatches_are_rejected_before_writes(): void
    {
        $this->regularReceivedCompletionFixture(); $actor = $this->user(['user_role' => 'user']);
        foreach ([['user_id' => 2], ['project_job_id' => 99], ['company_id' => 2], ['department_id' => 2], ['sender_id' => 1]] as $change) {
            DB::table('project_job_assignments')->where('id', 20)->update($change);
            try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($actor, 10); self::fail('Invalid original accepted'); }
            catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
            self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
            self::assertSame(0, DB::table('job_notifications')->count());
            DB::table('project_job_assignments')->where('id', 20)->update(['user_id' => 1, 'project_job_id' => 10, 'company_id' => null, 'department_id' => null, 'sender_id' => 2]);
        }
        try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($actor, 10, 2); self::fail('Changed context accepted'); }
        catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertNotNull($e); }
        try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['id' => 2, 'user_role' => 'user']), 10, null, 101); self::fail('Foreign calendar owner accepted'); }
        catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertNotNull($e); }
    }

    public function test_regular_worker_ambiguous_replacements_and_cyclic_ancestors_are_rejected(): void
    {
        $this->regularReceivedCompletionFixture(); $actor = $this->user(['user_role' => 'user']);
        DB::table('project_job_assignments')->insert(['id' => 11, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'supersedes_assignment_id' => 20]);
        try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($actor, 10); self::fail('Ambiguous import accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        DB::table('project_job_assignments')->where('id', 11)->update(['supersedes_assignment_id' => null, 'source_assignment_id' => 10]);
        DB::table('project_job_assignments')->where('id', 10)->update(['source_assignment_id' => 11]);
        try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($actor, 10); self::fail('Cycle accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertSame(0, DB::table('job_notifications')->count()); self::assertSame(0, DB::table('work_job_completion_syncs')->count());
    }

    public function test_regular_worker_mixed_parent_is_not_dropped_and_trial_guard_stays_closed(): void
    {
        $this->regularReceivedCompletionFixture();
        $entry = $this->save($this->request($this->payload()))->getData(true)['id'];
        DB::table('project_job_assignments')->where('id', 10)->update(['source_personal_work_entry_id' => $entry]);
        try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10); self::fail('User accessed typed trial series'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(), 10, null, 100); self::fail('Mismatched event binding accepted'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertNotNull($e); }
        self::assertNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        $result = app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(), 10, null, 101);
        self::assertSame(0, $result['pending_syncs']);
        self::assertNotNull(DB::table('personal_work_entries')->find($entry)->completed_at);
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_regular_worker_mginbon_uses_calendar_jst_day_after_commit_and_retries_without_notification_duplicates(): void
    {
        $event = $this->regularReceivedCompletionFixture();
        DB::table('events')->where('id', 101)->update(['starts_at' => '2001-01-02 00:15:00', 'ends_at' => '2001-01-02 01:15:00']);
        $state = (object) ['committed' => false];
        DB::listen(function ($query) use ($state) {
            if (str_starts_with($query->sql, 'insert into "job_notifications"')) DB::afterCommit(function () use ($state) { $state->committed = true; });
        });
        $sync = new class($state) extends \App\Services\MGinbon\MGinbonAssignmentSyncService {
            public bool $fail = true;
            public array $days = [];
            public function __construct(private object $state) {}
            public function complete(\App\Models\ProjectJobAssignment $assignment, ?int $actorId = null, ?string $occurredOn = null, bool $includeAncestors = true): void {
                if (! $this->state->committed) throw new \LogicException('Separate sync ran before default commit');
                $this->days[] = $occurredOn;
                if ($this->fail) throw new \RuntimeException('Synthetic separate sync unavailable');
            }
        };
        app()->instance(\App\Services\MGinbon\MGinbonAssignmentSyncService::class, $sync);
        $result = app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10, null, 101);
        self::assertSame(2, $result['pending_syncs']); self::assertTrue($state->committed);
        self::assertSame(['2001-01-02', '2001-01-02'], $sync->days);
        self::assertSame(1, DB::table('job_notifications')->count());
        $sync->fail = false;
        self::assertSame(0, \Illuminate\Support\Facades\Artisan::call('work-jobs:sync-completions'));
        self::assertSame(0, DB::table('work_job_completion_syncs')->whereNull('synced_at')->count());
        app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10, null, 101);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertSame(['2001-01-02', '2001-01-02', '2001-01-02', '2001-01-02'], $sync->days);
    }

    public function test_regular_worker_direct_request_keeps_existing_completion_notification(): void
    {
        $this->regularReceivedCompletionFixture();
        $actor = $this->user(['user_role' => 'user']);
        app(\App\Http\Controllers\User\MyProjectJobController::class)->completeAssignment($this->request([], $actor), \App\Models\ProjectJobAssignment::findOrFail(20));
        self::assertSame(1, DB::table('job_notifications')->where('type', 'completed')->where('assignment_id', 20)->where('recipient_id', 2)->count());
        self::assertFalse((bool) DB::table('project_job_assignments')->find(10)->completed);
    }

    public function test_existing_non_trial_company_and_department_worker_can_complete_without_trial_access(): void
    {
        $this->regularReceivedCompletionFixture();
        DB::table('companies')->where('id', 1)->update(['company_type' => 'general']);
        DB::table('users')->where('id', 1)->update(['department_id' => 2]);
        $actor = $this->user(['user_role' => 'user', 'department_id' => 2]);
        $result = app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($actor, 10);
        self::assertSame(0, $result['pending_syncs']);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertFalse(app(\App\Services\UnifiedMyJobBox::class)->available($actor));
    }

    public function test_legacy_received_completion_refuses_outer_transaction_before_any_effect(): void
    {
        $this->regularReceivedCompletionFixture();
        DB::beginTransaction();
        try {
            try { app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10); self::fail('Outer transaction accepted'); }
            catch (\LogicException $e) { self::assertStringContainsString('own its transaction', $e->getMessage()); }
            self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
            self::assertSame(0, DB::table('job_notifications')->count());
        } finally { DB::rollBack(); }
    }

    public function test_regular_worker_calendar_pending_sync_uses_one_flash_and_no_duplicate_toast(): void
    {
        $event = $this->regularReceivedCompletionFixture();
        $sync = new class extends \App\Services\MGinbon\MGinbonAssignmentSyncService {
            public function complete(\App\Models\ProjectJobAssignment $assignment, ?int $actorId = null, ?string $occurredOn = null, bool $includeAncestors = true): void {
                throw new \RuntimeException('Synthetic pending sync');
            }
        };
        app()->instance(\App\Services\MGinbon\MGinbonAssignmentSyncService::class, $sync);
        $request = $this->request([], $this->user(['user_role' => 'user']));
        $response = app(\App\Http\Controllers\EventController::class)->complete($request, $event);
        self::assertStringContainsString('再試行待ち', $response->getSession()->get('success'));
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\AssignmentStatusToast::class);
        self::assertSame(1, DB::table('job_notifications')->count());
        self::assertSame(0, DB::table('messages')->count());
    }

    public function test_unrelated_legacy_continuation_from_direct_request_is_not_rerouted(): void
    {
        $this->regularReceivedCompletionFixture();
        DB::table('project_job_assignments')->where('id', 10)->update(['supersedes_assignment_id' => null, 'source_assignment_id' => 20]);
        self::assertNull(app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10));
        self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
    }

    private function detailPayload(array $data = []): array
    {
        return $data + ['details_version' => 0, 'detail_note' => '作業説明', 'file_info' => [
            'total_pages' => 999, 'groups' => ['fake' => ['count' => 999]],
            'files' => [['name' => '合成.pdf', 'ext' => 'pdf', 'type' => 'pdf', 'size' => 1024, 'pages' => 5, 'doc_size' => 'A4']],
        ]];
    }

    public function test_details_keep_same_work_and_box_time_quantity_and_recalculate_file_totals(): void
    {
        $id = $this->save($this->request($this->payload()))->getData(true)['id'];
        $before = (array) DB::table('personal_work_entries')->find($id);
        $box = app(\App\Services\UnifiedMyJobBox::class)->rows($this->user());
        $time = app(\App\Services\WorkJobReader::class)->detail($this->user(), $id)['time'];
        $result = app(\App\Services\WorkJobDetails::class)->save($this->request($this->detailPayload()), $id, null);
        self::assertSame($id, $result['id']); self::assertSame(1, $result['details_version']);
        $after = (array) DB::table('personal_work_entries')->find($id);
        foreach ($before as $key => $value) if (! in_array($key, ['detail_note', 'file_info', 'details_version', 'updated_at'])) self::assertSame($value, $after[$key], $key);
        $info = json_decode($after['file_info'], true);
        self::assertSame(5, $info['total_pages']); self::assertSame(1, $info['total_files']);
        self::assertSame(1024, $info['total_size_bytes']); self::assertArrayNotHasKey('fake', $info['groups']);
        self::assertSame(1, DB::table('personal_work_entries')->count()); self::assertSame(1, DB::table('events')->count()); self::assertSame(0, DB::table('project_job_assignments')->count());
        self::assertSame($box, app(\App\Services\UnifiedMyJobBox::class)->rows($this->user()));
        self::assertSame($time, app(\App\Services\WorkJobReader::class)->detail($this->user(), $id)['time']);
        $read = app(\App\Services\WorkJobReader::class)->detail($this->user(), $id)['work'];
        self::assertSame('合成.pdf', $read['file_info']['files'][0]['name']);
        $generic = \App\Models\PersonalWorkEntry::findOrFail($id)->toArray();
        foreach (['detail_note', 'file_info', 'details_version'] as $field) self::assertArrayNotHasKey($field, $generic);
    }

    public function test_detail_retry_and_conflict_preserve_latest_and_quick_edit_does_not_erase_details(): void
    {
        $id = $this->save($this->request($this->payload()))->getData(true)['id'];
        $details = app(\App\Services\WorkJobDetails::class);
        $body = $this->detailPayload();
        $details->save($this->request($body), $id, null);
        self::assertSame(1, $details->save($this->request($body), $id, null)['details_version']);
        try { $details->save($this->request($this->detailPayload(['detail_note' => '古い画面の変更'])), $id, null); self::fail('Lost update allowed'); }
        catch (HttpException $e) { self::assertSame(409, $e->getStatusCode()); }
        app(\App\Http\Controllers\User\WorkJobDetailController::class)->update($this->request($this->payload(['title' => '通常編集', 'detail_note' => '改ざん', 'file_info' => null, 'details_version' => 99])), $id, app(\App\Services\WorkJobReader::class), app(DepartmentWorkController::class));
        $row = \App\Models\PersonalWorkEntry::findOrFail($id);
        self::assertSame('通常編集', $row->title); self::assertSame('作業説明', $row->detail_note); self::assertSame(1, $row->details_version); self::assertNotNull($row->file_info);
        $child = $this->continueWork($id, ['work_date' => '2099-12-02'])->getData(true)['id'];
        self::assertNull(\App\Models\PersonalWorkEntry::findOrFail($child)->file_info);
        self::assertNull(\App\Models\PersonalWorkEntry::findOrFail($child)->detail_note);
        $details->save($this->request(['details_version' => 1, 'detail_note' => null, 'file_info' => null]), $id, null);
        self::assertSame(2, \App\Models\PersonalWorkEntry::findOrFail($id)->details_version);
        self::assertSame(2, DB::table('personal_work_entries')->count());
    }

    public function test_detail_save_rejects_other_users_context_role_and_malformed_information_without_writes(): void
    {
        $id = $this->save($this->request($this->payload()))->getData(true)['id'];
        $details = app(\App\Services\WorkJobDetails::class);
        foreach ([[$this->user(['id' => 2]), null], [$this->user(), 2], [$this->user(['user_role' => 'user']), null]] as [$user, $context]) {
            try { $details->save($this->request($this->detailPayload(), $user), $id, $context); self::fail('Unauthorized details'); }
            catch (\Illuminate\Auth\Access\AuthorizationException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        }
        $file = $this->detailPayload()['file_info']['files'][0];
        foreach ([['files' => [$file + ['url' => '/secret']]], ['files' => [array_replace($file, ['pages' => -1])]], ['files' => ['named' => $file]], ['files' => array_fill(0, 201, $file)], ['files' => [array_replace($file, ['type' => '__proto__'])]]] as $invalid) {
            try { $details->save($this->request($this->detailPayload(['file_info' => $invalid])), $id, null); self::fail('Malformed details accepted'); }
            catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
        }
        self::assertNull(\App\Models\PersonalWorkEntry::findOrFail($id)->file_info);
        self::assertSame(0, \App\Models\PersonalWorkEntry::findOrFail($id)->details_version);
    }

    public function test_details_on_completed_work_do_not_recomplete_or_change_core_state(): void
    {
        $id = $this->save($this->request($this->payload()))->getData(true)['id'];
        app(\App\Services\WorkJobMutation::class)->completeEntry($this->user(), $id, null);
        $at = DB::table('personal_work_entries')->find($id)->completed_at;
        app(\App\Http\Controllers\User\WorkJobDetailController::class)->saveDetails($this->request($this->detailPayload() + ['actual_quantity' => 999, 'completed_at' => null, 'source_entry_id' => 999]), $id, app(\App\Services\WorkJobDetails::class));
        $row = DB::table('personal_work_entries')->find($id);
        self::assertSame($at, $row->completed_at); self::assertSame(2, (int) $row->actual_quantity); self::assertNull($row->source_entry_id);
        self::assertSame(0, DB::table('job_notifications')->count()); self::assertSame(0, DB::table('work_job_completion_syncs')->count());
    }

    public function test_legacy_user_edit_saves_reselected_file_information_and_preserves_it_when_omitted(): void
    {
        Schema::table('project_job_assignments', function (Blueprint $t) {
            foreach (['detail', 'difficulty_id', 'desired_time', 'start_time', 'estimated_hours', 'work_item_type_id', 'size_id', 'stage_id', 'amounts', 'file_info'] as $column) $t->string($column)->nullable();
        });
        Schema::table('events', function (Blueprint $t) { $t->text('description')->nullable(); $t->text('body')->nullable(); $t->integer('interruption_minutes')->default(0); $t->dateTime('start')->nullable(); $t->dateTime('end')->nullable(); });
        DB::table('project_jobs')->insert(['id' => 10, 'company_id' => 1]);
        DB::table('project_job_assignments')->insert(['id' => 10, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 1, 'title' => '旧式', 'file_info' => json_encode(['files' => [['name' => '以前.pdf']]])]);
        DB::table('events')->insert(['id' => 100, 'user_id' => 1, 'project_job_assignment_id' => 10, 'title' => '旧式', 'starts_at' => '2026-11-05 09:00:00', 'ends_at' => '2026-11-05 10:00:00', 'start' => '2026-11-05 09:00:00', 'end' => '2026-11-05 10:00:00']);
        $controller = app(\App\Http\Controllers\User\ProjectJobAssignmentController::class);
        $project = \App\Models\ProjectJob::findOrFail(10);
        $body = ['title' => '旧式', 'desired_start_date' => '2026-11-05', 'start_time' => '09:00', 'desired_time' => '10:00'];
        $info = $this->detailPayload()['file_info'];
        $controller->update($this->request($body + ['file_info' => $info]), $project, \App\Models\ProjectJobAssignment::findOrFail(10));
        self::assertSame($info, \App\Models\ProjectJobAssignment::findOrFail(10)->file_info);
        $controller->update($this->request($body), $project, \App\Models\ProjectJobAssignment::findOrFail(10));
        self::assertSame($info, \App\Models\ProjectJobAssignment::findOrFail(10)->file_info);
        foreach ([[$this->user(['id' => 2]), $project], [$this->user(), (new \App\Models\ProjectJob)->forceFill(['id' => 99, 'company_id' => 1])]] as [$actor, $routeProject]) {
            try { $controller->update($this->request($body + ['file_info' => null], $actor), $routeProject, \App\Models\ProjectJobAssignment::findOrFail(10)); self::fail('Foreign legacy file information changed'); }
            catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        }
        self::assertSame($info, \App\Models\ProjectJobAssignment::findOrFail(10)->file_info);
        $controller->update($this->request($body + ['file_info' => null]), $project, \App\Models\ProjectJobAssignment::findOrFail(10));
        self::assertNull(\App\Models\ProjectJobAssignment::findOrFail(10)->file_info);
        self::assertSame(1, DB::table('project_job_assignments')->count());
        self::assertSame(2, DB::table('events')->count());
        self::assertSame('2026-11-05 09:00:00', DB::table('events')->find(100)->starts_at);
        self::assertSame('2026-11-05 10:00:00', DB::table('events')->find(100)->ends_at);
    }

    private function legacyContinuationFixture(bool $private = false): int
    {
        Schema::table('project_job_assignments', function (Blueprint $t) {
            foreach (['detail','difficulty_id','estimated_hours','desired_time','start_time','work_item_type_id','size_id','stage_id','amounts','file_info','read_at','scheduled_at'] as $field) $t->string($field)->nullable();
            foreach (['assigned','accepted','scheduled'] as $field) $t->boolean($field)->default(false);
        });
        Schema::table('events', function (Blueprint $t) { $t->text('body')->nullable(); $t->dateTime('start')->nullable(); $t->dateTime('end')->nullable(); $t->date('date')->nullable(); $t->integer('interruption_minutes')->default(0); });
        DB::table('statuses')->insert(['id' => 2, 'key' => 'in_progress', 'slug' => 'in_progress']);
        DB::table('clients')->insert(['id' => 10, 'name' => 'その他', 'company_id' => null]);
        DB::table('project_jobs')->insert(['id' => 10, 'title' => 'その他', 'company_id' => 1, 'client_id' => 10, 'show_on_department_schedule' => true]);
        return $this->save($this->request($this->payload(['work_date' => '2099-12-01', 'actual_quantity' => 90, 'actual_unit' => '冊', 'entry_visibility' => $private ? 'private' : 'department'])))->getData(true)['id'];
    }

    private function legacyContinuation(int $source, array $slot = [], ?User $user = null, ?int $context = null)
    {
        $payload = ['myjob_modal' => 1, 'assignments' => [$slot + ['title' => '旧形式の続き', 'project_job_id' => 10,
            'desired_start_date' => '2099-12-02', 'start_time' => '00:00', 'desired_time' => '01:00',
            'amounts' => null, 'source_personal_work_entry_id' => $source, 'legacy_request_token' => 'c49be9ca-a214-4f5d-a47d-f5bbab801fd8']]];
        return app(\App\Http\Controllers\User\ProjectJobAssignmentController::class)->store($this->request($payload, $user, $context), \App\Models\ProjectJob::findOrFail(10));
    }

    public function test_simple_to_legacy_continuation_preserves_parent_private_time_and_replays_without_duplicates(): void
    {
        $source = $this->legacyContinuationFixture(true);
        $before = (array) DB::table('personal_work_entries')->find($source);
        $seed = app(\App\Services\LegacyWorkJobContinuation::class)->prefill($this->user(), $source, null, 10);
        self::assertSame(10, $seed['assignment']['project_job_id']); self::assertNull($seed['assignment']['amounts']);
        self::assertNull($seed['assignment']['file_info']); self::assertNull($seed['assignment']['amounts_unit']);
        self::assertSame(0, DB::table('project_job_assignments')->count());
        $response = $this->legacyContinuation($source); self::assertSame(201, $response->status());
        $id = $response->getData(true)['assignment_id'];
        $assignment = DB::table('project_job_assignments')->find($id);
        self::assertSame($source, $assignment->source_personal_work_entry_id); self::assertNull($assignment->source_assignment_id); self::assertNull($assignment->supersedes_assignment_id);
        self::assertSame(1, $assignment->user_id); self::assertSame(1, $assignment->sender_id); self::assertSame(1, $assignment->company_id);
        self::assertFalse((bool) $assignment->completed); self::assertNull($assignment->amounts); self::assertNull($assignment->file_info);
        $event = DB::table('events')->where('project_job_assignment_id', $id)->first();
        self::assertSame('2099-12-02 00:00:00', $event->starts_at); self::assertSame('2099-12-02 01:00:00', $event->ends_at); self::assertSame('private', $event->visibility);
        self::assertSame($before, (array) DB::table('personal_work_entries')->find($source));
        $again = $this->legacyContinuation($source); self::assertSame(200, $again->status()); self::assertSame($id, $again->getData(true)['assignment_id']);
        self::assertSame(1, DB::table('project_job_assignments')->count()); self::assertSame(2, DB::table('events')->count()); self::assertSame(1, DB::table('work_job_continuations')->count());
        $series = app(\App\Services\WorkJobReader::class)->series($this->user(), $id, null, true, 'assignment');
        self::assertSame(['entry:'.$source, 'assignment:'.$id], array_column($series['items'], 'key')); self::assertSame(90, $series['totals']['afterLunch']);
        self::assertSame(0, DB::table('job_notifications')->count());
        try { $this->continueWork($source, ['request_token' => 'c49be9ca-a214-4f5d-a47d-f5bbab801fd8']); self::fail('Legacy result mistaken for an entry'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        self::assertSame(1, DB::table('personal_work_entries')->count());
        $seed = app(\App\Services\LegacyWorkJobContinuation::class)->prefill($this->user(), $source, null, 10)['assignment'];
        $seed['legacy_request_token'] = 'c49be9ca-a214-4f5d-a47d-f5bbab801fd8';
        $payload = ['myjob_modal' => 1, 'assignments' => [$seed + ['desired_start_date' => '2099-12-02', 'start_time' => '00:00', 'desired_time' => '01:00']]];
        try { app(\App\Http\Controllers\User\ProjectJobAssignmentController::class)->store($this->request($payload), (new \App\Models\ProjectJob)->forceFill(['id' => 99, 'company_id' => 1])); self::fail('Retry with mismatched route project accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('assignments.0.project_job_id', $e->errors()); }
    }

    public function test_simple_to_legacy_rejects_foreign_source_context_role_and_multiple_source_modes(): void
    {
        $source = $this->legacyContinuationFixture();
        foreach ([[$this->user(['id' => 2]), null], [$this->user(['user_role' => 'user']), null], [$this->user(), 2]] as [$actor, $context]) {
            try { $this->legacyContinuation($source, [], $actor, $context); self::fail('Unauthorized legacy continuation'); }
            catch (\Illuminate\Auth\Access\AuthorizationException|\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertTrue(true); }
        }
        foreach ([['source_assignment_id' => 1], ['supersedes_assignment_id' => 1], ['legacy_request_token' => 'bad'], ['desired_time' => '00:00'], ['_row_id' => 1], ['project_job_id' => 99]] as $invalid) {
            try { $this->legacyContinuation($source, $invalid); self::fail('Invalid continuation accepted'); }
            catch (ValidationException $e) { self::assertNotEmpty($e->errors()); }
        }
        self::assertSame(0, DB::table('project_job_assignments')->count()); self::assertSame(1, DB::table('events')->count()); self::assertSame(0, DB::table('work_job_continuations')->count());
    }

    public function test_legacy_continuation_changed_replay_and_missing_event_roll_back_without_partial_work(): void
    {
        $source = $this->legacyContinuationFixture();
        $id = $this->legacyContinuation($source)->getData(true)['assignment_id'];
        try { $this->legacyContinuation($source, ['title' => '変更した再送']); self::fail('Changed retry accepted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        DB::statement("CREATE TRIGGER fail_new_legacy_event BEFORE INSERT ON events BEGIN SELECT RAISE(ABORT, 'synthetic event save failure'); END");
        try { $this->legacyContinuation($source, ['legacy_request_token' => 'e49be9ca-a214-4f5d-a47d-f5bbab801fd8']); self::fail('Partial legacy work accepted'); }
        catch (ValidationException|\Illuminate\Database\QueryException $e) { self::assertTrue($e instanceof ValidationException || str_contains($e->getMessage(), 'synthetic event save failure')); }
        self::assertSame(1, DB::table('project_job_assignments')->count()); self::assertSame(2, DB::table('events')->count()); self::assertSame(1, DB::table('work_job_continuations')->count());
        self::assertSame($id, DB::table('work_job_continuations')->first()->assignment_id);
    }

    public function test_legacy_continuation_completes_mixed_ancestors_but_keeps_sibling_and_rejects_parent_deletion(): void
    {
        $source = $this->legacyContinuationFixture();
        $id = $this->legacyContinuation($source)->getData(true)['assignment_id'];
        $sibling = $this->legacyContinuation($source, ['legacy_request_token' => 'e49be9ca-a214-4f5d-a47d-f5bbab801fd8'])->getData(true)['assignment_id'];
        try { app(\App\Http\Controllers\User\WorkJobDetailController::class)->destroy($this->request(), $source, app(\App\Services\WorkJobReader::class), app(DepartmentWorkController::class)); self::fail('Parent deleted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        app(\App\Services\WorkJobMutation::class)->completeWork($this->user(), 'assignment', $id, null);
        self::assertNotNull(DB::table('personal_work_entries')->find($source)->completed_at);
        self::assertTrue((bool) DB::table('project_job_assignments')->find($id)->completed);
        self::assertFalse((bool) DB::table('project_job_assignments')->find($sibling)->completed);
    }

    public function test_legacy_continuation_options_get_preserves_selected_time_without_creating_work(): void
    {
        $source = $this->legacyContinuationFixture();
        $request = $this->request(['myjob_modal' => 1, 'continue_entry' => $source, 'date' => '2099-12-04', 'startHour' => 0, 'startMinute' => 0, 'endHour' => 1, 'endMinute' => 15], null, null, 'GET');
        $data = app(\App\Http\Controllers\EventController::class)->createJob($request)->getData(true);
        self::assertSame($source, $data['assignments'][0]['source_personal_work_entry_id']);
        self::assertSame('00:00', $data['prefillEvent']['start_time']); self::assertSame('01:15', $data['prefillEvent']['desired_time']); self::assertSame('2099-12-04', $data['date']);
        self::assertNull($data['assignments'][0]['amounts']); self::assertNull($data['assignments'][0]['file_info']);
        self::assertSame('承認済み分類', $data['simpleContinuation']['classification']);
        self::assertSame(1, DB::table('personal_work_entries')->count()); self::assertSame(0, DB::table('project_job_assignments')->count()); self::assertSame(1, DB::table('events')->count());
        try { app(\App\Http\Controllers\EventController::class)->createJob($this->request(['continue_entry' => $source, 'project_job_id' => 999], null, null, 'GET')); self::fail('Conflicting foreign project option accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('project_job_id', $e->errors()); }
        try { app(\App\Http\Controllers\EventController::class)->createJob($this->request(['continue_entry' => $source], $this->user(['user_role' => 'user']), null, 'GET')); self::fail('General user got trial metadata'); }
        catch (\Illuminate\Auth\Access\AuthorizationException $e) { self::assertTrue(true); }
    }

    public function test_legacy_continuation_uses_explicit_historical_project_and_deleted_receipt_does_not_resurrect(): void
    {
        $source = $this->legacyContinuationFixture();
        DB::table('project_jobs')->insert(['id' => 12, 'title' => '本人の旧案件', 'company_id' => null, 'show_on_department_schedule' => true]);
        DB::table('personal_work_entries')->where('id', $source)->update(['project_job_id' => 12]);
        $seed = app(\App\Services\LegacyWorkJobContinuation::class)->prefill($this->user(), $source, null, 10);
        self::assertSame(12, $seed['assignment']['project_job_id']);
        $body = ['myjob_modal' => 1, 'assignments' => [$seed['assignment'] + ['desired_start_date' => '2099-12-02', 'start_time' => '13:00', 'desired_time' => '14:00']]];
        $controller = app(\App\Http\Controllers\User\ProjectJobAssignmentController::class); $project = \App\Models\ProjectJob::findOrFail(12);
        $id = $controller->store($this->request($body), $project)->getData(true)['assignment_id'];
        self::assertSame(12, DB::table('project_job_assignments')->find($id)->project_job_id);
        // Synthetic leaf deletion exercises the receipt FK; source work remains intact.
        DB::table('project_job_assignments')->where('id', $id)->delete();
        self::assertNull(DB::table('work_job_continuations')->first()->assignment_id);
        try { $controller->store($this->request($body), $project); self::fail('Deleted result recreated'); }
        catch (HttpException $e) { self::assertSame(410, $e->getStatusCode()); }
        self::assertSame(0, DB::table('project_job_assignments')->count()); self::assertSame(1, DB::table('personal_work_entries')->count());
    }

    public function test_legacy_continuation_full_page_save_redirects_to_legacy_detail_and_keeps_confidential_project_private(): void
    {
        $source = $this->legacyContinuationFixture();
        DB::table('personal_work_entries')->where('id', $source)->update(['project_job_id' => 10]);
        DB::table('project_jobs')->where('id', 10)->update(['show_on_department_schedule' => false]);
        $body = ['assignments' => [['title' => '旧式の続き', 'project_job_id' => 10, 'desired_start_date' => '2099-12-03', 'start_time' => '13:00', 'desired_time' => '14:00', 'source_personal_work_entry_id' => $source, 'legacy_request_token' => 'c49be9ca-a214-4f5d-a47d-f5bbab801fd8']]];
        $response = app(\App\Http\Controllers\User\ProjectJobAssignmentController::class)->store($this->request($body), \App\Models\ProjectJob::findOrFail(10));
        $id = DB::table('project_job_assignments')->first()->id;
        self::assertSame(302, $response->status()); self::assertSame(route('user.myjobbox.show', ['assignment' => $id]), $response->getTargetUrl());
        self::assertSame('private', DB::table('events')->where('project_job_assignment_id', $id)->first()->visibility);
    }

    public function test_trial_changelog_versions_fit_the_real_schema_and_seeding_is_idempotent(): void
    {
        (require dirname(__DIR__, 2).'/database/migrations/2026_05_23_155003_create_changelogs_table.php')->up();
        $seeder = new \Database\Seeders\UnifiedMyJobBoxChangelogSeeder;
        $seeder->run();
        (new \Database\Seeders\BroadProjectClientsChangelogSeeder)->run();
        $first = DB::table('changelogs')->orderBy('id')->pluck('version')->all();
        self::assertNotEmpty($first);
        foreach ($first as $version) self::assertLessThanOrEqual(30, mb_strlen($version), 'changelogs.version varchar(30)');
        $seeder->run();
        (new \Database\Seeders\BroadProjectClientsChangelogSeeder)->run();
        self::assertSame($first, DB::table('changelogs')->orderBy('id')->pluck('version')->all());
        self::assertSame(1, DB::table('changelogs')->where('version', 'myjob-entry-old-20261106')->count());
    }
    public function test_simple_history_multiple_related_clients_filter_without_inventing_an_actual_client(): void
    {
        DB::table('clients')->insert([['id' => 1, 'name' => 'Alpha', 'company_id' => 1], ['id' => 2, 'name' => 'Beta', 'company_id' => 1], ['id' => 3, 'name' => 'Foreign', 'company_id' => 2]]);
        DB::table('company_clients')->insert([['company_id' => 1, 'client_id' => 1], ['company_id' => 1, 'client_id' => 2], ['company_id' => 2, 'client_id' => 3]]);
        $id = $this->save($this->request($this->payload()))->getData(true)['id'];
        DB::table('broad_project_clients')->insert([['broad_project_id' => 1, 'client_id' => 1], ['broad_project_id' => 1, 'client_id' => 2], ['broad_project_id' => 1, 'client_id' => 3]]);
        $history = fn ($client) => app(\App\Http\Controllers\User\MyProjectJobController::class)->pastData($this->request(['mode' => 'project', 'client_id' => $client], null, null, 'GET'))->getData(true);
        foreach ([1, 2] as $client) {
            $data = $history($client); self::assertCount(1, $data['records']);
            self::assertNull($data['records'][0]['client_id']);
            self::assertSame('Alpha・Beta', $data['records'][0]['client_name']);
            self::assertCount(2, $data['clients']);
        }
        self::assertCount(0, $history(3)['records']);
        self::assertNull(DB::table('personal_work_entries')->find($id)->project_job_id);
        DB::table('project_jobs')->insert(['id' => 77, 'title' => 'Actual project', 'company_id' => 1, 'client_id' => 1]);
        DB::table('personal_work_entries')->where('id', $id)->update(['project_job_id' => 77]);
        self::assertCount(0, $history(2)['records']);
        self::assertSame(1, $history(1)['records'][0]['client_id']);
        self::assertSame('Alpha', $history(1)['records'][0]['client_name']);

    }

    private function quickProofFixture(): int
    {
        $this->save($this->request($this->payload(['title' => '元作業', 'result_note' => '非公開の結果'])));
        DB::table('users')->insert([
            ['id' => 11, 'company_id' => 1, 'department_id' => 1, 'user_role' => 'proof_coordinator'],
            ['id' => 12, 'company_id' => 1, 'department_id' => 2, 'user_role' => 'proof_coordinator'],
            ['id' => 13, 'company_id' => 2, 'department_id' => 3, 'user_role' => 'proof_coordinator'],
        ]);
        return (int) DB::table('personal_work_entries')->value('id');
    }

    private function sendQuickProof(int $id, array $extra = [], ?User $user = null, ?int $context = null): array
    {
        $request = $this->request($extra + ['title' => '元作業_校正', 'deadline' => '2026-12-01T17:30:00+09:00', 'note' => '校正の注意'], $user, $context);
        $this->app['auth']->setUser($request->user());
        return app(\App\Services\QuickWorkProofRequest::class)->store($request, $id, $context);
    }

    public function test_quick_proof_handoff_without_project_and_replay_are_atomic_and_scoped(): void
    {
        $id = $this->quickProofFixture();
        $before = (array) DB::table('personal_work_entries')->find($id);
        $first = $this->sendQuickProof($id);
        self::assertFalse($first['replayed']);
        self::assertSame(['id' => $first['id'], 'replayed' => true], $this->sendQuickProof($id));
        self::assertSame(1, DB::table('proof_requests')->count());
        $proof = DB::table('proof_requests')->first();
        self::assertSame($id, $proof->source_personal_work_entry_id);
        self::assertNull($proof->project_job_assignment_id);
        self::assertNull($proof->project_job_id);
        self::assertSame(1, $proof->requester_id);
        self::assertSame('pending', $proof->status);
        self::assertSame('2026-12-01 08:30:00', $proof->deadline);
        self::assertSame([11], DB::table('job_notifications')->pluck('recipient_id')->all());
        self::assertSame($before, (array) DB::table('personal_work_entries')->find($id));
        self::assertSame(0, DB::table('project_job_assignments')->count());
        self::assertSame(1, DB::table('events')->count());
        self::assertTrue(app(\App\Services\QuickWorkProofRequest::class)->state($this->user(), $id)['requested']);
    }

    public function test_quick_proof_keeps_real_project_and_source_labels_without_exposing_source_body(): void
    {
        $id = $this->quickProofFixture();
        DB::table('project_jobs')->insert(['id' => 77, 'company_id' => 1, 'title' => '実案件']);
        DB::table('personal_work_entries')->where('id', $id)->update(['project_job_id' => 77]);
        $result = $this->sendQuickProof($id);
        self::assertSame(77, DB::table('proof_requests')->value('project_job_id'));
        $this->app['auth']->setUser($this->user(['id' => 11, 'user_role' => 'proof_coordinator']));
        $data = \App\Models\ProofRequest::findOrFail($result['id'])->toArray();
        self::assertSame('元作業', $data['quick_work_source']['title']);
        self::assertSame('承認済み分類', $data['quick_work_source']['classification']);
        self::assertNull($data['quick_work_source']['entryId']);
        self::assertArrayNotHasKey('source_personal_work_entry', $data);
        self::assertStringNotContainsString('非公開の結果', json_encode($data));
        $this->app['auth']->setUser($this->user());
        self::assertSame($id, \App\Models\ProofRequest::findOrFail($result['id'])->quick_work_source['entryId']);
    }

    public function test_quick_proof_rejects_role_owner_context_and_injected_source_references(): void
    {
        $id = $this->quickProofFixture();
        foreach ([['user_role' => 'user'], ['user_role' => 'coordinator'], ['department_id' => 2], ['company_id' => 2, 'department_id' => 3]] as $attributes) {
            try { $this->sendQuickProof($id, [], $this->user($attributes)); self::fail('Forbidden proof handoff'); }
            catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        }
        try { $this->sendQuickProof($id, [], null, 2); self::fail('Context bypass'); }
        catch (HttpException $e) { self::assertSame(403, $e->getStatusCode()); }
        try { $this->sendQuickProof($id, [], $this->user(['id' => 2])); self::fail('Other owner'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { self::assertNotNull($e); }
        foreach (['project_job_id', 'project_job_assignment_id', 'proof_cell_id', 'proof_row_id', 'proof_col_key', 'workflow_cell_id', 'workflow_sheet_id', 'workflow_stage_key', 'requester_id', 'source_personal_work_entry_id'] as $key) {
            try { $this->sendQuickProof($id, [$key => 77]); self::fail('Injected '.$key); }
            catch (ValidationException $e) { self::assertArrayHasKey($key, $e->errors()); }
        }
        self::assertSame(0, DB::table('proof_requests')->count());
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_quick_proof_rejects_completed_source_and_missing_local_coordinator(): void
    {
        $id = $this->quickProofFixture();
        DB::table('personal_work_entries')->where('id', $id)->update(['completed_at' => now()]);
        try { $this->sendQuickProof($id); self::fail('Completed source accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('title', $e->errors()); }
        DB::table('personal_work_entries')->where('id', $id)->update(['completed_at' => null]);
        DB::table('users')->where('id', 11)->update(['user_role' => 'user']);
        try { $this->sendQuickProof($id); self::fail('No coordinator accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('title', $e->errors()); }
        self::assertSame(0, DB::table('proof_requests')->count());
    }

    public function test_quick_proof_notification_failure_rolls_back_request_and_all_notifications(): void
    {
        $id = $this->quickProofFixture();
        DB::table('users')->insert(['id' => 14, 'company_id' => 1, 'department_id' => 1, 'user_role' => 'proof_coordinator']);
        DB::statement("CREATE TRIGGER quick_proof_notification_failure BEFORE INSERT ON job_notifications WHEN NEW.recipient_id = 14 BEGIN SELECT RAISE(ABORT, 'synthetic notification failure'); END");
        try { $this->sendQuickProof($id); self::fail('Failure swallowed'); }
        catch (\Illuminate\Database\QueryException $e) { self::assertStringContainsString('synthetic notification failure', $e->getMessage()); }
        self::assertSame(0, DB::table('proof_requests')->count());
        self::assertSame(0, DB::table('job_notifications')->count());
        DB::statement('DROP TRIGGER quick_proof_notification_failure');
        self::assertFalse($this->sendQuickProof($id)['replayed']);
    }

    public function test_quick_proof_visibility_does_not_expand_legacy_visibility_or_other_company_sources(): void
    {
        $id = $this->quickProofFixture();
        $result = $this->sendQuickProof($id);
        DB::table('proof_requests')->insert(['id' => 77, 'project_job_id' => 77, 'requester_id' => 1, 'title' => '旧依頼', 'status' => 'pending']);
        foreach ([['company_id' => 2, 'department_id' => 3], ['department_id' => 2]] as $attributes) {
            $this->app['auth']->setUser($this->user($attributes));
            self::assertNull(\App\Models\ProofRequest::find($result['id']));
            self::assertNotNull(\App\Models\ProofRequest::find(77));
        }
        $this->app['auth']->setUser($this->user(['id' => 11, 'user_role' => 'proof_coordinator']));
        self::assertNotNull(\App\Models\ProofRequest::find($result['id']));
    }

    public function test_quick_proof_pending_cancel_allows_resend_and_prevents_source_deletion(): void
    {
        $id = $this->quickProofFixture();
        $result = $this->sendQuickProof($id);
        $controller = app(\App\Http\Controllers\User\WorkJobDetailController::class);
        try { $controller->destroy($this->request(), $id, app(\App\Services\WorkJobReader::class), app(DepartmentWorkController::class)); self::fail('Source deleted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        $request = $this->request();
        app(\App\Http\Controllers\ProofCoordinator\ProofRequestController::class)->destroy($request, \App\Models\ProofRequest::findOrFail($result['id']));
        self::assertFalse(app(\App\Services\QuickWorkProofRequest::class)->state($this->user(), $id)['requested']);
        self::assertFalse($this->sendQuickProof($id)['replayed']);
        self::assertSame(1, DB::table('proof_requests')->count());
        self::assertSame(2, DB::table('job_notifications')->count()); // New notification belongs to an explicit re-request.
    }

    public function test_quick_proof_route_and_legacy_injected_source_are_kept_separate(): void
    {
        $id = $this->quickProofFixture();
        $request = $this->request(['title' => '試験_校正', 'deadline' => '2026-12-01T17:30:00+09:00']);
        $request->server->set('REQUEST_URI', '/myjobbox/work-entries/'.$id.'/proof-request');
        $routeRequest = Request::create('/myjobbox/work-entries/'.$id.'/proof-request', 'POST');
        self::assertSame('user.myjobbox.entries.proof_request', app('router')->getRoutes()->match($routeRequest)->getName());
        $this->app['auth']->setUser($this->user());
        $response = app(\App\Http\Controllers\User\WorkJobDetailController::class)->requestProof($request, $id, app(\App\Services\QuickWorkProofRequest::class));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame(1, DB::table('proof_requests')->count());
        try {
            app(\App\Http\Controllers\ProofCoordinator\ProofRequestController::class)->store($this->request(['source_personal_work_entry_id' => $id, 'title' => '不正な旧送信']));
            self::fail('Quick reference accepted on legacy endpoint');
        } catch (ValidationException $e) { self::assertArrayHasKey('source_personal_work_entry_id', $e->errors()); }
        self::assertSame(1, DB::table('proof_requests')->count());
    }

    public function test_quick_proof_changed_context_hides_even_a_preloaded_source(): void
    {
        $id = $this->quickProofFixture();
        $result = $this->sendQuickProof($id);
        $proof = \App\Models\ProofRequest::findOrFail($result['id']);
        self::assertNotNull($proof->quick_work_source);
        $this->request([], $this->user(), 2);
        self::assertNull(\App\Models\ProofRequest::find($result['id']));
        self::assertNull($proof->quick_work_source);
    }

    public function test_quick_proof_completion_keeps_source_work_uncompleted_and_preserves_reference(): void
    {
        $id = $this->quickProofFixture();
        $result = $this->sendQuickProof($id);
        $coordinator = $this->user(['id' => 11, 'user_role' => 'proof_coordinator']);
        $this->app['auth']->setUser($coordinator);
        $request = $this->request([], $coordinator);
        app(\App\Http\Controllers\ProofCoordinator\ProofRequestController::class)->complete($request, \App\Models\ProofRequest::findOrFail($result['id']));
        self::assertSame('completed', DB::table('proof_requests')->value('status'));
        self::assertNull(DB::table('personal_work_entries')->value('completed_at'));
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->where('recipient_id', 1)->count());
        $this->app['auth']->setUser($this->user());
        try { app(DepartmentWorkController::class)->destroy($this->request(), \App\Models\PersonalWorkEntry::findOrFail($id)); self::fail('Historical source deleted'); }
        catch (HttpException $e) { self::assertSame(422, $e->getStatusCode()); }
        self::assertTrue($this->sendQuickProof($id)['replayed']);
        self::assertSame(1, DB::table('proof_requests')->count());
    }

    public function test_actual_shared_props_restore_trial_hub_without_losing_sales_calendar_metadata(): void
    {
        // Exercise the real middleware: frontend fixtures alone cannot detect a missing shared prop.
        Schema::table('companies', function (Blueprint $t) { $t->string('name')->nullable(); $t->string('code')->nullable(); $t->boolean('active')->default(true); $t->integer('sort_order')->default(0); });
        Schema::table('departments', function (Blueprint $t) { $t->string('name')->nullable(); $t->string('module')->nullable(); });
        Schema::table('job_notifications', fn (Blueprint $t) => $t->timestamp('read_at')->nullable());
        Schema::table('project_job_assignments', fn (Blueprint $t) => $t->boolean('is_registered')->default(false));
        Schema::create('job_assignment_messages', function (Blueprint $t) { $t->id(); $t->integer('project_job_assignment_id'); $t->integer('sender_id')->nullable(); $t->boolean('completed')->default(false); $t->timestamps(); });
        Schema::create('teams', function (Blueprint $t) { $t->id(); $t->string('team_type'); $t->integer('company_id'); $t->integer('leader_id'); });
        Schema::create('announcements', function (Blueprint $t) { $t->id(); $t->string('status'); });
        Schema::create('announcement_recipients', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('announcement_id'); $t->timestamp('read_at')->nullable(); });
        Schema::create('schedule_notifications', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->timestamp('read_at')->nullable(); });
        Schema::create('department_field_configs', function (Blueprint $t) { $t->id(); $t->integer('department_id'); foreach (['slot','label','allowed_item_ids','source','source_group'] as $c) $t->string($c)->nullable(); $t->boolean('enabled')->default(true); });
        Schema::create('job_field_options', function (Blueprint $t) { $t->id(); $t->integer('company_id'); foreach (['name','group_key'] as $c) $t->string($c)->nullable(); $t->integer('sort_order')->default(0); $t->float('coefficient')->default(1); });
        Schema::create('suna_ga_nencho_campaigns', function (Blueprint $t) { $t->id(); $t->string('status'); $t->date('opens_on'); $t->date('due_on'); });
        Schema::create('suna_ga_nencho_targets', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('campaign_id'); $t->boolean('is_excluded')->default(false); });
        Schema::create('suna_ga_nencho_submissions', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('status'); });
        foreach (['suna_ga_staff_permissions','user_application_access','portfolio_permissions','admin_permissions','leader_permissions','sales_analysis_permissions','proof_team_members','diary_team_leaders'] as $name) {
            Schema::create($name, function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->boolean('enabled')->default(false); $t->string('feature_key')->nullable(); $t->boolean('script_access')->default(false); });
        }
        DB::table('project_jobs')->insert(['id' => 77, 'title' => '受信案件', 'company_id' => 1]);
        DB::table('project_job_assignments')->insert(['id' => 77, 'project_job_id' => 77, 'user_id' => 1, 'sender_id' => 2]);
        DB::table('job_assignment_messages')->insert(['id' => 77, 'project_job_assignment_id' => 77, 'sender_id' => 2, 'created_at' => now()]);
        $share = function (?User $user, ?int $context = null, bool $sales = false) {
            app('session')->forget('superadmin_context');
            if ($context !== null) app('session')->put('superadmin_context.company_id', $context);
            $request = $this->request([], $user, $context, 'GET');
            if ($user === null) $request->setUserResolver(fn () => null);
            else $user->setRelation('worktype', null);
            if ($sales) $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('GET', '/suna/sales', fn () => null))->name('suna.sales.dashboard'));
            $props = app(\App\Http\Middleware\HandleInertiaRequests::class)->share($request);
            self::assertArrayHasKey('userWorkHub', $props);
            self::assertSame($sales ? 'suna.sales' : 'clerk', $props['clerkCalendarRoutePrefix']);
            $props['userWorkHub'] = ($props['userWorkHub'])();
            return $props;
        };
        $props = $share($this->user());
        self::assertSame(['enabled' => true, 'unregisteredRequests' => 1], $props['userWorkHub']);
        self::assertTrue($props['auth']['canManageBroadProjects']);
        self::assertTrue($props['auth']['canAccessPortfolio']);
        foreach ([['user_role' => 'user'], ['user_role' => 'coordinator'], ['user_role' => 'admin'], ['user_role' => 'leader'], ['department_id' => 2], ['company_id' => 2, 'department_id' => 3]] as $data) {
            self::assertSame(['enabled' => false, 'unregisteredRequests' => 0], $share($this->user($data))['userWorkHub']);
        }
        self::assertSame(['enabled' => false, 'unregisteredRequests' => 0], $share($this->user(), 2, true)['userWorkHub']);
        self::assertSame(['enabled' => true, 'unregisteredRequests' => 1], $share($this->user(), 1)['userWorkHub']);
        self::assertSame(['enabled' => false, 'unregisteredRequests' => 0], $share(null)['userWorkHub']);
        app('session')->forget('superadmin_context');
    }

    private function proofNoticeFixture(): \App\Models\Event
    {
        $event = $this->regularReceivedCompletionFixture();
        Schema::table('project_job_assignments', function (Blueprint $t) { $t->integer('coordinator_assignment_id')->nullable(); });
        DB::table('project_job_assignments')->where('id', 20)->update(['job_type' => 'proof']);
        DB::table('proof_requests')->insert(['id' => 1, 'project_job_id' => 10, 'project_job_assignment_id' => null,
            'proofreader_id' => 1, 'requester_id' => 2, 'title' => '通知試験', 'status' => 'in_progress']);
        return $event;
    }

    public function test_regular_worker_calendar_proof_completion_notifies_requester(): void
    {
        $event = $this->proofNoticeFixture();
        app(\App\Http\Controllers\EventController::class)->complete($this->request([], $this->user(['user_role' => 'user'])), $event);
        self::assertSame('completed', DB::table('proof_requests')->find(1)->status);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->where('recipient_id', 2)->count());
    }

    public function test_proof_completion_across_calendar_myjob_jobbox_and_explicit_pages_is_idempotent(): void
    {
        $event = $this->proofNoticeFixture();
        $actor = $this->user(['user_role' => 'user']);
        $request = $this->request([], $actor);
        app(\App\Http\Controllers\User\MyProjectJobController::class)->completeAssignment($request, \App\Models\ProjectJobAssignment::findOrFail(10));
        app(\App\Http\Controllers\ProjectJobs\JobBoxController::class)->completeAssignment($request, \App\Models\ProjectJobAssignment::findOrFail(10));
        app(\App\Http\Controllers\EventController::class)->complete($request, $event);
        app(\App\Http\Controllers\User\ProofJobController::class)->complete(\App\Models\ProofRequest::findOrFail(1));
        app(\App\Http\Controllers\ProofCoordinator\ProofRequestController::class)->complete($request, \App\Models\ProofRequest::findOrFail(1));
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
        self::assertSame(0, DB::table('job_notifications')->where('type', 'completed')->count());
        self::assertSame(0, DB::table('work_job_completion_syncs')->whereNull('synced_at')->count());
        self::assertTrue((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertStringStartsWith('【完了】', DB::table('events')->find(101)->title);
    }

    public function test_proof_notification_failure_rolls_back_calendar_job_and_proof_without_toast(): void
    {
        $event = $this->proofNoticeFixture();
        DB::statement("CREATE TRIGGER fail_proof_notice BEFORE INSERT ON job_notifications WHEN NEW.type = 'proof_completed' BEGIN SELECT RAISE(ABORT, 'proof notice failure'); END");
        $response = app(\App\Http\Controllers\EventController::class)->complete($this->request([], $this->user(['user_role' => 'user'])), $event);
        self::assertNotNull($response->getSession()->get('error'));
        self::assertSame('in_progress', DB::table('proof_requests')->find(1)->status);
        self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
        self::assertSame(0, DB::table('job_notifications')->count());
        self::assertSame(0, DB::table('work_job_completion_syncs')->count());
        self::assertSame('合成の自己予定', DB::table('events')->find(101)->title);
        \Illuminate\Support\Facades\Event::assertNotDispatched(\App\Events\AssignmentStatusToast::class);
    }

    public function test_proof_calendar_ambiguous_request_rolls_back_instead_of_selecting_latest(): void
    {
        $event = $this->proofNoticeFixture();
        DB::table('proof_requests')->insert(['project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '同じ担当の別校正', 'status' => 'assigned']);
        app(\App\Http\Controllers\EventController::class)->complete($this->request([], $this->user(['user_role' => 'user'])), $event);
        self::assertSame(0, DB::table('project_job_assignments')->where('completed', true)->count());
        self::assertSame(0, DB::table('proof_requests')->where('status', 'completed')->count());
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_proof_coordinator_reference_and_direct_proof_use_same_notification_transaction(): void
    {
        $event = $this->proofNoticeFixture();
        DB::table('proof_requests')->where('id', 1)->update(['proof_coordinator_id' => 2]);
        DB::table('project_job_assignments')->where('id', 10)->update(['job_type' => 'proof', 'supersedes_assignment_id' => null, 'coordinator_assignment_id' => 20]);
        $actor = $this->user(['user_role' => 'user']);
        app(\App\Http\Controllers\EventController::class)->complete($this->request([], $actor), $event);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
        self::assertTrue((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertSame(0, DB::table('work_job_completion_syncs')->whereNull('synced_at')->count());
    }

    public function test_direct_nonself_proof_assignment_can_complete_from_requested_job_box(): void
    {
        $this->proofNoticeFixture();
        $actor = $this->user(['user_role' => 'user']);
        app(\App\Http\Controllers\ProjectJobs\JobBoxController::class)->completeAssignment($this->request([], $actor), \App\Models\ProjectJobAssignment::findOrFail(20));
        self::assertSame('completed', DB::table('proof_requests')->find(1)->status);
        self::assertSame(1, DB::table('job_notifications')->where('recipient_id', 2)->where('type', 'proof_completed')->count());
        self::assertSame(0, DB::table('work_job_completion_syncs')->whereNull('synced_at')->count());
        self::assertSame(1, DB::table('job_notifications')->count());
    }

    public function test_completed_old_proof_import_does_not_complete_or_notify_later_request(): void
    {
        $event = $this->proofNoticeFixture();
        DB::table('project_job_assignments')->whereIn('id', [10, 20])->update(['completed' => true]);
        DB::table('proof_requests')->where('id', 1)->update(['status' => 'completed']);
        DB::table('proof_requests')->insert(['project_job_id' => 10, 'proofreader_id' => 1, 'requester_id' => 2, 'title' => '後の校正', 'status' => 'assigned']);
        app(\App\Http\Controllers\EventController::class)->complete($this->request([], $this->user(['user_role' => 'user'])), $event);
        self::assertSame('assigned', DB::table('proof_requests')->find(2)->status);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_explicit_proof_pages_only_complete_proof_assignment_and_notify_once(): void
    {
        $this->proofNoticeFixture();
        DB::table('proof_requests')->where('id', 1)->update(['proof_coordinator_id' => 2]);
        DB::table('project_job_assignments')->insert(['id' => 30, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 2, 'job_type' => 'work']);
        $actor = $this->user(['user_role' => 'user']);
        app(\App\Http\Controllers\User\ProofJobController::class)->complete(\App\Models\ProofRequest::findOrFail(1));
        app(\App\Http\Controllers\ProofCoordinator\ProofRequestController::class)->complete($this->request([], $actor), \App\Models\ProofRequest::findOrFail(1));
        self::assertTrue((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertFalse((bool) DB::table('project_job_assignments')->find(30)->completed);
        self::assertSame(1, DB::table('job_notifications')->where('type', 'proof_completed')->count());
    }

    public function test_explicit_proof_notification_failure_preserves_source_and_assignment(): void
    {
        $this->proofNoticeFixture();
        DB::table('proof_requests')->where('id', 1)->update(['proof_coordinator_id' => 2, 'project_job_assignment_id' => 30]);
        DB::table('project_job_assignments')->insert(['id' => 30, 'project_job_id' => 10, 'user_id' => 2, 'sender_id' => 2]);
        DB::statement("CREATE TRIGGER fail_explicit_proof_notice BEFORE INSERT ON job_notifications BEGIN SELECT RAISE(ABORT, 'proof notice failure'); END");
        try { app(\App\Services\ProofRequestCompletion::class)->completeRequest($this->user(), 1); self::fail('Notification failure swallowed'); }
        catch (\Illuminate\Database\QueryException $e) { self::assertStringContainsString('proof notice failure', $e->getMessage()); }
        self::assertSame('in_progress', DB::table('proof_requests')->find(1)->status);
        self::assertNull(DB::table('project_job_assignments')->find(30)->proof_completed_at);
        self::assertFalse((bool) DB::table('project_job_assignments')->find(20)->completed);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_proof_matching_does_not_complete_another_coordinators_request(): void
    {
        $this->proofNoticeFixture();
        DB::table('proof_requests')->where('id', 1)->update(['proof_coordinator_id' => 3]);
        app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10);
        self::assertSame('in_progress', DB::table('proof_requests')->find(1)->status);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_explicit_proof_rejects_foreign_company_source_and_ambiguous_assignments(): void
    {
        $this->proofNoticeFixture();
        DB::table('proof_requests')->where('id', 1)->update(['proof_coordinator_id' => 2]);
        DB::table('project_job_assignments')->insert(['id' => 30, 'project_job_id' => 10, 'user_id' => 1, 'sender_id' => 2, 'job_type' => 'proof']);
        try { app(\App\Services\ProofRequestCompletion::class)->completeRequest($this->user(), 1); self::fail('Ambiguous assignments accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertSame(0, DB::table('job_notifications')->count());
        DB::table('project_job_assignments')->where('id', 30)->update(['job_type' => 'work']);
        DB::table('users')->where('id', 2)->update(['company_id' => 2]);
        try { app(\App\Services\ProofRequestCompletion::class)->completeRequest($this->user(), 1); self::fail('Foreign company accepted'); }
        catch (ValidationException $e) { self::assertArrayHasKey('source', $e->errors()); }
        self::assertSame('in_progress', DB::table('proof_requests')->find(1)->status);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

    public function test_proof_import_cannot_consume_request_created_after_proof_assignment(): void
    {
        $this->proofNoticeFixture();
        DB::table('project_job_assignments')->where('id', 20)->update(['created_at' => '2026-11-01 10:00:00']);
        DB::table('proof_requests')->where('id', 1)->update(['created_at' => '2026-11-02 10:00:00']);
        app(\App\Services\LegacyReceivedJobCompletion::class)->tryComplete($this->user(['user_role' => 'user']), 10);
        self::assertSame('in_progress', DB::table('proof_requests')->find(1)->status);
        self::assertSame(0, DB::table('job_notifications')->count());
    }

}

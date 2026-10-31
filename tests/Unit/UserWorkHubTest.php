<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\PersonalWorkSchedule;
use App\Services\UserWorkHub;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class UserWorkHubTest extends TestCase
{
    private Capsule $db;
    private mixed $previousFacade;
    private mixed $previousResolver;
    private UserWorkHub $hub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $container = new Container;
        $this->db = new Capsule($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $container->instance('validator', new \Illuminate\Validation\Factory($translator, $container));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('companies', function (Blueprint $t) { $t->id(); $t->string('company_type'); });
        $schema->create('departments', function (Blueprint $t) { $t->id(); $t->integer('company_id'); $t->string('code'); });
        $schema->create('clients', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('is_dormant')->default(false); });
        $schema->create('company_clients', function (Blueprint $t) { $t->integer('company_id'); $t->integer('client_id'); });
        $schema->create('broad_projects', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->integer('department_id'); $t->string('name');
            $t->integer('client_id')->nullable(); $t->integer('fiscal_year')->nullable(); $t->boolean('is_active'); $t->timestamps();
        });
        $schema->create('project_jobs', fn (Blueprint $t) => $t->id());
        $schema->create('project_job_assignments', function (Blueprint $t) {
            $t->id(); $t->integer('project_job_id')->default(1); $t->integer('user_id'); $t->integer('sender_id')->nullable();
            $t->boolean('is_registered')->nullable(); $t->boolean('completed')->nullable(); $t->integer('supersedes_assignment_id')->nullable();
        });
        $schema->create('job_assignment_messages', function (Blueprint $t) {
            $t->id(); $t->integer('project_job_assignment_id'); $t->integer('sender_id')->nullable();
            $t->boolean('completed')->nullable(); $t->timestamp('created_at')->nullable();
        });
        $this->db->getConnection()->table('companies')->insert([['id' => 1, 'company_type' => 'sunbrain'], ['id' => 2, 'company_type' => 'general']]);
        $this->db->getConnection()->table('departments')->insert([
            ['id' => 1, 'company_id' => 1, 'code' => 'INFO'], ['id' => 2, 'company_id' => 1, 'code' => 'OTHER'],
            ['id' => 3, 'company_id' => 2, 'code' => 'INFO'],
        ]);
        $this->db->getConnection()->table('project_jobs')->insert(['id' => 1]);
        $this->hub = new UserWorkHub(new PersonalWorkSchedule);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        $this->db->getConnection()->disconnect();
        if ($this->previousResolver) \Illuminate\Database\Eloquent\Model::setConnectionResolver($this->previousResolver);
        else \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();
        parent::tearDown();
    }

    private function user(array $attributes = []): User
    {
        return (new User)->forceFill($attributes + ['id' => 1, 'user_role' => 'superadmin', 'company_id' => 1, 'department_id' => 1]);
    }

    public function test_only_own_info_superadmin_can_use_preview(): void
    {
        self::assertTrue($this->hub->available($this->user()));
        self::assertFalse($this->hub->available(null));
        foreach ([['user_role' => 'user'], ['user_role' => 'coordinator'], ['user_role' => 'leader'],
            ['department_id' => 2], ['company_id' => 2], ['company_id' => 2, 'department_id' => 3]] as $attributes) {
            $user = $this->user($attributes);
            self::assertFalse($this->hub->available($user));
            self::assertSame(['enabled' => false, 'unregisteredRequests' => 0], $this->hub->navigation($user));
            try { $this->hub->createBroadProject($user, ['name' => 'Denied']); self::fail('Write was allowed'); }
            catch (AuthorizationException) { self::assertSame(0, $this->db->getConnection()->table('broad_projects')->count()); }
            try { $this->hub->broadProjects($user); self::fail('Read was allowed'); }
            catch (AuthorizationException) { self::assertTrue(true); }
        }
    }

    public function test_creation_fixes_scope_and_lists_only_own_department(): void
    {
        $project = $this->hub->createBroadProject($this->user(), ['name' => '  Example　 work  ', 'company_id' => 2, 'department_id' => 3, 'is_active' => false]);
        self::assertSame('Example work', $project->name);
        self::assertSame(1, $project->company_id);
        self::assertSame(1, $project->department_id);
        self::assertTrue($project->is_active);
        $this->db->getConnection()->table('broad_projects')->insert([
            ['company_id' => 1, 'department_id' => 2, 'name' => 'Other department', 'is_active' => true],
            ['company_id' => 2, 'department_id' => 1, 'name' => 'Other company', 'is_active' => true],
        ]);
        self::assertSame([$project->id], $this->hub->broadProjects($this->user())->pluck('id')->all());
    }

    public function test_all_new_controller_actions_reject_direct_access_by_ordinary_user(): void
    {
        $controller = new \App\Http\Controllers\User\WorkHubBroadProjectController(new \App\Services\BroadProjectTree, new PersonalWorkSchedule);
        foreach (['index', 'clients', 'store'] as $action) {
            $request = \Illuminate\Http\Request::create('/user/work/broad-projects', 'POST', ['name' => 'Denied']);
            $request->setUserResolver(fn () => $this->user(['user_role' => 'user']));
            try { $controller->{$action}($request); self::fail('Direct action was allowed'); }
            catch (AuthorizationException) { self::assertSame(0, $this->db->getConnection()->table('broad_projects')->count()); }
        }
    }

    public function test_duplicate_active_or_inactive_candidate_is_rejected_but_another_year_is_allowed(): void
    {
        $project = $this->hub->createBroadProject($this->user(), ['name' => 'Example', 'fiscal_year' => 2026]);
        foreach ([true, false] as $active) {
            $project->update(['is_active' => $active]);
            try { $this->hub->createBroadProject($this->user(), ['name' => ' Example ', 'fiscal_year' => 2026]); self::fail('Duplicate accepted'); }
            catch (ValidationException $e) { self::assertArrayHasKey('name', $e->errors()); }
        }
        $this->hub->createBroadProject($this->user(), ['name' => 'Example', 'fiscal_year' => 2027]);
        self::assertSame(2, $this->db->getConnection()->table('broad_projects')->count());
    }

    public function test_clients_are_active_and_company_scoped_and_foreign_client_cannot_be_saved(): void
    {
        $this->db->getConnection()->table('clients')->insert([
            ['id' => 1, 'name' => 'Own', 'is_dormant' => false], ['id' => 2, 'name' => 'Foreign', 'is_dormant' => false],
            ['id' => 3, 'name' => 'Dormant', 'is_dormant' => true],
        ]);
        $this->db->getConnection()->table('company_clients')->insert([['company_id' => 1, 'client_id' => 1], ['company_id' => 2, 'client_id' => 2], ['company_id' => 1, 'client_id' => 3]]);
        self::assertSame([1], $this->hub->clients($this->user(), '')->pluck('id')->all());
        foreach ([0, 2, 3, 999] as $clientId) {
            try { $this->hub->createBroadProject($this->user(), ['name' => 'Example', 'client_id' => $clientId]); self::fail('Invalid client accepted'); }
            catch (ValidationException $e) { self::assertArrayHasKey('client_id', $e->errors()); }
        }
        self::assertSame(1, $this->hub->createBroadProject($this->user(), ['name' => 'Example', 'client_id' => 1])->client_id);
    }

    public function test_count_uses_distinct_received_assignments_and_excludes_registered_completed_and_self_work(): void
    {
        foreach (range(1, 10) as $id) {
            $this->db->getConnection()->table('project_job_assignments')->insert([
                'id' => $id, 'user_id' => $id === 4 ? 2 : 1, 'sender_id' => $id === 3 ? 1 : ($id === 2 ? null : 2),
                'is_registered' => $id === 5, 'completed' => $id === 7,
            ]);
            $this->db->getConnection()->table('job_assignment_messages')->insert([
                'id' => $id, 'project_job_assignment_id' => $id, 'sender_id' => in_array($id, [2, 3]) ? 1 : 2,
                'completed' => false, 'created_at' => '2026-10-30 09:00:00',
            ]);
        }
        // Duplicate messages do not increase the count. Latest completion hides assignment 8.
        $this->db->getConnection()->table('job_assignment_messages')->insert([
            ['id' => 11, 'project_job_assignment_id' => 1, 'sender_id' => 2, 'completed' => false, 'created_at' => '2026-10-30 10:00:00'],
            ['id' => 12, 'project_job_assignment_id' => 8, 'sender_id' => 2, 'completed' => true, 'created_at' => '2026-10-30 10:00:00'],
        ]);
        $this->db->getConnection()->table('project_job_assignments')->insert(['id' => 11, 'user_id' => 1, 'sender_id' => 1, 'supersedes_assignment_id' => 6]);
        $this->db->getConnection()->table('project_job_assignments')->insert(['id' => 12, 'user_id' => 1, 'sender_id' => 2]);
        $this->db->getConnection()->table('job_assignment_messages')->insert(['id' => 13, 'project_job_assignment_id' => 12, 'sender_id' => 2]);
        self::assertSame(['enabled' => true, 'unregisteredRequests' => 5], $this->hub->navigation($this->user()));
        $this->db->getConnection()->table('project_job_assignments')->where('id', 1)->update(['is_registered' => true]);
        self::assertSame(4, $this->hub->navigation($this->user())['unregisteredRequests']);
    }
}

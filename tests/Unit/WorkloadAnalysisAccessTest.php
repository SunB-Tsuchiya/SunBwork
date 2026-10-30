<?php

namespace Tests\Unit;

use App\Http\Controllers\Management\WorkloadAnalysisController;
use App\Models\AdminPermission;
use App\Models\LeaderPermission;
use App\Models\Team;
use App\Models\User;
use App\Services\PersonalWorkSchedule;
use App\Services\WorkloadAnalysisAccess;
use App\Services\WorkloadTrialAnalysis;
use Illuminate\Container\Container;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Laravel\Jetstream\Jetstream;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Only synthetic SQLite memory data. No application boot or persistent DB. */
class WorkloadAnalysisAccessTest extends TestCase
{
    private Capsule $database;

    private Container $previousContainer;

    private mixed $previousFacade;

    private mixed $previousResolver;

    private WorkloadAnalysisAccess $access;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $app = new Application(dirname(__DIR__, 2));
        Container::setInstance($app);
        $this->database = new Capsule($app);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $this->database->bootEloquent();
        $app->instance('db', $this->database->getDatabaseManager());
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
        Jetstream::useTeamModel(Team::class);
        foreach ([
            'companies' => 'id integer primary key, name text, sort_order integer',
            'departments' => 'id integer primary key, company_id integer, name text, code text, sort_order integer',
            'users' => 'id integer primary key, name text, user_role text, company_id integer, department_id integer, assignment_id integer, sort_order integer default 0, employment_type text, is_ghost integer default 0',
            'user_settings' => 'id integer primary key, user_id integer, worktype_id integer',
            'user_employment_settings' => 'id integer primary key, user_id integer, ranking_included integer, workload_tracked integer',
            'teams' => 'id integer primary key, name text, company_id integer, department_id integer',
            'team_user' => 'user_id integer, team_id integer, role text, created_at text, updated_at text',
        ] as $table => $columns) {
            $this->database->getConnection()->statement("create table $table ($columns)");
        }
        $this->table('companies')->insert([['id' => 1, 'name' => 'C1', 'sort_order' => 1], ['id' => 2, 'name' => 'C2', 'sort_order' => 2]]);
        $this->table('departments')->insert([
            ['id' => 11, 'company_id' => 1, 'name' => 'D11', 'code' => 'INFO', 'sort_order' => 1],
            ['id' => 12, 'company_id' => 1, 'name' => 'D12', 'code' => 'OTHER', 'sort_order' => 2],
            ['id' => 21, 'company_id' => 2, 'name' => 'D21', 'code' => 'INFO', 'sort_order' => 1],
        ]);
        foreach ([[1, 'superadmin', 1, 11], [2, 'admin', 1, 11], [3, 'leader', 1, 11], [4, 'user', 1, 11],
            [5, 'user', 1, 11], [6, 'user', 1, 12], [7, 'user', 2, 21], [8, 'user', 1, 11], [9, 'leader', 1, 11]] as [$id,$role,$company,$department]) {
            $this->table('users')->insert(['id' => $id, 'name' => 'U'.$id, 'user_role' => $role, 'company_id' => $company, 'department_id' => $department, 'is_ghost' => $id === 8 ? 1 : 0]);
        }
        $this->table('teams')->insert([
            ['id' => 100, 'name' => 'T100', 'company_id' => 1, 'department_id' => 11],
            ['id' => 101, 'name' => 'T101', 'company_id' => 1, 'department_id' => 12],
            ['id' => 200, 'name' => 'T200', 'company_id' => 2, 'department_id' => 21],
        ]);
        foreach ([[3, 100], [3, 101], [4, 100], [6, 101], [7, 200], [8, 100]] as [$user,$team]) {
            $this->table('team_user')->insert(['user_id' => $user, 'team_id' => $team]);
        }
        $this->access = new WorkloadAnalysisAccess;
    }

    protected function tearDown(): void
    {
        $this->database->getConnection()->disconnect();
        if ($this->previousResolver) {
            Model::setConnectionResolver($this->previousResolver);
        } else {
            Model::unsetConnectionResolver();
        }
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->previousFacade);
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function table(string $name)
    {
        return $this->database->getConnection()->table($name);
    }

    private function user(int $id): User
    {
        return User::findOrFail($id)->setRelation('adminPermission', null)->setRelation('leaderPermission', null);
    }

    private function request(User $user, string $prefix, array $query = [], ?int $contextCompany = null): Request
    {
        $request = ValidatedWorkloadRequest::create('/'.$prefix.'/workload-analyzer/analysis', 'GET', $query);
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => (new Route('GET', 'test', fn () => null))->name($prefix.'.workload_analyzer.analysis'));
        $session = new Store('test', new ArraySessionHandler(120));
        if ($contextCompany !== null) {
            $session->put('superadmin_context.company_id', $contextCompany);
        }
        $request->setLaravelSession($session);

        return $request;
    }

    private function assertDenied(callable $action, int $status = 403): void
    {
        try {
            $action();
            self::fail('Request should be denied');
        } catch (HttpException $e) {
            self::assertSame($status, $e->getStatusCode());
        }
    }

    public function test_admin_keeps_company_scope_and_can_choose_own_department(): void
    {
        $actor = $this->user(2);
        [$viewer,$ids,$props] = $this->access->resolve($this->request($actor, 'admin', ['department_id' => 12]));
        self::assertSame([6], $ids);
        self::assertSame([1], array_column($props['analysisContext']['companies'], 'id'));
        self::assertSame('admin.workload_analyzer', $props['analysisContext']['routePrefix']);
        self::assertSame(12, $viewer->department_id);
        self::assertSame(11, $actor->department_id);
        self::assertSame($actor->id, $viewer->id);
        $this->assertDenied(fn () => $this->access->resolve($this->request($actor, 'admin', ['company_id' => 2])));
        $this->assertDenied(fn () => $this->access->resolve($this->request($actor, 'admin', ['department_id' => 21])));
    }

    public function test_leader_sees_only_members_of_shared_units_not_department_peers_or_ghosts(): void
    {
        [$viewer,$ids,$props] = $this->access->resolve($this->request($this->user(3), 'leader'));
        sort($ids);
        self::assertSame([3, 4], $ids);
        self::assertSame([11, 12], array_column($props['analysisContext']['departments'], 'id'));
        [, $otherIds] = $this->access->resolve($this->request($this->user(3), 'leader', ['department_id' => 12]));
        self::assertSame([6], $otherIds);
        $this->assertDenied(fn () => $this->access->resolve($this->request($this->user(3), 'leader', ['company_id' => 2])));
    }

    public function test_permission_flags_deny_management_even_when_no_page_link_is_used(): void
    {
        $admin = $this->user(2)->setRelation('adminPermission', new AdminPermission(['workload_analysis' => false]));
        $leader = $this->user(3)->setRelation('leaderPermission', new LeaderPermission(['workload_analysis' => false]));
        $this->assertDenied(fn () => $this->access->resolve($this->request($admin, 'admin')));
        $this->assertDenied(fn () => $this->access->resolve($this->request($admin, 'leader')));
        $this->assertDenied(fn () => $this->access->resolve($this->request($leader, 'leader')));
    }

    public function test_general_roles_cannot_use_any_management_prefix(): void
    {
        foreach (['user', 'coordinator', 'clerk', 'proof_coordinator'] as $role) {
            $actor = $this->user(4);
            $actor->user_role = $role;
            foreach (['superadmin', 'admin', 'leader'] as $prefix) {
                $this->assertDenied(fn () => $this->access->resolve($this->request($actor, $prefix)));
            }
        }
        $this->assertDenied(fn () => $this->access->resolve($this->request($this->user(3), 'admin')));
        $this->assertDenied(fn () => $this->access->resolve($this->request($this->user(2), 'superadmin')));
    }

    public function test_superadmin_selection_respects_existing_company_context(): void
    {
        [$viewer,$ids,$props] = $this->access->resolve($this->request($this->user(1), 'superadmin', [], 2));
        self::assertSame([7], $ids);
        self::assertSame(2, $viewer->company_id);
        self::assertSame(21, $viewer->department_id);
        self::assertSame([2], array_column($props['analysisContext']['companies'], 'id'));
        $this->assertDenied(fn () => $this->access->resolve($this->request($this->user(1), 'superadmin', ['company_id' => 1], 2)));
        [, $selected] = $this->access->resolve($this->request($this->user(1), 'superadmin', ['company_id' => 2]));
        self::assertSame([7], $selected);
    }

    public function test_guest_is_denied_before_resolving_any_scope(): void
    {
        $request = Request::create('/superadmin/workload-analyzer/analysis', 'GET');
        $request->setUserResolver(fn () => null);
        $request->setRouteResolver(fn () => (new Route('GET', 'test', fn () => null))->name('superadmin.workload_analyzer.analysis'));
        $this->assertDenied(fn () => $this->access->resolve($request));
        $this->assertDenied(fn () => $this->access->assertUserVisible($request, 4));
    }

    public function test_no_units_does_not_fall_back_to_whole_department(): void
    {
        [, $ids,$props] = $this->access->resolve($this->request($this->user(9), 'leader'));
        self::assertSame([], $ids);
        self::assertSame([], $props['analysisContext']['companies']);
    }

    public function test_invalid_scope_ids_are_rejected(): void
    {
        foreach ([['company_id' => ['1']], ['department_id' => 'abc'], ['department_id' => -1]] as $query) {
            $this->assertDenied(fn () => $this->access->resolve($this->request($this->user(2), 'admin', $query)), 422);
        }
    }

    public function test_management_access_does_not_grant_trial_input_permission(): void
    {
        foreach ([2, 3] as $id) {
            $user = $this->user($id);
            $this->access->authorize($user, $user->user_role);
            self::assertFalse((new PersonalWorkSchedule)->available($user));
        }
    }

    public function test_legacy_person_links_cannot_bypass_management_scope(): void
    {
        $leaderRequest = $this->request($this->user(3), 'leader');
        $this->access->assertUserVisible($leaderRequest, 6);
        self::assertFalse($leaderRequest->query->has('department_id'));
        $this->assertDenied(fn () => $this->access->assertUserVisible($leaderRequest, 5));
        $this->assertDenied(fn () => $this->access->assertUserVisible($leaderRequest, 7));
        $this->assertDenied(fn () => $this->access->assertUserVisible($this->request($this->user(2), 'admin'), 7));
        $this->assertDenied(fn () => $this->access->assertUserVisible($this->request($this->user(1), 'superadmin', [], 2), 4));
    }

    public function test_management_apis_reject_a_person_outside_the_leader_units(): void
    {
        $trial = $this->getMockBuilder(WorkloadTrialAnalysis::class)->disableOriginalConstructor()->onlyMethods(['analyze'])->getMock();
        $trial->expects(self::never())->method('analyze');
        $controller = new WorkloadAnalysisController(new PersonalWorkSchedule, $trial);
        foreach (['analysis', 'index'] as $method) {
            $request = $this->request($this->user(3), 'leader', ['date' => '2026-09-01', 'period' => 'month', 'user_id' => 5]);
            $this->assertDenied(fn () => $controller->$method($request), 404);
        }
    }

    public function test_management_analysis_reuses_the_same_calculator_with_only_permitted_users(): void
    {
        $trial = $this->getMockBuilder(WorkloadTrialAnalysis::class)->disableOriginalConstructor()->onlyMethods(['analyze'])->getMock();
        $trial->expects(self::once())->method('analyze')->with(
            self::callback(fn ($viewer) => $viewer->id === 3 && $viewer->company_id === 1 && $viewer->department_id === 11),
            self::callback(fn ($users) => $users->pluck('id')->all() === [4]),
            self::callback(fn ($start) => $start->toDateString() === '2026-09-01'),
            self::callback(fn ($end) => $end->toDateString() === '2026-09-30'),
            true, 'trial_time_v3'
        )->willReturn([['id' => 4, 'minutes' => 60, 'points' => 10]]);
        $factory = $this->createMock(ResponseFactory::class);
        $factory->method('json')->willReturnCallback(fn ($data) => new JsonResponse($data));
        Container::getInstance()->instance(ResponseFactory::class, $factory);
        $controller = new WorkloadAnalysisController(new PersonalWorkSchedule, $trial);
        $response = $controller->analysis($this->request($this->user(3), 'leader', ['date' => '2026-09-01', 'period' => 'month', 'user_id' => 4]));
        $body = $response->getData(true);
        self::assertSame('trial_time_v3', $body['score_version']);
        self::assertSame([['id' => 4, 'minutes' => 60, 'points' => 10]], $body['people']);
        self::assertSame(0, $body['point_rule']['creation_points']);
        self::assertSame(0, $body['point_rule']['management_points_per_active_day']);
    }
}

class ValidatedWorkloadRequest extends Request
{
    public function validate(array $rules, ...$params): array
    {
        return (new Factory(new Translator(new ArrayLoader,'en')))->make($this->all(),$rules)->validate();
    }
}

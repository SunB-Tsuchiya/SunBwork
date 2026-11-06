<?php

namespace Tests\Unit;

use App\Models\BroadProject;
use App\Models\ProjectJob;
use App\Models\User;
use App\Services\BroadProjectTree;
use App\Services\ProjectBroadProjectLink;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class ProjectBroadProjectLinkTest extends TestCase
{
    private Capsule $db;
    private mixed $previousFacade;
    private mixed $previousResolver;
    private ProjectBroadProjectLink $links;

    protected function setUp(): void
    {
        $this->previousFacade = Facade::getFacadeApplication();
        $this->previousResolver = Model::getConnectionResolver();
        $container = new Container;
        $this->db = new Capsule($container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
        $this->db->bootEloquent();
        $container->instance('db', $this->db->getDatabaseManager());
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader, 'en');
        $container->instance('validator', new \Illuminate\Validation\Factory($translator, $container));
        $container->bind('db.schema', fn () => $this->db->getConnection()->getSchemaBuilder());
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($container);
        $schema = $this->db->getConnection()->getSchemaBuilder();
        $schema->create('companies', function (Blueprint $t) { $t->id(); $t->string('company_type'); });
        $schema->create('departments', function (Blueprint $t) { $t->id(); $t->integer('company_id'); $t->string('code'); });
        $schema->create('users', function (Blueprint $t) { $t->id(); $t->integer('company_id'); $t->integer('department_id'); $t->boolean('is_ghost')->default(false); });
        $schema->create('clients', function (Blueprint $t) { $t->id(); $t->string('name'); $t->boolean('is_dormant')->default(false); });
        $schema->create('company_clients', function (Blueprint $t) { $t->integer('company_id'); $t->integer('client_id'); });
        $schema->create('broad_projects', function (Blueprint $t) {
            $t->id(); $t->integer('company_id'); $t->integer('department_id'); $t->string('name');
            $t->integer('parent_id')->nullable(); $t->integer('client_id')->nullable(); $t->integer('fiscal_year')->nullable();
            $t->string('import_key')->nullable(); $t->integer('sort_order')->nullable(); $t->boolean('is_active'); $t->timestamps();
        });
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_06_150001_create_broad_project_clients_table.php')->up();
        $schema->create('project_jobs', function (Blueprint $t) {
            $t->id(); $t->integer('company_id')->nullable(); $t->integer('user_id'); $t->integer('created_by_user_id')->nullable();
            $t->string('title'); $t->timestamps();
        });
        $schema->create('project_job_coordinators', function (Blueprint $t) { $t->integer('project_job_id'); $t->integer('user_id'); });
        foreach (['project_team_members', 'project_job_assignments'] as $table) {
            $schema->create($table, function (Blueprint $t) { $t->id(); $t->integer('project_job_id'); $t->integer('user_id'); });
        }
        $this->db->getConnection()->table('companies')->insert([['id' => 1, 'company_type' => 'sunbrain'], ['id' => 2, 'company_type' => 'general'], ['id' => 3, 'company_type' => 'sunbrain']]);
        $this->db->getConnection()->table('departments')->insert([
            ['id' => 1, 'company_id' => 1, 'code' => 'INFO'], ['id' => 2, 'company_id' => 1, 'code' => 'OTHER'],
            ['id' => 3, 'company_id' => 2, 'code' => 'INFO'], ['id' => 4, 'company_id' => 3, 'code' => 'INFO'],
        ]);
        $this->db->getConnection()->table('users')->insert([
            ['id' => 1, 'company_id' => 1, 'department_id' => 1], ['id' => 2, 'company_id' => 1, 'department_id' => 2],
            ['id' => 3, 'company_id' => 2, 'department_id' => 3], ['id' => 4, 'company_id' => 3, 'department_id' => 4],
        ]);
        // An existing row before migration must retain its title and start unlinked.
        $this->db->getConnection()->table('project_jobs')->insert(['id' => 1, 'company_id' => 1, 'user_id' => 1, 'title' => 'Existing synthetic project']);
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_02_100001_add_broad_project_to_project_jobs.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_11_02_110001_create_broad_project_favorites_table.php')->up();
        $this->links = new ProjectBroadProjectLink(new BroadProjectTree);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances(); Facade::setFacadeApplication($this->previousFacade);
        $this->db->getConnection()->disconnect();
        if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver); else Model::unsetConnectionResolver();
    }

    private function actor(array $attributes = []): User
    {
        return (new User)->forceFill($attributes + ['id' => 1, 'company_id' => 1, 'department_id' => 1, 'user_role' => 'superadmin']);
    }

    private function node(string $name, array $attributes = []): BroadProject
    {
        $node = BroadProject::create($attributes + ['name' => $name, 'company_id' => 1, 'department_id' => 1, 'is_active' => true]);
        return $node;
    }

    private function rejects(callable $action, string $class = ValidationException::class): void
    {
        try { $action(); self::fail('Expected rejection'); } catch (\Throwable $error) { self::assertInstanceOf($class, $error); }
    }

    public function test_migration_is_nullable_and_explicit_create_update_clear_preserve_identity(): void
    {
        $job = ProjectJob::findOrFail(1);
        self::assertNull($job->broad_project_id); self::assertSame('Existing synthetic project', $job->title);
        $root = $this->node('Series'); $child = $this->node('Middle', ['parent_id' => $root->id]);
        foreach ([$root, $child] as $node) {
            $job->update($this->links->attributes($this->actor(), ['broad_project_id' => $node->id], 1, $job));
            self::assertEquals($node->id, $job->fresh()->broad_project_id); self::assertSame(1, $job->id);
            self::assertSame('Existing synthetic project', $job->fresh()->title);
        }
        self::assertSame([], $this->links->attributes($this->actor(), [], 1, $job));
        self::assertEquals($child->id, $job->fresh()->broad_project_id);
        $job->update($this->links->attributes($this->actor(), ['broad_project_id' => null], 1, $job));
        self::assertNull($job->fresh()->broad_project_id);
        $new = ProjectJob::create(['company_id' => 1, 'user_id' => 1, 'title' => 'New synthetic']
            + $this->links->attributes($this->actor(), ['broad_project_id' => $root->id], 1));
        self::assertIsInt($new->fresh()->broad_project_id);
        self::assertEquals($root->id, $new->broadProject->id); self::assertSame(2, ProjectJob::count());
    }

    public function test_roles_context_and_project_management_boundaries(): void
    {
        $job = ProjectJob::findOrFail(1); $input = ['broad_project_id' => $this->node('Own')->id];
        foreach (['superadmin', 'coordinator', 'leader'] as $role) self::assertTrue($this->links->available($this->actor(['user_role' => $role]), 1, $job));
        foreach (['user', 'admin', 'clerk'] as $role) {
            self::assertFalse($this->links->editor($this->actor(['user_role' => $role]), 1, $job)['enabled']);
            $this->rejects(fn () => $this->links->attributes($this->actor(['user_role' => $role]), $input, 1, $job), AuthorizationException::class);
            self::assertSame([], $this->links->attributes($this->actor(['user_role' => $role]), [], 1, $job));
        }
        self::assertFalse($this->links->available($this->actor(), 3, $job));
        self::assertFalse($this->links->available($this->actor(['id' => 3, 'company_id' => 2, 'department_id' => 3]), 2));
        $foreign = ProjectJob::create(['company_id' => 3, 'user_id' => 1, 'title' => 'Foreign company']);
        self::assertFalse($this->links->available($this->actor(), 1, $foreign));
        $otherDept = ProjectJob::create(['company_id' => 1, 'user_id' => 2, 'created_by_user_id' => 2, 'title' => 'Other department']);
        self::assertFalse($this->links->available($this->actor(), 1, $otherDept));
        self::assertFalse($this->links->available($this->actor(['user_role' => 'coordinator']), 1, $otherDept));
        $this->db->getConnection()->table('project_job_coordinators')->insert(['project_job_id' => $otherDept->id, 'user_id' => 1]);
        self::assertTrue($this->links->available($this->actor(['user_role' => 'coordinator']), 1, $otherDept));
        $legacy = ProjectJob::create(['company_id' => null, 'user_id' => 1, 'title' => 'Legacy company null']);
        self::assertTrue($this->links->available($this->actor(), 1, $legacy));
        $this->links->attributes($this->actor(), $input, 1, $legacy);
        self::assertNull($legacy->fresh()->company_id);
    }

    public function test_paused_ancestors_foreign_nodes_and_malformed_selection_are_rejected(): void
    {
        $job = ProjectJob::findOrFail(1); $root = $this->node('Paused root', ['is_active' => false]);
        $child = $this->node('Child', ['parent_id' => $root->id]);
        $foreign = $this->node('Foreign', ['company_id' => 3, 'department_id' => 4]);
        $other = $this->node('Other department', ['department_id' => 2]);
        foreach ([$root->id, $child->id, $foreign->id, $other->id, 999, -1, 'wrong', []] as $id) {
            $this->rejects(fn () => $this->links->attributes($this->actor(), ['broad_project_id' => $id], 1, $job));
        }
        $job->update(['broad_project_id' => $child->id]);
        self::assertSame(['broad_project_id' => $child->id], $this->links->attributes($this->actor(), ['broad_project_id' => $child->id], 1, $job));
        $editor = $this->links->editor($this->actor(), 1, $job);
        self::assertSame([$child->id], array_column($editor['options'], 'id')); self::assertFalse($editor['options'][0]['active']);
        $job->update(['broad_project_id' => $foreign->id]);
        self::assertFalse($this->links->editor($this->actor(), 1, $job)['enabled']);
        self::assertNull($this->links->label($this->actor(), 1, $job));
    }

    public function test_path_updates_follow_ids_and_candidates_never_auto_link(): void
    {
        $root = $this->node('テスト・模試'); $root->import_key = '20261031:r01'; $root->sort_order = 10; $root->save();
        $child = $this->node('公開模試', ['parent_id' => $root->id]); $child->sort_order = 10; $child->save();
        $other = $this->node('別の系列'); $other->sort_order = 20; $other->save();
        $job = ProjectJob::findOrFail(1); $job->update(['title' => '2027 公開模試 サンプル']);
        $editor = $this->links->editor($this->actor(), 1, $job);
        self::assertSame([$root->id, $child->id, $other->id], array_column($editor['options'], 'id'));
        self::assertSame($child->id, $editor['suggestions'][0]['id']); self::assertNull($job->fresh()->broad_project_id);
        $job->update($this->links->attributes($this->actor(), ['broad_project_id' => $child->id], 1, $job));
        $child->update(['name' => '修正後', 'parent_id' => $other->id]);
        self::assertSame('別の系列 ／ 修正後', $this->links->label($this->actor(), 1, $job));
        self::assertSame('2027 公開模試 サンプル', $job->fresh()->title);
        self::assertSame([], $this->links->editor($this->actor(), 1, $job)['suggestions']);
        self::assertSame([], $this->links->suggestions('原稿整理と組版', [$root->fresh(), $child->fresh(), $other->fresh()]));
    }
}

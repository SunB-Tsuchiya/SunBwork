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

class BroadProjectFavoritesTest extends TestCase
{
    private Capsule $db;
    private mixed $previousFacade;
    private mixed $previousResolver;
    private ProjectBroadProjectLink $links;
    private \App\Services\BroadProjectFavorites $favorites;

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
        $this->favorites = new \App\Services\BroadProjectFavorites(new BroadProjectTree);
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

    public function test_personal_and_idempotent_stars_do_not_change_shared_order_or_other_people(): void
    {
        $root = $this->node('Root'); $child = $this->node('Middle', ['parent_id' => $root->id]);
        $before = $this->db->getConnection()->table('broad_projects')->orderBy('id')->get()->toJson();
        self::assertTrue($this->favorites->set($this->actor(), $root, true));
        self::assertTrue($this->favorites->set($this->actor(), $root, true));
        self::assertSame([$root->id], $this->favorites->ids($this->actor()));
        self::assertSame(1, \App\Models\BroadProjectFavorite::count());
        $this->db->getConnection()->table('users')->insert(['id' => 5, 'company_id' => 1, 'department_id' => 1]);
        $other = $this->actor(['id' => 5, 'user_role' => 'coordinator']);
        self::assertSame([], $this->favorites->ids($other));
        $this->favorites->set($other, $child, true);
        self::assertSame([$child->id], $this->favorites->ids($other));
        self::assertFalse($this->favorites->set($this->actor(), $root, false));
        self::assertFalse($this->favorites->set($this->actor(), $root, false));
        self::assertSame([], $this->favorites->ids($this->actor()));
        self::assertSame([$child->id], $this->favorites->ids($other));
        self::assertSame($before, $this->db->getConnection()->table('broad_projects')->orderBy('id')->get()->toJson());
        self::assertNull(ProjectJob::find(1)->broad_project_id);
    }

    public function test_roles_tenants_and_department_changes_are_scoped(): void
    {
        $root = $this->node('Own');
        foreach (['user', 'admin', 'clerk'] as $role) {
            $this->rejects(fn () => $this->favorites->set($this->actor(['user_role' => $role]), $root, true), AuthorizationException::class);
        }
        $foreign = $this->node('Foreign', ['company_id' => 3, 'department_id' => 4]);
        $otherDept = $this->node('Other department', ['department_id' => 2]);
        foreach ([$foreign, $otherDept] as $node) {
            $this->rejects(fn () => $this->favorites->set($this->actor(), $node, true), AuthorizationException::class);
            $this->rejects(fn () => $this->favorites->set($this->actor(), $node, false), AuthorizationException::class);
        }
        $this->rejects(fn () => $this->favorites->ids($this->actor(['id' => 3, 'company_id' => 2, 'department_id' => 3])), AuthorizationException::class);
        self::assertSame([], $this->favorites->candidates($this->actor(), 'Foreign', $foreign->id));
        self::assertSame([], $this->favorites->candidates($this->actor(), 'Other department', $otherDept->id));
        $this->favorites->set($this->actor(), $root, true);
        $this->db->getConnection()->table('departments')->where('id', 2)->update(['code' => 'INFO']);
        self::assertSame([], $this->favorites->ids($this->actor(['department_id' => 2])));
        self::assertSame([], $this->favorites->ids($this->actor(['id' => 4, 'company_id' => 3, 'department_id' => 4])));
        self::assertSame(1, \App\Models\BroadProjectFavorite::count());
    }

    public function test_paused_ancestor_blocks_new_stars_but_existing_can_be_shown_and_removed(): void
    {
        $root = $this->node('Root'); $child = $this->node('Middle', ['parent_id' => $root->id]);
        $this->favorites->set($this->actor(), $child, true); $root->update(['is_active' => false]);
        self::assertTrue($this->favorites->set($this->actor(), $child, true)); // existing state is idempotent
        $this->rejects(fn () => $this->favorites->set($this->actor(), $root, true));
        self::assertSame([], $this->favorites->candidates($this->actor()));
        $current = $this->favorites->candidates($this->actor(), '', $child->id);
        self::assertSame($child->id, $current[0]['id']); self::assertFalse($current[0]['is_active']);
        $rows = $this->favorites->decorate($this->actor(), (new BroadProjectTree)->rows($this->actor()));
        self::assertTrue(collect($rows)->firstWhere('id', $child->id)['is_favorite']);
        $this->favorites->set($this->actor(), $child, false);
        self::assertSame([], $this->favorites->ids($this->actor()));
        $this->rejects(fn () => $this->favorites->set($this->actor(), $child->fresh(), true));
    }

    public function test_full_tree_preserves_parent_metadata_and_excludes_paused_or_foreign_nodes(): void
    {
        $root = $this->node('Root', ['sort_order' => 10]);
        $child = $this->node('Child', ['parent_id' => $root->id]);
        $this->node('Foreign', ['company_id' => 3, 'department_id' => 4]);
        $paused = $this->node('Paused', ['is_active' => false]);
        $this->node('Hidden child', ['parent_id' => $paused->id]);
        $all = $this->favorites->candidates($this->actor(), '', 0, null);
        self::assertSame([$root->id, $child->id], array_column($all, 'id'));
        self::assertSame($root->id, (int) $all[1]['parent_id']);
        self::assertSame('Root ／ Child', $all[1]['path']);
    }

    public function test_favorites_beyond_thirty_and_search_are_available_without_duplicates(): void
    {
        $nodes = [];
        for ($i = 1; $i <= 40; $i++) {
            $node = $this->node(sprintf('Series %02d', $i)); $node->sort_order = $i * 10; $node->save(); $nodes[] = $node;
        }
        $this->favorites->set($this->actor(), $nodes[39], true);
        $this->favorites->set($this->actor(), $nodes[38], true);
        $candidates = $this->favorites->candidates($this->actor());
        $all = $this->favorites->candidates($this->actor(), '', 0, null);
        self::assertSame(array_map(fn ($n) => $n->id, $nodes), array_column($all, 'id'));
        self::assertCount(40, $all);
        self::assertCount(32, $candidates);
        self::assertSame([$nodes[38]->id, $nodes[39]->id], array_column(array_slice($candidates, 0, 2), 'id'));
        self::assertCount(32, array_unique(array_column($candidates, 'id')));
        $searched = $this->favorites->candidates($this->actor(), 'Ｓｅｒｉｅｓ ４０');
        self::assertSame([$nodes[39]->id], array_column($searched, 'id'));
        $selected = $this->favorites->candidates($this->actor(), '', $nodes[39]->id);
        self::assertSame($nodes[39]->id, $selected[0]['id']);
        self::assertCount(32, array_unique(array_column($selected, 'id')));
        $editor = $this->links->editor($this->actor(), 1, ProjectJob::find(1));
        self::assertSame(array_map(fn ($n) => $n->id, $nodes), array_column($editor['options'], 'id'));
        self::assertCount(2, array_filter($editor['options'], fn ($option) => $option['is_favorite']));
        self::assertNull(ProjectJob::find(1)->broad_project_id);
    }
    public function test_multiple_client_names_search_candidates_and_editor_without_changing_client_or_order(): void
    {
        $db = $this->db->getConnection();
        $db->table('clients')->insert([['id' => 1, 'name' => 'Alpha'], ['id' => 2, 'name' => 'Beta']]);
        $db->table('company_clients')->insert([['company_id' => 1, 'client_id' => 1], ['company_id' => 1, 'client_id' => 2]]);
        $root = $this->node('Root'); $child = $this->node('Middle', ['parent_id' => $root->id]);
        $child->clients()->attach([1, 2]);
        $rows = $this->favorites->candidates($this->actor(), 'Ｂｅｔａ', 0, null);
        self::assertSame([$root->id, $child->id], array_column($rows, 'id'));
        self::assertSame('Alpha・Beta', $rows[1]['client_name']);
        $options = $this->links->editor($this->actor(), 1)['options'];
        self::assertSame([1, 2], array_column($options[1]['clients'], 'id'));
        self::assertNull($child->fresh()->client_id);
        $this->favorites->set($this->actor(), $child, true);
        self::assertTrue(collect($this->favorites->candidates($this->actor(), 'Beta'))->firstWhere('id', $child->id)['is_favorite']);
    }

}

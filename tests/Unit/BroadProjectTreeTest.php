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

class BroadProjectTreeTest extends TestCase
{
    private Capsule $db;
    private mixed $previousFacade;
    private mixed $previousResolver;
    private \App\Services\BroadProjectTree $tree;

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
        $container->bind('db.schema', fn () => $this->db->getConnection()->getSchemaBuilder());
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_31_200001_add_tree_to_broad_projects.php')->up();
        (require dirname(__DIR__, 2).'/database/migrations/2026_10_31_210001_add_sort_order_to_broad_projects.php')->up();
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
        $this->tree = new \App\Services\BroadProjectTree;
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

    private function save(string $name, ?int $parent = null, ?\App\Models\BroadProject $existing = null): \App\Models\BroadProject
    {
        return $this->tree->save($this->user(), ['name' => $name, 'is_active' => true] + ($existing ? [] : ['group_type' => $parent ? 'middle' : 'large', 'parent_id' => $parent]), $existing);
    }

    private function rejects(callable $action, string $exception = ValidationException::class): void
    {
        try { $action(); self::fail('Expected rejection'); }
        catch (\Throwable $error) { self::assertInstanceOf($exception, $error); }
    }

    public function test_roles_and_company_department_scope(): void
    {
        foreach (['superadmin', 'coordinator', 'leader'] as $role) {
            self::assertTrue($this->tree->canManage($this->user(['user_role' => $role])));
        }
        foreach ([['user_role' => 'user'], ['user_role' => 'admin'], ['user_role' => 'clerk'], ['department_id' => 2],
            ['company_id' => 2], ['company_id' => 2, 'department_id' => 3]] as $attributes) {
            self::assertFalse($this->tree->canManage($this->user($attributes)));
            $this->rejects(fn () => $this->tree->save($this->user($attributes), ['name' => 'x', 'is_active' => true]), AuthorizationException::class);
        }
        $foreign = \App\Models\BroadProject::create(['name' => 'Foreign', 'company_id' => 2, 'department_id' => 3, 'is_active' => true]);
        $this->rejects(fn () => $this->save('Injected', $foreign->id));
        $this->rejects(fn () => $this->save('Injected', null, $foreign), AuthorizationException::class);
        self::assertCount(0, $this->tree->rows($this->user()));
        $node = $this->tree->save($this->user(), ['name' => 'Scoped', 'group_type' => 'large', 'is_active' => true, 'company_id' => 2, 'department_id' => 3]);
        self::assertEquals(1, $node->company_id); self::assertEquals(1, $node->department_id);
    }

    public function test_two_levels_and_metadata_edits_cannot_change_membership(): void
    {
        $root = $this->save('Root'); $middle = $this->save('Middle', $root->id);
        self::assertSame('Root ／ Middle', $middle->fresh()->pathLabel());
        self::assertLessThan($middle->fresh()->displayOrderKey(), $root->fresh()->displayOrderKey());
        $this->rejects(fn () => $this->save('Third', $middle->id));
        $this->rejects(fn () => $this->tree->save($this->user(), ['name' => 'Wrong', 'is_active' => true, 'group_type' => 'large', 'parent_id' => $root->id]));
        $this->rejects(fn () => $this->tree->save($this->user(), ['name' => 'Wrong', 'is_active' => true, 'group_type' => 'middle']));
        $this->rejects(fn () => $this->tree->save($this->user(), ['name' => 'Root', 'is_active' => true, 'parent_id' => $middle->id], $root));
        $this->rejects(fn () => $this->tree->save($this->user(), ['name' => 'Middle', 'is_active' => true, 'parent_id' => null], $middle));
        $this->rejects(fn () => $this->save(' Middle ', $root->id));
        $other = $this->save('Other');
        self::assertNotNull($this->save('Middle', $other->id));
        $edited = $this->tree->save($this->user(), ['name' => 'Renamed', 'is_active' => true, 'sort_order' => 999], $middle);
        self::assertEquals($root->id, $edited->parent_id); self::assertEquals(10, $edited->sort_order);
        $root->update(['is_active' => false]);
        self::assertFalse($middle->fresh()->activePath());
        $this->rejects(fn () => $this->save('Root'));
        self::assertNull($root->fresh()->parent_id);
    }

    public function test_only_middle_groups_move_to_own_large_groups_and_keep_identity(): void
    {
        $root = $this->save('Root'); $other = $this->save('Other');
        $middle = $this->save('Middle', $root->id); $existing = $this->save('Existing', $other->id);
        $this->rejects(fn () => $this->tree->move($this->user(), $root, ['parent_id' => $other->id]));
        $this->rejects(fn () => $this->tree->move($this->user(), $middle, ['parent_id' => $middle->id]));
        $this->rejects(fn () => $this->tree->move($this->user(), $middle, ['parent_id' => null]));
        $this->tree->move($this->user(), $middle, ['parent_id' => $other->id]);
        self::assertEquals($other->id, $middle->fresh()->parent_id);
        self::assertSame('Middle', $middle->fresh()->name); self::assertEquals(20, $middle->fresh()->sort_order);
        self::assertEquals($middle->id, $middle->fresh()->id);
        self::assertEquals(10, $existing->fresh()->sort_order);
        $duplicate = $this->save('Middle', $root->id);
        $this->rejects(fn () => $this->tree->move($this->user(), $duplicate, ['parent_id' => $other->id]));
        $foreign = \App\Models\BroadProject::create(['name' => 'Foreign', 'company_id' => 2, 'department_id' => 3, 'is_active' => true]);
        $this->rejects(fn () => $this->tree->move($this->user(), $middle, ['parent_id' => $foreign->id]));
        $this->rejects(fn () => $this->tree->move($this->user(), $foreign, ['parent_id' => $root->id]), AuthorizationException::class);
        $this->rejects(fn () => $this->tree->move($this->user(['user_role' => 'user']), $middle, ['parent_id' => $root->id]), AuthorizationException::class);
    }

    public function test_reorder_requires_full_current_sibling_order_and_manager_role(): void
    {
        $root = $this->save('Root'); $other = $this->save('Other'); $child = $this->save('Child', $root->id);
        $input = ['parent_id' => null, 'current_ids' => [$root->id, $other->id], 'ordered_ids' => [$other->id, $root->id]];
        foreach (['user', 'admin', 'clerk'] as $role) {
            $this->rejects(fn () => $this->tree->reorder($this->user(['user_role' => $role]), $input), AuthorizationException::class);
        }
        $this->rejects(fn () => $this->tree->reorder($this->user(), array_replace($input, ['ordered_ids' => [$root->id]])));
        $this->rejects(fn () => $this->tree->reorder($this->user(), array_replace($input, ['ordered_ids' => [$root->id, $child->id]])));
        $this->rejects(fn () => $this->tree->reorder($this->user(), array_replace($input, ['ordered_ids' => [$root->id, $root->id]])));
        $this->rejects(fn () => $this->tree->reorder($this->user(), array_replace($input, ['ordered_ids' => ['first' => $other->id, 'second' => $root->id]])));
        $this->tree->reorder($this->user(['user_role' => 'coordinator']), $input);
        self::assertSame([$other->id, $root->id], \App\Models\BroadProject::whereNull('parent_id')->inDisplayOrder()->pluck('id')->all());
        self::assertEquals(10, $child->fresh()->sort_order);
        $this->rejects(fn () => $this->tree->reorder($this->user(), $input)); // stale original order
        $paused = $this->save('Paused', $root->id); $paused->update(['is_active' => false]);
        $children = ['parent_id' => $root->id, 'current_ids' => [$child->id, $paused->id], 'ordered_ids' => [$paused->id, $child->id]];
        $this->tree->reorder($this->user(['user_role' => 'leader']), $children);
        self::assertSame([$paused->id, $child->id], \App\Models\BroadProject::where('parent_id', $root->id)->inDisplayOrder()->pluck('id')->all());
        $this->save('New', $root->id);
        $this->rejects(fn () => $this->tree->reorder($this->user(), $children));
    }

    public function test_client_scope_and_existing_dormant_client(): void
    {
        $db = $this->db->getConnection();
        $db->table('clients')->insert([['id' => 1, 'name' => 'Own', 'is_dormant' => false], ['id' => 2, 'name' => 'Foreign', 'is_dormant' => false]]);
        $db->table('company_clients')->insert([['company_id' => 1, 'client_id' => 1], ['company_id' => 2, 'client_id' => 2]]);
        $input = ['name' => 'Root', 'group_type' => 'large', 'is_active' => true, 'client_id' => 2];
        $this->rejects(fn () => $this->tree->save($this->user(), $input));
        $input['client_id'] = 1;
        $node = $this->tree->save($this->user(), $input);
        $db->table('clients')->where('id', 1)->update(['is_dormant' => true]);
        $editing = $input; unset($editing['group_type']);
        self::assertEquals(1, $this->tree->save($this->user(), $editing, $node)->client_id);
        $input['name'] = 'New';
        $this->rejects(fn () => $this->tree->save($this->user(), $input));
    }

    public function test_approved_import_dry_run_repeat_and_web_edits_preserve_old_references(): void
    {
        $old = \App\Models\BroadProject::create(['name' => '中学入試問題集', 'fiscal_year' => 2027, 'company_id' => 1, 'department_id' => 1, 'is_active' => true]);
        $import = new \App\Services\ApprovedBroadProjectImport(dirname(__DIR__, 2).'/database/data/broad-project-classifications-20261031.json');
        $result = $import->run(1, 1, true, 'default');
        self::assertSame(62, $result['created']); self::assertSame(62, $result['catalog_nodes']);
        self::assertSame(1, \App\Models\BroadProject::count());
        self::assertSame(62, $import->run(1, 1, false, 'default')['created']);
        self::assertSame(63, \App\Models\BroadProject::count());
        $child = \App\Models\BroadProject::whereNotNull('parent_id')->firstOrFail();
        $child->update(['name' => 'Web corrected', 'parent_id' => null, 'is_active' => false]);
        $result = $import->run(1, 1, false, 'default');
        self::assertSame(0, $result['created']); self::assertSame(62, $result['reused']);
        self::assertSame('Web corrected', $child->fresh()->name);
        self::assertNull($child->fresh()->parent_id); self::assertFalse($child->fresh()->is_active);
        self::assertSame(2027, $old->fresh()->fiscal_year); self::assertNull($old->fresh()->import_key);
        self::assertSame('中学入試問題集', $old->fresh()->name);
        $this->rejects(fn () => $import->run(2, 1, false, 'default'), \RuntimeException::class);
        $this->rejects(fn () => $import->run(2, 3, false, 'default'), \RuntimeException::class);
        self::assertSame(63, \App\Models\BroadProject::count());
    }

    public function test_import_adopts_only_unambiguous_exact_common_node(): void
    {
        $existing = $this->save('テスト・模試');
        $import = new \App\Services\ApprovedBroadProjectImport(dirname(__DIR__, 2).'/database/data/broad-project-classifications-20261031.json');
        self::assertSame(1, $import->run(1, 1, false, 'default')['adopted']);
        self::assertSame('20261031:r01', $existing->fresh()->import_key);
        self::assertSame(62, \App\Models\BroadProject::count());
    }

    public function test_initial_order_matches_approved_excel_and_preserves_manual_order_on_repeat(): void
    {
        $old = \App\Models\BroadProject::create(['name' => '中学入試問題集', 'fiscal_year' => 2027, 'company_id' => 1, 'department_id' => 1, 'is_active' => true]);
        $path = dirname(__DIR__, 2).'/database/data/broad-project-classifications-20261031.json';
        (new \App\Services\ApprovedBroadProjectImport($path))->run(1, 1, false, 'default');
        $order = new \App\Services\ApprovedBroadProjectOrder($path);
        self::assertSame(63, $order->run(1, 1, true, 'default')['initialized_nodes']);
        self::assertSame(63, \App\Models\BroadProject::whereNull('sort_order')->count());
        self::assertSame(63, $order->run(1, 1, false, 'default')['initialized_nodes']);
        $catalog = json_decode(file_get_contents($path), true);
        $expected = array_column(array_filter($catalog, fn ($n) => $n['parent_key'] === null), 'key');
        $roots = \App\Models\BroadProject::whereNull('parent_id')->inDisplayOrder()->get();
        self::assertSame($expected, $roots->whereNotNull('import_key')->pluck('import_key')->all());
        self::assertEquals($old->id, $roots->last()->id); self::assertSame(2027, $old->fresh()->fiscal_year);
        foreach (\App\Models\BroadProject::whereNull('parent_id')->get() as $root) {
            if (! $root->import_key) continue;
            $expectedChildren = array_column(array_filter($catalog, fn ($n) => $n['parent_key'] === $root->import_key), 'key');
            self::assertSame($expectedChildren, \App\Models\BroadProject::where('parent_id', $root->id)->inDisplayOrder()->pluck('import_key')->all());
        }
        $current = $roots->pluck('id')->all(); $reverse = array_reverse($current);
        $this->tree->reorder($this->user(), ['parent_id' => null, 'current_ids' => $current, 'ordered_ids' => $reverse]);
        self::assertSame(0, $order->run(1, 1, false, 'default')['initialized_nodes']);
        self::assertSame($reverse, \App\Models\BroadProject::whereNull('parent_id')->inDisplayOrder()->pluck('id')->all());
        $this->rejects(fn () => $order->run(2, 1, false, 'default'), \RuntimeException::class);
        $this->rejects(fn () => $order->run(2, 3, false, 'default'), \RuntimeException::class);
    }

    public function test_initial_order_stops_on_existing_third_level_without_reparenting(): void
    {
        $root = $this->save('Root'); $middle = $this->save('Middle', $root->id);
        $third = \App\Models\BroadProject::create(['name' => 'Legacy third', 'parent_id' => $middle->id, 'company_id' => 1, 'department_id' => 1, 'is_active' => true]);
        $order = new \App\Services\ApprovedBroadProjectOrder(dirname(__DIR__, 2).'/database/data/broad-project-classifications-20261031.json');
        $this->rejects(fn () => $order->run(1, 1, false, 'default'), \RuntimeException::class);
        self::assertEquals($middle->id, $third->fresh()->parent_id); self::assertNull($third->fresh()->sort_order);
        self::assertFalse($third->fresh()->activePath());
    }

}

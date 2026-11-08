<?php

namespace Tests\Unit;

use App\Models\Assignment;
use App\Models\User;
use App\Services\PersonalWorkSchedule;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class PersonalWorkCategoryDefaultTest extends TestCase
{
    private Capsule $db;
    private mixed $previousResolver;

    protected function setUp(): void
    {
        $this->previousResolver = Model::getConnectionResolver();
        $this->db = new Capsule(new Container);
        $this->db->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->db->bootEloquent();
        $this->db->getConnection()->getSchemaBuilder()->create('personal_work_entries', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->integer('company_id'); $t->integer('department_id');
            $t->date('work_date'); $t->string('work_category'); $t->integer('source_event_id')->nullable(); $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $this->db->getConnection()->disconnect();
        if ($this->previousResolver) Model::setConnectionResolver($this->previousResolver);
        else Model::unsetConnectionResolver();
    }

    private function user(?string $code = 'shinko', string $role = 'superadmin'): User
    {
        return (new User)->forceFill(['id' => 1, 'company_id' => 2, 'department_id' => 1, 'user_role' => $role])
            ->setRelation('assignment', $code ? (new Assignment)->forceFill(['code' => $code]) : null);
    }

    private function row(int $id, string $category, array $extra = []): void
    {
        $this->db->getConnection()->table('personal_work_entries')->insert($extra + [
            'id' => $id, 'user_id' => 1, 'company_id' => 2, 'department_id' => 1, 'work_category' => $category,
            'work_date' => '2026-11-08', 'created_at' => '2026-11-08 09:00:00', 'updated_at' => '2026-11-08 09:00:00',
        ]);
    }

    public function test_registration_order_ignores_future_work_date_and_later_edits(): void
    {
        $this->row(1, 'shinko', ['work_date' => '2099-12-31', 'created_at' => '2026-11-01 09:00:00', 'updated_at' => '2099-12-31 23:59:00']);
        $this->row(2, 'dtp', ['work_date' => '2026-10-01']);
        $before = $this->db->getConnection()->table('personal_work_entries')->get()->toJson();
        self::assertSame('dtp', (new PersonalWorkSchedule)->defaultCategory($this->user()));
        self::assertSame($before, $this->db->getConnection()->table('personal_work_entries')->get()->toJson());
    }

    public function test_only_valid_standalone_work_in_the_owners_current_company_and_department_is_used(): void
    {
        $this->row(1, 'eigyo');
        foreach ([['user_id' => 9], ['company_id' => 9], ['department_id' => 9], ['source_event_id' => 9]] as $i => $extra) {
            $this->row($i + 2, 'dtp', $extra + ['created_at' => '2026-11-09 09:00:00']);
        }
        $this->row(6, 'unknown', ['created_at' => '2099-12-31 09:00:00']);
        self::assertSame('eigyo', (new PersonalWorkSchedule)->defaultCategory($this->user()));
    }

    public function test_same_registration_timestamp_uses_id_and_unknown_old_timestamps_do_not_win(): void
    {
        $this->row(1, 'dtp'); $this->row(2, 'kousei');
        $this->row(99, 'other', ['created_at' => null]);
        self::assertSame('kousei', (new PersonalWorkSchedule)->defaultCategory($this->user()));
    }

    public function test_first_entry_uses_the_existing_assignment_mapping_and_clerk_fallback(): void
    {
        foreach (['shinko' => 'shinko', 'eigyo' => 'eigyo', 'kousei' => 'kousei', 'operator' => 'dtp', 'seisaku' => 'dtp', 'admin' => 'other'] as $code => $expected) {
            self::assertSame($expected, (new PersonalWorkSchedule)->defaultCategory($this->user($code)));
        }
        self::assertSame('jimu', (new PersonalWorkSchedule)->defaultCategory($this->user(null, 'clerk')));
        self::assertSame('other', (new PersonalWorkSchedule)->defaultCategory($this->user(null)));
    }
}

<?php

namespace Tests\Unit;

use App\Models\ClerkEvent;
use App\Models\ClerkScheduleOccurrence;
use App\Models\ClerkScheduleRule;
use App\Services\ClerkScheduleDates;
use App\Services\ClerkScheduleGenerator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

// 通常DBとsales DBを使わず、テストごとに専用のメモリDBだけを操作する。
class ClerkScheduleGeneratorTest extends TestCase
{
    private $app;

    private ClerkScheduleGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        config(['database.default' => 'clerk_schedule_test', 'database.connections.clerk_schedule_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('clerk_schedule_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('companies', fn (Blueprint $table) => $table->id());
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id');
        });
        DB::table('companies')->insert([['id' => 1], ['id' => 2]]);
        DB::table('users')->insert([['id' => 1, 'company_id' => 1], ['id' => 2, 'company_id' => 2]]);
        (require database_path('migrations/2026_08_20_100001_create_clerk_events_table.php'))->up();
        (require database_path('migrations/2026_08_20_100004_add_color_key_and_completed_at_to_clerk_events_table.php'))->up();
        (require database_path('migrations/2026_09_15_110000_create_clerk_schedule_rules_tables.php'))->up();
        Carbon::setTestNow('2026-09-15 12:00:00');
        $this->generator = new ClerkScheduleGenerator(new ClerkScheduleDates);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('clerk_schedule_test');
        \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
        $this->app->flush();
        parent::tearDown();
    }

    private function rule(array $overrides = []): ClerkScheduleRule
    {
        return ClerkScheduleRule::create($overrides + [
            'company_id' => 1, 'created_by' => 1, 'title' => '交通費申請', 'description' => '領収書を提出',
            'color_key' => 'purple', 'recurrence' => 'monthly_day', 'day_of_month' => 25,
            'starts_on' => '2026-09-01', 'ends_on' => null, 'is_active' => true,
        ]);
    }

    public function test_date_calculation_handles_month_end_nth_last_and_missing_dates(): void
    {
        $dates = new ClerkScheduleDates;
        $this->assertSame(['2028-02-29', '2028-03-31', '2028-04-30'], $dates->between($this->rule(['recurrence' => 'month_end']), '2028-02-01', '2028-04-30'));
        $this->assertSame(['2026-09-08', '2026-10-13'], $dates->between($this->rule(['recurrence' => 'monthly_weekday', 'ordinal' => 2, 'day_of_week' => 2]), '2026-09-01', '2026-10-31'));
        $this->assertSame(['2026-09-29', '2026-10-27'], $dates->between($this->rule(['recurrence' => 'monthly_weekday', 'ordinal' => 0, 'day_of_week' => 2]), '2026-09-01', '2026-10-31'));
        $this->assertSame(['2026-10-31'], $dates->between($this->rule(['day_of_month' => 31]), '2026-09-01', '2026-10-31'));
        $this->assertSame([], $dates->between($this->rule(['recurrence' => 'monthly_weekday', 'ordinal' => 5, 'day_of_week' => 1]), '2026-09-01', '2026-09-30'));
    }

    public function test_generation_is_idempotent_and_keeps_company_color_and_content(): void
    {
        $rule = $this->rule();
        $this->generator->generate($rule->id, '2026-09-01', '2026-11-30');
        $this->generator->generate($rule->id, '2026-09-01', '2026-11-30');
        $this->assertSame(3, ClerkEvent::count());
        $this->assertSame(3, ClerkScheduleOccurrence::count());
        $event = ClerkEvent::whereDate('starts_at', '2026-09-25')->firstOrFail();
        $this->assertSame(1, $event->company_id);
        $this->assertSame('purple', $event->color_key);
        $this->assertSame('領収書を提出', $event->description);
    }

    public function test_individual_update_delete_and_completion_are_never_restored(): void
    {
        $rule = $this->rule();
        $this->generator->generate($rule->id, '2026-09-01', '2026-11-30');
        $september = ClerkEvent::whereDate('starts_at', '2026-09-25')->firstOrFail();
        $october = ClerkEvent::whereDate('starts_at', '2026-10-25')->firstOrFail();
        $november = ClerkEvent::whereDate('starts_at', '2026-11-25')->firstOrFail();
        $this->generator->changeEvent($september, ['starts_at' => '2026-09-26', 'ends_at' => '2026-09-26', 'title' => '個別変更']);
        $this->generator->changeEvent($october, [], 'delete');
        $this->generator->changeEvent($november, [], 'complete');
        $this->generator->generate($rule->id, '2026-09-01', '2026-11-30');
        $this->assertSame('個別変更', $september->fresh()->title);
        $this->assertNull(ClerkEvent::find($october->id));
        $this->assertNotNull($november->fresh()->completed_at);
        $this->assertSame('customized', $september->scheduleOccurrence->state);
        $this->assertSame('cancelled', ClerkScheduleOccurrence::where('rule_id', $rule->id)->whereDate('nominal_date', '2026-10-25')->firstOrFail()->state);
        $this->assertSame(2, ClerkEvent::count());
    }

    public function test_rule_change_stop_resume_and_delete_only_replace_plain_future_events(): void
    {
        $rule = $this->rule();
        $this->generator->generate($rule->id, '2026-09-01', '2026-11-30');
        $this->generator->changeRule($rule, ['day_of_month' => 28, 'title' => '外注費提出']);
        $this->assertSame(0, ClerkEvent::whereDate('starts_at', '2026-10-25')->count());
        $this->assertSame(1, ClerkEvent::whereDate('starts_at', '2026-10-28')->where('title', '外注費提出')->count());
        $this->generator->changeRule($rule->fresh(), ['is_active' => false]);
        $this->assertSame(0, ClerkEvent::whereDate('starts_at', '>=', '2026-09-15')->count());
        $this->generator->changeRule($rule->fresh(), ['is_active' => true]);
        $this->assertGreaterThan(0, ClerkEvent::whereDate('starts_at', '>=', '2026-09-15')->count());
        $this->generator->changeRule($rule->fresh(), [], true);
        $this->assertNotNull(DB::table('clerk_schedule_rules')->where('id', $rule->id)->value('deleted_at'));
        $this->assertSame(0, ClerkEvent::whereDate('starts_at', '>=', '2026-09-15')->count());
    }

    public function test_custom_dates_respect_boundaries(): void
    {
        $rule = $this->rule(['recurrence' => 'custom_dates', 'custom_dates' => ['2026-09-10', '2026-10-15', '2026-11-20'], 'starts_on' => '2026-09-15', 'ends_on' => '2026-10-31']);
        $this->assertSame(['2026-10-15'], (new ClerkScheduleDates)->between($rule, '2026-01-01', '2026-12-31'));
    }
}

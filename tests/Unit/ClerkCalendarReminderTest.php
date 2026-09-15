<?php

namespace Tests\Unit;

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Http\Requests\ClerkCalendarReminderRequest;
use App\Models\ClerkCalendarReminder;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\TestCase;

// Tests\TestCase / RefreshDatabaseは使わず、各テスト専用のメモリDBのみを操作する。
class ClerkCalendarReminderTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        config(['database.default' => 'reminder_test', 'database.connections.reminder_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'session.driver' => 'array']);
        DB::purge('reminder_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('user_role')->nullable();
            $table->boolean('is_ghost')->default(false);
        });
        DB::table('companies')->insert([['id' => 1], ['id' => 2]]);
        DB::table('users')->insert(['id' => 1]);
        (require database_path('migrations/2026_09_15_120000_create_clerk_calendar_reminders_table.php'))->up();
        (require database_path('migrations/2026_09_15_120001_add_color_key_to_clerk_calendar_reminders_table.php'))->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('reminder_test');
        \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
        $this->app->flush();
        parent::tearDown();
    }

    public function test_visible_period_includes_both_boundary_dates(): void
    {
        $reminder = $this->createReminder(1, '2026-09-15', '2026-09-17');

        $this->assertSame([$reminder->id], ClerkCalendarReminder::visibleForCompany(1, '2026-09-15')->pluck('id')->all());
        $this->assertSame([$reminder->id], ClerkCalendarReminder::visibleForCompany(1, '2026-09-17')->pluck('id')->all());
        $this->assertCount(0, ClerkCalendarReminder::visibleForCompany(1, '2026-09-14'));
        $this->assertCount(0, ClerkCalendarReminder::visibleForCompany(1, '2026-09-18'));
    }

    public function test_company_scope_and_active_state_are_enforced(): void
    {
        $this->createReminder(1, '2026-09-15', '2026-09-15');
        $this->createReminder(2, '2026-09-15', '2026-09-15');
        $this->createReminder(1, '2026-09-15', '2026-09-15', false);

        $this->assertCount(1, ClerkCalendarReminder::visibleForCompany(1, '2026-09-15'));
        $this->assertCount(1, ClerkCalendarReminder::visibleForCompany(2, '2026-09-15'));
    }

    public function test_validation_rejects_reversed_range_and_long_content(): void
    {
        $request = new ClerkCalendarReminderRequest;
        $valid = Validator::make([
            'content' => '交通費精算の締め切りです。',
            'color_key' => 'red',
            'starts_on' => '2026-09-15',
            'ends_on' => '2026-09-15',
            'is_active' => true,
        ], $request->rules());
        $this->assertFalse($valid->fails());

        $invalid = Validator::make([
            'content' => str_repeat('あ', 1001),
            'color_key' => 'unknown',
            'starts_on' => '2026-09-16',
            'ends_on' => '2026-09-15',
            'is_active' => true,
        ], $request->rules());
        $this->assertTrue($invalid->fails());
        $this->assertArrayHasKey('content', $invalid->errors()->toArray());
        $this->assertArrayHasKey('color_key', $invalid->errors()->toArray());
        $this->assertArrayHasKey('ends_on', $invalid->errors()->toArray());
    }

    public function test_superadmin_uses_selected_company_for_user_calendar_reminders(): void
    {
        $this->createReminder(2, '2026-09-15', '2026-09-15');
        Auth::setUser((new User)->forceFill([
            'id' => 1,
            'company_id' => 99,
            'user_role' => 'superadmin',
            'is_ghost' => false,
        ]));
        session(['superadmin_context.company_id' => 2]);

        $resolver = new class
        {
            use ResolvesContextCompany;

            public function companyId(): ?int
            {
                return $this->contextCompanyId();
            }
        };

        $this->assertSame(2, $resolver->companyId());
        $this->assertCount(1, ClerkCalendarReminder::visibleForCompany($resolver->companyId(), '2026-09-15'));
        $this->assertContains(ResolvesContextCompany::class, class_uses_recursive(CalendarController::class));
    }

    private function createReminder(int $companyId, string $startsOn, string $endsOn, bool $active = true): ClerkCalendarReminder
    {
        return ClerkCalendarReminder::create([
            'company_id' => $companyId,
            'created_by' => 1,
            'content' => 'テストリマインダー',
            'color_key' => 'orange',
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => $active,
        ]);
    }
}

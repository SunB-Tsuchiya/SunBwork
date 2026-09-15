<?php

namespace Tests\Unit;

use App\Http\Controllers\Clerk\ClerkCalendarHolidayController;
use App\Http\Middleware\ClerkMiddleware;
use App\Models\ClerkCalendarHoliday;
use App\Models\ClerkCalendarYear;
use App\Models\User;
use App\Services\ClerkCalendarHolidays;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Tests\TestCase / RefreshDatabaseは使わず、各テスト専用のメモリDBのみを操作する。
class ClerkCalendarHolidayTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        config(['database.default' => 'clerk_test', 'database.connections.clerk_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'session.driver' => 'array']);
        DB::purge('clerk_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('companies', function (Blueprint $table) { $table->id(); });
        DB::table('companies')->insert([['id' => 1], ['id' => 2]]);
        (require database_path('migrations/2026_09_15_100000_create_clerk_calendar_holidays_tables.php'))->up();
        $this->login(1);
    }

    protected function tearDown(): void
    {
        DB::disconnect('clerk_test');
        \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
        $this->app->flush();
        parent::tearDown();
    }

    private function login(int $companyId, string $role = 'clerk'): void
    {
        Auth::setUser((new User)->forceFill(['id' => 1, 'company_id' => $companyId, 'user_role' => $role]));
    }

    public function test_initialization_is_per_company_and_does_not_restore_deleted_holidays(): void
    {
        $service = new ClerkCalendarHolidays;
        $service->initialize(1, 2026);
        $this->assertSame(18, ClerkCalendarHoliday::count());
        $holiday = ClerkCalendarHoliday::where('date', '2026-09-22')->firstOrFail();
        $this->assertSame('2026-09-22', $holiday->toArray()['date']);
        (new ClerkCalendarHolidayController)->destroy($holiday);
        $service->initialize(1, 2026);
        $service->initialize(2, 2026);
        $this->assertSame(17, ClerkCalendarHoliday::where('company_id', 1)->count());
        $this->assertSame(18, ClerkCalendarHoliday::where('company_id', 2)->count());
        $this->assertSame(2, ClerkCalendarYear::count());
    }

    public function test_published_next_year_and_unknown_year(): void
    {
        $service = new ClerkCalendarHolidays;
        $service->initialize(1, 2027);
        $service->initialize(1, 2028);
        $this->assertSame(17, ClerkCalendarHoliday::count());
        $this->assertSame('振替休日', ClerkCalendarHoliday::where('date', '2027-03-22')->firstOrFail()->name);
        $controller = new ClerkCalendarHolidayController;
        $controller->store(Request::create('/', 'POST', ['year' => 2028, 'date' => '2028-02-29', 'name' => '追加休日']), $service);
        $holiday = ClerkCalendarHoliday::where('date', '2028-02-29')->firstOrFail();
        $controller->update(Request::create('/', 'PUT', ['year' => 2028, 'date' => '2028-03-01', 'name' => '修正休日']), $holiday);
        $this->assertSame('2028-03-01', $holiday->fresh()->toArray()['date']);
    }

    public function test_validation_rejects_duplicates_out_of_year_and_invalid_dates(): void
    {
        $controller = new ClerkCalendarHolidayController;
        $service = new ClerkCalendarHolidays;
        foreach ([['date' => '2026-01-01'], ['date' => '2027-01-02'], ['date' => '2026-02-30'], ['date' => '2026-09-25', 'name' => '']] as $input) {
            try {
                $controller->store(Request::create('/', 'POST', $input + ['year' => 2026, 'name' => '休日']), $service);
                $this->fail('不正な入力を受け付けました');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_other_company_cannot_update_or_delete(): void
    {
        (new ClerkCalendarHolidays)->initialize(2, 2026);
        $holiday = ClerkCalendarHoliday::firstOrFail();
        $controller = new ClerkCalendarHolidayController;
        foreach (['update', 'destroy'] as $action) {
            try {
                if ($action === 'update') $controller->update(Request::create('/', 'PUT', ['year' => 2026, 'date' => '2026-01-02', 'name' => '変更']), $holiday);
                else $controller->destroy($holiday);
                $this->fail('他社の変更を許可しました');
            } catch (HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
        }
        $this->assertSame(18, ClerkCalendarHoliday::count());
    }

    public function test_feed_only_contains_current_company_and_superadmin_context_is_respected(): void
    {
        $controller = new ClerkCalendarHolidayController;
        $service = new ClerkCalendarHolidays;
        $service->initialize(1, 2026);
        $service->initialize(2, 2026);
        ClerkCalendarHoliday::where('company_id', 2)->where('date', '2026-01-01')->firstOrFail()->update(['name' => '会社2の休日']);
        $this->login(1, 'superadmin');
        session(['superadmin_context.company_id' => 2]);
        $data = $controller->data(Request::create('/', 'GET', ['year' => 2026]), $service)->getData(true);
        $this->assertSame('会社2の休日', collect($data)->firstWhere('date', '2026-01-01')['name']);
        session()->forget('superadmin_context');
        $data = $controller->data(Request::create('/', 'GET', ['year' => 2026]), $service)->getData(true);
        $this->assertSame('元日', collect($data)->firstWhere('date', '2026-01-01')['name']);
    }

    public function test_regular_user_is_denied_by_clerk_middleware(): void
    {
        $this->login(1, 'user');
        $this->expectException(HttpException::class);
        (new ClerkMiddleware)->handle(Request::create('/'), fn () => response('ok'));
    }

    public function test_settings_routes_use_existing_clerk_access_control(): void
    {
        foreach ([
            'settings', 'holidays.index', 'holidays.store', 'holidays.update', 'holidays.destroy', 'holidays.data',
            'schedule_rules.index', 'schedule_rules.create', 'schedule_rules.store', 'schedule_rules.edit',
            'schedule_rules.update', 'schedule_rules.toggle', 'schedule_rules.destroy', 'schedule_rules.preview',
        ] as $name) {
            $route = $this->app['router']->getRoutes()->getByName('clerk.calendar.'.$name);
            $this->assertNotNull($route);
            $this->assertContains('clerk', $route->gatherMiddleware());
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        }
    }
}

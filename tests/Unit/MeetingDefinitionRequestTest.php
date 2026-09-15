<?php

namespace Tests\Unit;

use App\Http\Controllers\Leader\MeetingDefinitionController;
use App\Http\Requests\MeetingDefinitionRequest;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\TestCase;

// Tests\TestCase / RefreshDatabaseは使わず、各テスト専用のメモリDBのみを操作する。
class MeetingDefinitionRequestTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        config(['database.default' => 'meeting_definition_test', 'database.connections.meeting_definition_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'session.driver' => 'array']);
        DB::purge('meeting_definition_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->string('user_role')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_ghost')->default(false);
        });
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Super Admin', 'company_id' => 99, 'user_role' => 'superadmin'],
            ['id' => 2, 'name' => '会社メンバー', 'company_id' => 2, 'user_role' => 'user'],
            ['id' => 3, 'name' => '他社メンバー', 'company_id' => 3, 'user_role' => 'user'],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('meeting_definition_test');
        \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
        $this->app->flush();
        parent::tearDown();
    }

    public function test_weekly_meeting_accepts_empty_custom_dates_sent_by_form(): void
    {
        $validator = Validator::make($this->validPayload(), (new MeetingDefinitionRequest)->rules());

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_custom_date_meeting_still_requires_at_least_one_date(): void
    {
        $validator = Validator::make(array_merge($this->validPayload(), ['recurrence' => 'custom_dates']), (new MeetingDefinitionRequest)->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('custom_dates', $validator->errors()->toArray());
    }

    public function test_superadmin_is_added_to_selected_company_members(): void
    {
        $user = User::withoutGlobalScopes()->findOrFail(1);
        Auth::setUser($user);
        session(['superadmin_context.company_id' => 2]);

        $controller = new class extends MeetingDefinitionController
        {
            public function availableMembers()
            {
                return $this->getAvailableMembers();
            }
        };

        $this->assertSame([2, 1], $controller->availableMembers()->pluck('id')->all());
    }

    private function validPayload(): array
    {
        return [
            'title' => '週次会議',
            'description' => '',
            'recurrence' => 'weekly',
            'day_of_week' => 1,
            'week_of_month' => null,
            'custom_dates' => [],
            'start_time' => '10:00',
            'end_time' => '11:00',
            'members' => [2],
        ];
    }
}

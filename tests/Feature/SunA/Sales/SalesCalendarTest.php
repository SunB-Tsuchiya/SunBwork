<?php

namespace Tests\Feature\SunA\Sales;

use App\Models\ClerkEvent;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use Tests\Concerns\RefreshesSalesDatabase;
use Tests\Concerns\SetsUpSunaSales;
use Tests\TestCase;

/** 営業のカレンダー（総務・経理とは別。suna.sales.* のルートを、クラークと同じコントローラーで使う） */
class SalesCalendarTest extends TestCase
{
    use RefreshesSalesDatabase;
    use SetsUpSunaSales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSunaSales();
    }

    protected function tearDown(): void
    {
        $this->tearDownSunaSales();
        parent::tearDown();
    }

    private function salesUser(): User
    {
        $user = $this->saneiUser('user');
        ReportPermission::create(['user_id' => $user->id, 'enabled' => true, 'can_import' => false]);

        return $user;
    }

    public function test_sales_user_manages_events_only_in_the_sales_calendar()
    {
        $sales = $this->salesUser();
        $id = $this->actingAs($sales)->postJson(route('suna.sales.calendar.events.store'), ['title' => '営業会議', 'starts_at' => '2026-11-10', 'all_day' => true])->assertStatus(201)->json('id');
        $this->assertSame('sales', ClerkEvent::find($id)->calendar_kind);

        $titles = collect($this->actingAs($sales)->getJson(route('suna.sales.calendar.events.index', ['year' => 2026]))->assertOk()->json())->pluck('title')->all();
        $this->assertSame(['営業会議'], $titles);

        // 総務のカレンダーには出ない
        $soumu = $this->saneiUser('clerk');
        $this->actingAs($soumu)->getJson(route('clerk.calendar.events.index', ['year' => 2026]))->assertOk()->assertJsonCount(0);
        $this->actingAs($soumu)->deleteJson(route('clerk.calendar.events.destroy', ['event' => $id]))->assertNotFound();

        // 営業ユーザーは営業の予定を編集・削除できる
        $this->actingAs($sales)->putJson(route('suna.sales.calendar.events.update', ['event' => $id]), ['title' => '更新', 'starts_at' => '2026-11-10', 'all_day' => true])->assertOk();
        $this->actingAs($sales)->deleteJson(route('suna.sales.calendar.events.destroy', ['event' => $id]))->assertSuccessful();
    }

    public function test_calendar_pages_are_for_permitted_sales_users_only()
    {
        $this->actingAs($this->salesUser())->get(route('suna.sales.calendar'))->assertOk();
        $this->actingAs($this->saneiUser('user'))->get(route('suna.sales.calendar'))->assertForbidden();
        $this->actingAs($this->saneiUser('user'))->getJson(route('suna.sales.calendar.events.index', ['year' => 2026]))->assertForbidden();
    }

    public function test_dashboard_and_shared_route_prefix()
    {
        $sales = $this->salesUser();
        $this->actingAs($sales)->get(route('suna.sales.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('SunA/Sales/Dashboard', false)->where('clerkCalendarRoutePrefix', 'suna.sales'));
        $this->actingAs($this->saneiUser('user'))->get(route('suna.sales.dashboard'))->assertForbidden();

        // 総務のカレンダーは従来の接頭辞のまま
        $this->actingAs($this->saneiUser('clerk'))->get(route('clerk.calendar'))
            ->assertOk()->assertInertia(fn ($p) => $p->where('clerkCalendarRoutePrefix', 'clerk'));
    }
}

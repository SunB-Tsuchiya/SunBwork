<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ProjectJob;
use App\Models\ProofReservation;
use App\Models\ProofRequest;
use App\Models\ProofDispatcher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProofReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_owner_can_create_datetime_reservation(): void
    {
        [$user, $job] = $this->makeProject();
        $response = $this->actingAs($user)->post(
            route('coordinator.proof_reservations.store', ['projectJob' => $job->id]),
            [
                'title' => '案件A_校正',
                'requested_at_mode' => 'datetime',
                'requested_at' => '2026-07-01T00:00:00.000Z',
                'requested_at_text' => null,
                'deadline_mode' => 'datetime',
                'deadline_at' => '2026-07-03T08:30:00.000Z',
                'deadline_text' => null,
                'note' => '予約メモ',
            ],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $reservation = ProofReservation::firstOrFail();
        $this->assertSame($job->id, $reservation->project_job_id);
        $this->assertSame($user->id, $reservation->requester_id);
        $this->assertSame('2026-07-01 00:00:00', $reservation->getRawOriginal('requested_at'));
        $this->assertSame('2026-07-03 08:30:00', $reservation->getRawOriginal('deadline_at'));
        $this->assertTrue($reservation->canRegisterToCalendar());
    }

    public function test_text_reservation_cannot_be_registered_to_calendar(): void
    {
        [$user, $job] = $this->makeProject();
        $reservation = ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '日程未定の校正',
            'requested_at_mode' => 'text',
            'requested_at_text' => '原稿到着後',
            'deadline_mode' => 'text',
            'deadline_text' => '依頼から2営業日',
        ]);
        $this->withoutMiddleware([
            \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            \App\Http\Middleware\CheckCompanyType::class,
        ]);

        $this->actingAs($user)
            ->post(route('proof_coordinator.reservations.register_calendar', ['reservation' => $reservation->id]))
            ->assertSessionHasErrors('calendar');

        $this->assertNull($reservation->fresh()->calendar_registered_at);
    }

    public function test_confirmed_reservation_can_be_registered_to_calendar(): void
    {
        [$user, $job] = $this->makeProject();
        $reservation = ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '確定済み校正',
            'requested_at_mode' => 'datetime',
            'requested_at' => '2026-07-01 09:00:00',
            'deadline_mode' => 'datetime',
            'deadline_at' => '2026-07-03 17:30:00',
        ]);
        $this->withoutMiddleware([
            \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            \App\Http\Middleware\CheckCompanyType::class,
        ]);

        $this->actingAs($user)
            ->post(route('proof_coordinator.reservations.register_calendar', ['reservation' => $reservation->id]))
            ->assertSessionHasNoErrors();

        $this->assertNotNull($reservation->fresh()->calendar_registered_at);
    }

    public function test_registered_reservation_is_a_single_calendar_period_strip(): void
    {
        [$user, $job] = $this->makeProject();
        $reservation = ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '期間表示する校正',
            'requested_at_mode' => 'datetime',
            'requested_at' => '2026-07-01 09:00:00',
            'deadline_mode' => 'datetime',
            'deadline_at' => '2026-07-03 17:30:00',
            'calendar_registered_at' => now(),
        ]);
        $this->withoutMiddleware([
            \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            \App\Http\Middleware\CheckCompanyType::class,
        ]);

        $this->actingAs($user)
            ->get(route('proof_coordinator.calendar'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ProofCoordinator/Calendar', false)
                ->where('monthEvents', function ($events) use ($reservation) {
                    $event = collect($events)->first(
                        fn (array $item) => $item['type'] === 'proof_reservation'
                            && $item['id'] === $reservation->id,
                    );

                    return $event
                        && $event['start'] === '2026-07-01'
                        && $event['end'] === '2026-07-04';
                }));
    }

    public function test_proof_admin_reservation_index_serializes_datetime_accessors(): void
    {
        [$user, $job] = $this->makeProject();
        ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '一覧表示テスト',
            'requested_at_mode' => 'datetime',
            'requested_at' => '2026-07-01 09:00:00',
            'deadline_mode' => 'datetime',
            'deadline_at' => '2026-07-03 17:30:00',
        ]);
        $this->withoutMiddleware([
            \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            \App\Http\Middleware\CheckCompanyType::class,
        ]);

        $this->actingAs($user)
            ->get(route('proof_coordinator.reservations.index'))
            ->assertOk();
    }

    public function test_duplicate_check_matches_title_or_same_jst_dates_ignoring_time(): void
    {
        [$user, $job] = $this->makeProject();
        ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '過去の校正予約',
            'requested_at_mode' => 'datetime',
            'requested_at' => '2026-07-01 09:00:00',
            'deadline_mode' => 'datetime',
            'deadline_at' => '2026-07-03 17:30:00',
        ]);

        $this->actingAs($user)
            ->postJson(route('coordinator.proof_reservations.check_duplicate', ['projectJob' => $job->id]), [
                'title' => '異なるタイトル',
                'requested_at_mode' => 'datetime',
                'requested_at' => '2026-07-01T06:00:00.000Z',
                'deadline_mode' => 'datetime',
                'deadline_at' => '2026-07-03T01:00:00.000Z',
            ])
            ->assertOk()
            ->assertJsonPath('has_duplicates', true)
            ->assertJsonPath('duplicates.0.date_match', true);

        $this->actingAs($user)
            ->post(route('coordinator.proof_reservations.store', ['projectJob' => $job->id]), [
                'title' => '過去の校正予約',
                'requested_at_mode' => 'text',
                'requested_at_text' => '未定',
                'deadline_mode' => 'text',
                'deadline_text' => '後日',
            ])
            ->assertSessionHasErrors('duplicate');

        $this->assertSame(1, ProofReservation::count());
    }

    public function test_proof_admin_can_change_reservation_status(): void
    {
        [$user, $job] = $this->makeProject();
        $reservation = ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => 'ステータス変更テスト',
            'requested_at_mode' => 'text',
            'requested_at_text' => '未定',
            'deadline_mode' => 'text',
            'deadline_text' => '後日',
        ]);
        $this->withoutMiddleware([
            \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            \App\Http\Middleware\CheckCompanyType::class,
        ]);

        foreach (['in_progress', 'completed', 'deleted', 'reserved'] as $status) {
            $this->actingAs($user)
                ->patch(route('proof_coordinator.reservations.update_status', [
                    'reservation' => $reservation->id,
                ]), ['status' => $status])
                ->assertSessionHasNoErrors();

            $this->assertSame($status, $reservation->fresh()->status);
        }
    }

    public function test_proof_admin_indexes_sort_by_created_date_in_both_directions(): void
    {
        [$user, $job] = $this->makeProject();
        $olderReservation = ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '古い予約',
            'requested_at_mode' => 'text',
            'requested_at_text' => '未定',
            'deadline_mode' => 'text',
            'deadline_text' => '未定',
        ]);
        $newerReservation = ProofReservation::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '新しい予約',
            'requested_at_mode' => 'text',
            'requested_at_text' => '未定',
            'deadline_mode' => 'text',
            'deadline_text' => '未定',
        ]);
        $olderReservation->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();
        $newerReservation->forceFill(['created_at' => '2026-02-01 00:00:00'])->saveQuietly();

        $olderInbox = ProofRequest::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '古い受信',
            'status' => 'pending',
        ]);
        $newerInbox = ProofRequest::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '新しい受信',
            'status' => 'pending',
        ]);
        $olderInbox->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();
        $newerInbox->forceFill(['created_at' => '2026-02-01 00:00:00'])->saveQuietly();

        $olderJob = ProofRequest::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '古いジョブ',
            'status' => 'assigned',
        ]);
        $newerJob = ProofRequest::create([
            'project_job_id' => $job->id,
            'requester_id' => $user->id,
            'title' => '新しいジョブ',
            'status' => 'assigned',
        ]);
        $olderJob->forceFill(['created_at' => '2026-01-01 00:00:00'])->saveQuietly();
        $newerJob->forceFill(['created_at' => '2026-02-01 00:00:00'])->saveQuietly();

        $this->withoutMiddleware([
            \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            \App\Http\Middleware\CheckCompanyType::class,
        ]);

        $this->actingAs($user)
            ->get(route('proof_coordinator.reservations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sortOrder', 'desc')
                ->where('reservations.0.id', $newerReservation->id));
        $this->actingAs($user)
            ->get(route('proof_coordinator.reservations.index', ['sort_order' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sortOrder', 'asc')
                ->where('reservations.0.id', $olderReservation->id));

        $this->actingAs($user)
            ->get(route('proof_coordinator.inbox'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sortOrder', 'desc')
                ->where('proofRequests.0.id', $newerInbox->id));
        $this->actingAs($user)
            ->get(route('proof_coordinator.inbox', ['sort_order' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sortOrder', 'asc')
                ->where('proofRequests.0.id', $olderInbox->id));

        $this->actingAs($user)
            ->get(route('proof_coordinator.jobs'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sortOrder', 'desc')
                ->where('activeRequests.0.id', $newerJob->id));
        $this->actingAs($user)
            ->get(route('proof_coordinator.jobs', ['sort_order' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sortOrder', 'asc')
                ->where('activeRequests.0.id', $olderJob->id));
    }

    public function test_proof_dispatcher_index_uses_existing_ordered_scope(): void
    {
        $user = User::factory()->create(['user_role' => 'superadmin']);
        $inactive = ProofDispatcher::create(['name' => 'A社', 'is_active' => false]);
        $active = ProofDispatcher::create(['name' => 'B社', 'is_active' => true]);
        $this->withoutMiddleware([
            \App\Http\Middleware\ProofCoordinatorMiddleware::class,
            \App\Http\Middleware\CheckCompanyType::class,
        ]);

        $this->actingAs($user)
            ->get(route('proof_coordinator.dispatchers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dispatchers.0.id', $active->id)
                ->where('dispatchers.1.id', $inactive->id));
    }

    private function makeProject(): array
    {
        $user = User::factory()->create(['user_role' => 'coordinator']);
        $client = Client::create(['name' => '予約テストクライアント']);
        $job = ProjectJob::create([
            'jobcode' => 'RESV-001',
            'title' => '予約テスト案件',
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        return [$user, $job];
    }
}

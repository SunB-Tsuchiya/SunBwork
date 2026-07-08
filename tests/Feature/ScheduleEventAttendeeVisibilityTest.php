<?php

namespace Tests\Feature;

use App\Models\MeetingRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleEventAttendeeVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_sees_own_created_meeting_with_attendee_in_range()
    {
        $organizer = User::factory()->create(['user_role' => 'coordinator']);
        $invitee   = User::factory()->create(['user_role' => 'user']);

        $starts = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');
        $ends   = now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s');

        $res = $this->actingAs($organizer)->postJson(route('schedule.events.store'), [
            'title'           => 'テスト会議',
            'starts_at'       => $starts,
            'ends_at'         => $ends,
            'is_company_event' => true,
            'visibility'      => 'company',
            'attendee_ids'    => [$invitee->id],
        ]);

        $res->assertStatus(201);
        $eventId = $res->json('id');

        fwrite(STDERR, "\n[store response] is_own=" . json_encode($res->json('is_own')) . "\n");

        // schedule_attendees の状態を直接確認
        $rows = \DB::table('schedule_attendees')->where('event_id', $eventId)->get();
        fwrite(STDERR, "[schedule_attendees] " . $rows->toJson() . "\n");

        $day = now()->addDay()->format('Y-m-d');

        // 主催者から見た range()
        $rangeAsOrganizer = $this->actingAs($organizer)->getJson(
            route('schedule.events.range') . "?start={$day}&end={$day}"
        );
        $rangeAsOrganizer->assertStatus(200);
        $ownEntry = collect($rangeAsOrganizer->json('events'))->firstWhere('id', $eventId);
        fwrite(STDERR, "[organizer range entry] " . json_encode($ownEntry) . "\n");
        $this->assertNotNull($ownEntry, '主催者の range() にイベントが存在しない');
        $this->assertTrue((bool) ($ownEntry['is_own'] ?? false), '主催者の range() で is_own が true になっていない');

        // 招待された側から見た range()
        $rangeAsInvitee = $this->actingAs($invitee)->getJson(
            route('schedule.events.range') . "?start={$day}&end={$day}"
        );
        $rangeAsInvitee->assertStatus(200);
        $inviteeEntry = collect($rangeAsInvitee->json('events'))->firstWhere('id', $eventId);
        fwrite(STDERR, "[invitee range entry] " . json_encode($inviteeEntry) . "\n");
        $this->assertNotNull($inviteeEntry, '招待者の range() にイベントが存在しない');
        $this->assertTrue((bool) ($inviteeEntry['as_attendee'] ?? false));
    }

    public function test_organizer_sees_own_meeting_with_room_reservation_in_range()
    {
        $company   = \App\Models\Company::create(['name' => 'テスト会社', 'code' => 'TST']);
        $organizer = User::factory()->create(['user_role' => 'coordinator', 'company_id' => $company->id]);
        $invitee   = User::factory()->create(['user_role' => 'user', 'company_id' => $company->id]);
        $room      = MeetingRoom::create([
            'name'       => 'テスト会議室',
            'company_id' => $company->id,
            'active'     => true,
        ]);

        $starts = now()->addDay()->setTime(13, 0)->format('Y-m-d H:i:s');
        $ends   = now()->addDay()->setTime(14, 0)->format('Y-m-d H:i:s');

        $res = $this->actingAs($organizer)->postJson(
            route('schedule.room-reservations.store', ['room' => $room->id]),
            [
                'title'          => 'テスト会議(会議室予約)',
                'starts_at'      => $starts,
                'ends_at'        => $ends,
                'self_included'  => true,
                'attendee_ids'   => [$invitee->id],
            ]
        );

        $res->assertStatus(201);
        $eventId = $res->json('event_id');
        fwrite(STDERR, "\n[room reservation response] " . $res->getContent() . "\n");

        $rows = \DB::table('schedule_attendees')->where('event_id', $eventId)->get();
        fwrite(STDERR, "[schedule_attendees] " . $rows->toJson() . "\n");

        $day = now()->addDay()->format('Y-m-d');

        $rangeAsOrganizer = $this->actingAs($organizer)->getJson(
            route('schedule.events.range') . "?start={$day}&end={$day}"
        );
        $rangeAsOrganizer->assertStatus(200);
        $ownEntry = collect($rangeAsOrganizer->json('events'))->firstWhere('id', $eventId);
        fwrite(STDERR, "[organizer range entry] " . json_encode($ownEntry) . "\n");
        $reservationEntry = collect($rangeAsOrganizer->json('reservations'))->first();
        fwrite(STDERR, "[organizer reservations] " . json_encode($reservationEntry) . "\n");

        $this->assertNotNull($ownEntry, '主催者の range() に会議室予約付きイベントが存在しない（これが報告されたバグ）');
        $this->assertTrue((bool) ($ownEntry['is_own'] ?? false));
    }
}

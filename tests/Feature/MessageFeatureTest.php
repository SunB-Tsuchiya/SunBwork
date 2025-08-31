<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Message;

class MessageFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_message_and_recipients_created()
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender)->post(route('messages.store'), [
            'subject' => 'テスト件名',
            'body' => '本文',
            'to' => [$recipient->id],
            'cc' => [],
            'bcc' => [],
        ])->assertRedirect(route('messages.index', ['folder' => 'sent']));

        $this->assertDatabaseHas('messages', ['subject' => 'テスト件名', 'from_user_id' => $sender->id]);
        $msg = Message::where('subject', 'テスト件名')->first();
        $this->assertDatabaseHas('message_recipients', ['message_id' => $msg->id, 'user_id' => $recipient->id, 'type' => 'to']);
    }

    public function test_user_search_and_compose_submission()
    {
        $sender = User::factory()->create();
        $r1 = User::factory()->create(['name' => 'Alice Example']);
        $r2 = User::factory()->create(['name' => 'Bob Example']);

        $this->actingAs($sender)->getJson(route('users.search', ['q' => 'Alice']))
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $r1->id]);

        $this->actingAs($sender)->post(route('messages.store'), [
            'subject' => 'UI Test',
            'body' => 'Body',
            'to' => [$r1->id],
            'cc' => [$r2->id],
            'bcc' => [],
        ])->assertRedirect(route('messages.index', ['folder' => 'sent']));

        $this->assertDatabaseHas('messages', ['subject' => 'UI Test']);
        $msg = Message::where('subject', 'UI Test')->first();
        $this->assertDatabaseHas('message_recipients', ['message_id' => $msg->id, 'user_id' => $r1->id, 'type' => 'to']);
        $this->assertDatabaseHas('message_recipients', ['message_id' => $msg->id, 'user_id' => $r2->id, 'type' => 'cc']);
    }

    public function test_mark_read_updates_recipient()
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        // create message and recipient
        $this->actingAs($sender)->post(route('messages.store'), [
            'subject' => 'Read Test',
            'body' => 'Body',
            'to' => [$recipient->id],
            'cc' => [],
            'bcc' => [],
        ]);

        $msg = Message::where('subject', 'Read Test')->first();
        $this->assertDatabaseHas('message_recipients', ['message_id' => $msg->id, 'user_id' => $recipient->id, 'read_at' => null]);

        // mark read via API
        $this->actingAs($recipient)->postJson(route('messages.read', $msg->id))->assertStatus(200)->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('message_recipients', ['message_id' => $msg->id, 'user_id' => $recipient->id, 'read_at' => null]);
    }
}

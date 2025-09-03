<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Message;

class MessageCreateE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_message_with_attachment()
    {
        Storage::fake('public');

        $sender = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($sender);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600)->size(500);

        // upload file (multipart)
        $res = $this->post('/api/uploads', ['file' => $file]);
        $res->assertStatus(200);
        $data = $res->json();
        $this->assertArrayHasKey('id', $data);
        $attId = $data['id'];

        // mark attachment ready in DB so message creation can attach it
        $attachment = \App\Models\Attachment::find($attId);
        $this->assertNotNull($attachment, 'attachment record should exist after upload');
        $attachment->status = 'ready';
        // ensure path exists for later retrieval (some upload handlers set path)
        if (empty($attachment->path)) {
            $attachment->path = 'attachments/' . $file->hashName();
            Storage::putFileAs('attachments', $file, $file->hashName());
        }
        $attachment->save();

        // create message with the uploaded attachment
        $payload = [
            'to' => [$recipient->id],
            'cc' => [],
            'bcc' => [],
            'subject' => 'E2E Test',
            'body' => 'Please see attached',
            'attachments' => [$attId],
        ];

        $resp = $this->post(route('messages.store'), $payload);
        $resp->assertStatus(302);

        $this->assertDatabaseHas('messages', ['subject' => 'E2E Test', 'from_user_id' => $sender->id]);
        $message = Message::where('subject', 'E2E Test')->first();
        $this->assertNotNull($message);
        $this->assertDatabaseHas('message_recipients', ['message_id' => $message->id, 'user_id' => $recipient->id, 'type' => 'to']);
        $this->assertDatabaseHas('attachments', ['id' => $attId, 'message_id' => $message->id]);
    }
}

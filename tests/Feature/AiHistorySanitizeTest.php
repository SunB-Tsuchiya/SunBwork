<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AiHistorySanitizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_url_is_stripped_from_meta()
    {
        // create user
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'title' => 't1',
            'system_prompt' => 'sys',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'here is a file',
                    'meta' => [
                        'file' => [
                            'url' => 'https://evil.example.com/malicious.png',
                            'original_name' => 'malicious.png',
                            'mime' => 'image/png',
                            'size' => 1234,
                        ],
                    ],
                ],
            ],
        ];

        $res = $this->postJson('/bot/history', $payload);
        $res->assertStatus(200);
        $convId = $res->json('id');

        // get the conversation via JSON endpoint
        $show = $this->getJson('/bot/history/' . $convId . '/json');
        $show->assertStatus(200);
        $data = $show->json();
        $this->assertArrayHasKey('messages', $data);
        $this->assertCount(1, $data['messages']);
        $this->assertNull($data['messages'][0]['meta'] ?? null, 'External URL meta should be stripped');
    }

    public function test_internal_path_is_preserved_in_meta()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $payload = [
            'title' => 't2',
            'system_prompt' => 'sys',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'internal file',
                    'meta' => [
                        'file' => [
                            'path' => 'bot/sample.txt',
                            'original_name' => 'sample.txt',
                            'mime' => 'text/plain',
                            'size' => 12,
                        ],
                    ],
                ],
            ],
        ];

        // ensure the file path exists in storage (create a dummy file)
        \Storage::disk('public')->put('bot/sample.txt', 'hello');

        $res = $this->postJson('/bot/history', $payload);
        $res->assertStatus(200);
        $convId = $res->json('id');

        $show = $this->getJson('/bot/history/' . $convId . '/json');
        $show->assertStatus(200);
        $data = $show->json();
        $this->assertArrayHasKey('messages', $data);
        $this->assertCount(1, $data['messages']);
        $meta = $data['messages'][0]['meta'] ?? null;
        $this->assertNotNull($meta, 'Internal path meta should be preserved');
        $this->assertArrayHasKey('file', $meta);
        $this->assertEquals('bot/sample.txt', $meta['file']['path']);
    }
}

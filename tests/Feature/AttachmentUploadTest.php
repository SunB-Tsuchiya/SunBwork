<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Attachment;
use App\Jobs\ProcessUploadJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class AttachmentUploadTest extends TestCase
{
    public function test_process_upload_job_moves_tmp_to_attachments_and_preserves_multibyte_name()
    {
        // Use in-memory sqlite for this test to avoid external DB requirements
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        // Create a minimal attachments table needed for this test (avoid running full migrations)
        Schema::create('attachments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->bigInteger('size')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        // Fake local and public disks so we can inspect files
        Storage::fake('local');

        // Use a temporary writable public disk root for this test to avoid permission issues
        $publicBase = storage_path('app/public_test');
        if (!is_dir($publicBase)) {
            mkdir($publicBase, 0777, true);
        }
        if (!is_dir($publicBase . '/attachments')) {
            mkdir($publicBase . '/attachments', 0777, true);
        }
        // configure the public disk to point to the test directory
        Config::set('filesystems.disks.public', [
            'driver' => 'local',
            'root' => $publicBase,
            'url' => env('APP_URL', 'http://localhost') . '/storage',
            'visibility' => 'public',
        ]);

        // prepare a multibyte original name
        $originalName = 'テスト画像 - コピー.png';
        $tmpFilename = 'tmp_uploads/' . uniqid() . '_' . $originalName;

        // Put a dummy file into the local disk to simulate UploadController behavior
        $content = 'dummy-content-' . Str::random(8);
        Storage::disk('local')->put($tmpFilename, $content);

        // create an Attachment DB row in processing state
        $att = Attachment::create([
            'user_id' => null,
            'path' => '',
            'original_name' => $originalName,
            'mime_type' => 'text/plain',
            'status' => 'processing',
            'size' => strlen($content),
        ]);

        // Run the job synchronously
        $job = new ProcessUploadJob($tmpFilename, $att->id, null, null);
        $job->handle();

        // Refresh attachment and assertions
        $att->refresh();
        $this->assertEquals('ready', $att->status, 'Attachment status should be ready after job');
        $this->assertNotEmpty($att->path, 'Attachment.path should be set');

        // Storage public should have the file at the path
        $this->assertTrue(Storage::disk('public')->exists($att->path), 'Public disk should contain the moved file');

        // filename should be <uuid>_<original_name>
        $basename = basename($att->path);
        $this->assertStringEndsWith($originalName, $basename, 'Stored filename should end with original name');

        // prefix should look like a UUID
        $parts = explode('_', $basename, 2);
        $this->assertCount(2, $parts, 'Stored filename should contain an underscore separator after UUID');
        $uuidPart = $parts[0];
        $this->assertMatchesRegularExpression('/^[0-9a-fA-F\-]{36}$/', $uuidPart, 'Prefix should be a UUID');
    }
}

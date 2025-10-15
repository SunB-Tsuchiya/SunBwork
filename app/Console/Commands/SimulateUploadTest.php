<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SimulateUploadTest extends Command
{
    protected $signature = 'simulate:upload-test';
    protected $description = 'Simulate storing a file with Japanese filename and create an Attachment record for verification';

    public function handle()
    {
        $tmp = storage_path('app/tmp_simulate.txt');
        file_put_contents($tmp, "test content\n");
        $origName = "AIエージェント用プロンプト.txt";
        $uuid = Str::uuid()->toString();
        $storedName = $uuid . '_' . $origName;
        $path = 'chat/' . $storedName;
        $disk = Storage::disk('public');
        $put = $disk->putFileAs('chat', $tmp, $storedName);
        $this->info('put result: ' . var_export($put, true));
        $this->info('stored path: ' . $path);
        // create DB record if model exists
        if (class_exists(\App\Models\Attachment::class)) {
            $a = \App\Models\Attachment::create([
                'path' => $path,
                'original_name' => $origName,
                'mime_type' => 'text/plain',
                'size' => filesize($tmp),
                // existing schema expects integer status; use 1 as ready
                'status' => 1,
            ]);
            $this->info('Attachment created id=' . $a->id);
        } else {
            $this->warn('Attachment model not found');
        }
        return 0;
    }
}

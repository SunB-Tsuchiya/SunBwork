<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TempAttachmentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $userId = DB::table('users')->insertGetId([
            'name' => 'Migrator',
            'email' => 'migrator' . time() . '@example.com',
            'password' => bcrypt('secret'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $diaryId = DB::table('diaries')->insertGetId([
            'user_id' => $userId,
            'date' => now()->toDateString(),
            'content' => 'sample',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eventId = DB::table('events')->insertGetId([
            'user_id' => $userId,
            'title' => 'ev',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $messageId = DB::table('messages')->insertGetId([
            'from_user_id' => $userId,
            'subject' => 's',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $ids = [];
        $ids[] = DB::table('attachments')->insertGetId(['path' => 'attachments/a1.txt', 'original_name' => 'a1.txt', 'filename' => 'a1.txt', 'mime_type' => 'text/plain', 'status' => 0, 'size' => 123, 'created_at' => $now, 'updated_at' => $now]);
        $ids[] = DB::table('attachments')->insertGetId(['path' => 'attachments/a2.png', 'original_name' => 'a2.png', 'filename' => 'a2.png', 'mime_type' => 'image/png', 'status' => 0, 'size' => 456, 'created_at' => $now, 'updated_at' => $now]);
        $ids[] = DB::table('attachments')->insertGetId(['path' => 'attachments/a3.pdf', 'original_name' => 'a3.pdf', 'filename' => 'a3.pdf', 'mime_type' => 'application/pdf', 'status' => 0, 'size' => 789, 'created_at' => $now, 'updated_at' => $now]);
        $ids[] = DB::table('attachments')->insertGetId(['path' => 'attachments/a4.jpg', 'original_name' => 'a4.jpg', 'filename' => 'a4.jpg', 'mime_type' => 'image/jpeg', 'status' => 0, 'size' => 111, 'created_at' => $now, 'updated_at' => $now]);

        // Create attachmentables pivots for the seeded diary/event/message
        DB::table('attachmentables')->insertOrIgnore([
            ['attachment_id' => $ids[0], 'attachable_type' => \App\Models\Diary::class, 'attachable_id' => $diaryId, 'created_at' => $now, 'updated_at' => $now],
            ['attachment_id' => $ids[1], 'attachable_type' => \App\Models\Event::class, 'attachable_id' => $eventId, 'created_at' => $now, 'updated_at' => $now],
            ['attachment_id' => $ids[2], 'attachable_type' => \App\Models\Message::class, 'attachable_id' => $messageId, 'created_at' => $now, 'updated_at' => $now],
            ['attachment_id' => $ids[3], 'attachable_type' => \App\Models\Diary::class, 'attachable_id' => $diaryId, 'created_at' => $now, 'updated_at' => $now],
        ]);

        echo "TempAttachmentSeeder: inserted sample data and created pivots\n";
    }
}

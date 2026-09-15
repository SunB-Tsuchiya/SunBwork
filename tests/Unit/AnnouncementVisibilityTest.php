<?php

namespace Tests\Unit;

use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

// Tests\TestCase / RefreshDatabaseは使わず、各テスト専用のメモリDBのみを操作する。
class AnnouncementVisibilityTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        config(['database.default' => 'announcement_test', 'database.connections.announcement_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('announcement_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->boolean('is_ghost')->default(false);
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->string('target_type');
            $table->string('title');
            $table->text('content');
            $table->string('status');
            $table->timestamps();
        });
        Schema::create('announcement_recipients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('announcement_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert([
            ['id' => 1, 'company_id' => 99],
            ['id' => 2, 'company_id' => 10],
            ['id' => 3, 'company_id' => 20],
        ]);
        DB::table('announcements')->insert([
            ['id' => 1, 'sender_id' => 1, 'target_type' => 'all', 'title' => '管理者の下書き', 'content' => '本文', 'status' => 'draft'],
            ['id' => 2, 'sender_id' => 2, 'target_type' => 'all', 'title' => '選択会社のお知らせ', 'content' => '本文', 'status' => 'sent'],
            ['id' => 3, 'sender_id' => 3, 'target_type' => 'all', 'title' => '他社のお知らせ', 'content' => '本文', 'status' => 'sent'],
        ]);
        DB::table('announcement_recipients')->insert([
            ['announcement_id' => 1, 'user_id' => 2, 'read_at' => null],
            ['announcement_id' => 2, 'user_id' => 2, 'read_at' => null],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('announcement_test');
        \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
        $this->app->flush();
        parent::tearDown();
    }

    public function test_drafts_are_not_counted_as_sent_notifications(): void
    {
        $ids = AnnouncementRecipient::where('user_id', 2)
            ->forSentAnnouncements()
            ->pluck('announcement_id')
            ->all();

        $this->assertSame([2], $ids);
    }

    public function test_superadmin_authored_draft_is_visible_in_selected_company_context(): void
    {
        $ids = Announcement::managedInCompanyContext(10, 1)->orderBy('id')->pluck('id')->all();

        $this->assertSame([1, 2], $ids);
    }
}

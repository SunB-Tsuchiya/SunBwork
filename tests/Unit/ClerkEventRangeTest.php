<?php

namespace Tests\Unit;

use App\Models\ClerkEvent;
use App\Support\ClerkCalendarRange;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;

// Tests\TestCase / RefreshDatabaseは使わず、各テスト専用のメモリDBのみを操作する。
class ClerkEventRangeTest extends TestCase
{
    private $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = require __DIR__.'/../../bootstrap/app.php';
        $this->app->make(Kernel::class)->bootstrap();
        config(['database.default' => 'clerk_range_test', 'database.connections.clerk_range_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        DB::purge('clerk_range_test');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('clerk_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('color_key', 20)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        DB::disconnect('clerk_range_test');
        \Illuminate\Foundation\Bootstrap\HandleExceptions::flushState();
        $this->app->flush();
        parent::tearDown();
    }

    public function test_year_range_matches_calendar_strip_and_filters_overlapping_events(): void
    {
        [$start, $end] = ClerkCalendarRange::forYear(2026);
        $this->assertSame('2025-11-23 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-31 00:00:00', $end->format('Y-m-d H:i:s'));

        $this->createEvent('inside', 1, '2026-06-01', '2026-06-01');
        $this->createEvent('crosses-start', 1, '2025-11-01', '2025-11-23');
        $this->createEvent('before', 1, '2025-11-01', '2025-11-22');
        $this->createEvent('at-exclusive-end', 1, '2027-01-31', null);
        $this->createEvent('other-company', 2, '2026-06-01', null);

        $titles = ClerkEvent::where('company_id', 1)
            ->overlappingRange($start, $end)
            ->orderBy('title')
            ->pluck('title')
            ->all();

        $this->assertSame(['crosses-start', 'inside'], $titles);
    }

    private function createEvent(string $title, int $companyId, string $start, ?string $end): void
    {
        ClerkEvent::create([
            'company_id' => $companyId, 'user_id' => 1, 'title' => $title,
            'starts_at' => $start.' 00:00:00', 'ends_at' => $end ? $end.' 23:59:59' : null,
            'all_day' => true,
        ]);
    }
}

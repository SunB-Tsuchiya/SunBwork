<?php

namespace Tests\Unit;

use App\Models\JobAssignmentMessage;
use App\Models\ProjectJobAssignment;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class JobInboxSelfRequestTest extends TestCase
{
    private Capsule $database;

    protected function setUp(): void
    {
        parent::setUp();

        // Standalone in-memory connection: never boot the application's DB test hooks.
        $this->database = new Capsule();
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setAsGlobal();
        $this->database->bootEloquent();

        $this->database->schema()->create('project_job_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sender_id')->nullable();
        });
        $this->database->schema()->create('job_assignment_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_job_assignment_id');
            $table->unsignedBigInteger('sender_id')->nullable();
        });
    }

    public function test_self_request_is_received_but_a_my_job_and_another_users_job_are_not(): void
    {
        $this->database->table('project_job_assignments')->insert([
            ['id' => 1, 'user_id' => 1, 'sender_id' => null], // self request
            ['id' => 2, 'user_id' => 1, 'sender_id' => 1], // MyJob
            ['id' => 3, 'user_id' => 1, 'sender_id' => 2], // request from another user
            ['id' => 4, 'user_id' => 2, 'sender_id' => 1], // assigned to another user
        ]);
        $this->database->table('job_assignment_messages')->insert([
            ['id' => 1, 'project_job_assignment_id' => 1, 'sender_id' => 1],
            ['id' => 2, 'project_job_assignment_id' => 2, 'sender_id' => 1],
            ['id' => 3, 'project_job_assignment_id' => 3, 'sender_id' => 2],
            ['id' => 4, 'project_job_assignment_id' => 4, 'sender_id' => 1],
            ['id' => 5, 'project_job_assignment_id' => 3, 'sender_id' => 1], // own reply
        ]);

        $inboxIds = JobAssignmentMessage::query()
            ->join('project_job_assignments', 'job_assignment_messages.project_job_assignment_id', '=', 'project_job_assignments.id')
            ->forRecipientInbox(1)
            ->orderBy('job_assignment_messages.id')
            ->pluck('job_assignment_messages.id')->all();

        $this->assertSame([1, 3], $inboxIds);
        $this->assertSame([2], ProjectJobAssignment::selfAssigned()->pluck('id')->all());
        $this->assertSame([1, 3, 4], ProjectJobAssignment::coordinatorAssigned()->orderBy('id')->pluck('id')->all());
    }
}

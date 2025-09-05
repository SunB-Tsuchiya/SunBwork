<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Diary;
use Carbon\Carbon;

class AdminMarkReadAllTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_mark_all_diaries_on_date_as_read()
    {
        // Create admin user
        $admin = User::factory()->create(['is_admin' => true]);

        // Create two other users with diaries on the same date
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $date = Carbon::today()->format('Y-m-d');
        Diary::factory()->create(['user_id' => $u1->id, 'date' => $date, 'content' => 'a']);
        Diary::factory()->create(['user_id' => $u2->id, 'date' => $date, 'content' => 'b']);

        $this->actingAs($admin, 'web')
            ->post(route('admin.diaries.mark_read_all'), ['date' => $date])
            ->assertStatus(302);

        $this->assertDatabaseMissing('diaries', ['read_by' => null]);
    }
}

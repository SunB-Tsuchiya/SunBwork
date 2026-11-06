<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Portfolio\PortfolioItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** 未完了作品の定期掃除（portfolio:prune-stale）のテスト */
class PortfolioPruneTest extends TestCase
{
    use DatabaseTransactions;

    private function owner(): User
    {
        $c = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);

        return User::factory()->create(['user_role' => 'user', 'company_id' => $c->id]);
    }

    private function item(User $owner, string $status, int $hoursAgo, string $path): PortfolioItem
    {
        $item = PortfolioItem::create([
            'user_id' => $owner->id, 'type' => 'pdf', 'title' => $status, 'original_name' => 'a.pdf', 'mime' => 'application/pdf',
            'size_bytes' => 1, 'file_path' => $path, 'status' => $status,
        ]);
        PortfolioItem::whereKey($item->id)->update(['updated_at' => now()->subHours($hoursAgo)]);

        return $item;
    }

    public function test_24時間経過した未完了だけが実体ごと消え完了と新しいものは残る(): void
    {
        Storage::fake('portfolio_test');
        config(['filesystems.portfolio_disk' => 'portfolio_test']);
        $owner = $this->owner();
        $disk = Storage::disk('portfolio_test');
        $disk->put('portfolio/x/old.pdf', 'a');
        $disk->put('portfolio/x/fresh.pdf', 'b');
        $disk->put('portfolio/x/ready.pdf', 'c');

        $oldFailed = $this->item($owner, 'failed', 30, 'portfolio/x/old.pdf');
        $oldUploading = $this->item($owner, 'uploading', 48, 'portfolio/x/none.pdf');
        $fresh = $this->item($owner, 'processing', 2, 'portfolio/x/fresh.pdf');
        $readyOld = $this->item($owner, 'ready', 500, 'portfolio/x/ready.pdf');
        // アップ途中の一時ファイル
        $disk->put("portfolio/{$owner->id}/tmp/{$oldUploading->id}.part", 'partial');

        $this->artisan('portfolio:prune-stale')->assertSuccessful();

        $this->assertDatabaseMissing('portfolio_items', ['id' => $oldFailed->id]);
        $this->assertDatabaseMissing('portfolio_items', ['id' => $oldUploading->id]);
        $this->assertDatabaseHas('portfolio_items', ['id' => $fresh->id]);
        $this->assertDatabaseHas('portfolio_items', ['id' => $readyOld->id]);
        $disk->assertMissing('portfolio/x/old.pdf');
        $disk->assertMissing("portfolio/{$owner->id}/tmp/{$oldUploading->id}.part");
        $disk->assertExists('portfolio/x/fresh.pdf');
        $disk->assertExists('portfolio/x/ready.pdf');
    }

    public function test_dry_runは削除しない(): void
    {
        Storage::fake('portfolio_test');
        config(['filesystems.portfolio_disk' => 'portfolio_test']);
        $item = $this->item($this->owner(), 'failed', 30, 'portfolio/x/old.pdf');

        $this->artisan('portfolio:prune-stale', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('portfolio_items', ['id' => $item->id]);
    }
}

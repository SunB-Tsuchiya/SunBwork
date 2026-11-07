<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 動画取り込み（init → chunk → video-complete）のテスト。
 * 圧縮はブラウザ側で行うため、サーバーは「MP4/MOV として検証し、ポスターとサムネイルを作る」部分だけを確認する。
 */
class PortfolioVideoTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeDisk(): void
    {
        Storage::fake('portfolio_test');
        config(['filesystems.portfolio_disk' => 'portfolio_test']);
    }

    private function designer(?Company $company = null): User
    {
        $company ??= Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $user = User::factory()->create(['user_role' => 'user', 'company_id' => $company->id]);
        PortfolioPermission::create(['user_id' => $user->id, 'enabled' => true]);

        return $user;
    }

    /** MP4（または MOV）らしい先頭を持つ疑似動画。中身の再生可否はテスト対象外 */
    private function fakeVideo(string $brand = 'isom', int $size = 5000): string
    {
        return pack('N', 24) . 'ftyp' . str_pad($brand, 4) . "\0\0\0\0" . $brand . str_repeat('x', $size);
    }

    private function init(User $user, int $size)
    {
        return $this->actingAs($user)->postJson(route('portfolio.api.video.init'), [
            'title' => 'キーホルダー', 'original_name' => 'kh.mp4', 'size' => $size,
        ]);
    }

    private function sendChunks(User $user, int $id, string $bin, int $chunk = 2000): void
    {
        for ($offset = 0; $offset < strlen($bin); $offset += $chunk) {
            $this->actingAs($user)->post(route('portfolio.api.items.chunk', ['item' => $id]), [
                'offset' => $offset,
                'chunk' => UploadedFile::fake()->createWithContent('c', substr($bin, $offset, $chunk)),
            ], ['Accept' => 'application/json'])->assertOk();
        }
    }

    private function complete(User $user, int $id, array $extra = [])
    {
        return $this->actingAs($user)->post(route('portfolio.api.video.complete', ['item' => $id]), $extra + [
            'poster' => UploadedFile::fake()->image('poster.jpg', 640, 360),
            'width' => 1280, 'height' => 720, 'duration' => 12.4,
        ], ['Accept' => 'application/json']);
    }

    public function test_動画を分割アップしてポスターとサムネイルが作られる(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->fakeVideo();

        $id = $this->init($user, strlen($bin))->assertCreated()->json('id');
        $this->sendChunks($user, $id, $bin);
        $res = $this->complete($user, $id)->assertOk();

        $res->assertJsonPath('type', 'video')->assertJsonPath('status', 'ready')->assertJsonPath('duration_sec', 12)->assertJsonPath('has_poster', true);
        $item = PortfolioItem::find($id);
        $this->assertSame('video/mp4', $item->mime);
        $this->assertSame(1280, $item->width);
        foreach ([$item->file_path, $item->poster_path, $item->thumb_path] as $path) {
            Storage::disk('portfolio_test')->assertExists($path);
        }
        Storage::disk('portfolio_test')->assertMissing("portfolio/{$user->id}/tmp/{$id}.part");
    }

    public function test_MOVはquicktimeとして保存される(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->fakeVideo('qt  ');
        $id = $this->init($user, strlen($bin))->json('id');
        $this->sendChunks($user, $id, $bin);
        $this->complete($user, $id)->assertOk();

        $item = PortfolioItem::find($id);
        $this->assertSame('video/quicktime', $item->mime);
        $this->assertStringEndsWith('.mov', $item->file_path);
    }

    public function test_動画として配信されRangeリクエストに部分応答する(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->fakeVideo('isom', 20000);
        $id = $this->init($user, strlen($bin))->json('id');
        $this->sendChunks($user, $id, $bin, 5000);
        $this->complete($user, $id)->assertOk();

        $full = $this->actingAs($user)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'file']));
        $full->assertOk()->assertHeader('Content-Type', 'video/mp4');
        // シーク（Range）に対応している
        $this->actingAs($user)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'file']), ['Range' => 'bytes=0-99'])
            ->assertStatus(206);
        $this->actingAs($user)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'poster']))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_MP4でない中身は拒否され作品も残らない(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = str_repeat('これは動画ではありません', 100);
        $id = $this->init($user, strlen($bin))->json('id');
        $this->sendChunks($user, $id, $bin);

        $this->complete($user, $id)->assertStatus(422)->assertJsonPath('message', '動画ファイル（MP4 / MOV）ではありません。');
        $this->assertDatabaseMissing('portfolio_items', ['id' => $id]);
    }

    public function test_申告サイズと違えば完了できない(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->fakeVideo();
        $id = $this->init($user, strlen($bin) + 50)->json('id');
        $this->sendChunks($user, $id, $bin);

        $this->complete($user, $id)->assertStatus(422);
        $this->assertDatabaseMissing('portfolio_items', ['id' => $id]);
    }

    public function test_ポスターが無ければ完了できない(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->fakeVideo();
        $id = $this->init($user, strlen($bin))->json('id');
        $this->sendChunks($user, $id, $bin);

        $this->actingAs($user)->postJson(route('portfolio.api.video.complete', ['item' => $id]), ['width' => 1, 'height' => 1, 'duration' => 1])
            ->assertStatus(422);
    }

    public function test_上限1GBを超える申告は拒否される(): void
    {
        $this->fakeDisk();
        $user = $this->designer();

        $this->init($user, 1024 * 1024 * 1024 + 1)->assertStatus(422);
    }

    public function test_削除すると動画もポスターもサムネイルも消える(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->fakeVideo();
        $id = $this->init($user, strlen($bin))->json('id');
        $this->sendChunks($user, $id, $bin);
        $this->complete($user, $id)->assertOk();
        $item = PortfolioItem::find($id);
        $paths = [$item->file_path, $item->poster_path, $item->thumb_path];

        $this->actingAs($user)->deleteJson(route('portfolio.api.items.destroy', ['item' => $id]))->assertOk();

        foreach ($paths as $path) {
            Storage::disk('portfolio_test')->assertMissing($path);
        }
    }

    public function test_スイッチをOFFにしたAdminは動画を作れず他人の動画は閲覧のみ(): void
    {
        $this->fakeDisk();
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $designer = $this->designer($company);
        $admin = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);
        PortfolioPermission::create(['user_id' => $admin->id, 'enabled' => false]); // OFF にした Admin
        $bin = $this->fakeVideo();

        $this->init($admin, strlen($bin))->assertForbidden();

        $id = $this->init($designer, strlen($bin))->json('id');
        $this->sendChunks($designer, $id, $bin);
        $this->complete($designer, $id)->assertOk();

        $this->actingAs($admin)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'file']))->assertOk();
        $this->complete($admin, $id)->assertForbidden();
    }

    public function test_別会社の人は動画を見られない(): void
    {
        $this->fakeDisk();
        $designer = $this->designer();
        $stranger = $this->designer();
        $bin = $this->fakeVideo();
        $id = $this->init($designer, strlen($bin))->json('id');
        $this->sendChunks($designer, $id, $bin);
        $this->complete($designer, $id)->assertOk();

        $this->actingAs($stranger)->get(route('portfolio.api.files', ['item' => $id, 'kind' => 'file']))->assertNotFound();
    }
}

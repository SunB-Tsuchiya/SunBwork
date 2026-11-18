<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Tests\TestCase;

/**
 * ブラウザ（PDF.js）で画像化したページの受け取り（pages/{n} → pages-complete）のテスト。
 * 画像化をブラウザに移した理由: さくらの Ghostscript 9.52 が印刷用 PDF を正しく描画できないため。
 */
class PortfolioPdfBrowserPagesTest extends TestCase
{
    use DatabaseTransactions;

    private function setUpDisk(): void
    {
        Storage::fake('portfolio_test');
        config(['filesystems.portfolio_disk' => 'portfolio_test']);
    }

    private function designer(): User
    {
        $c = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $u = User::factory()->create(['user_role' => 'user', 'company_id' => $c->id]);
        PortfolioPermission::create(['user_id' => $u->id, 'enabled' => true]);

        return $u;
    }

    /** 「変換中」の PDF 作品（原本 PDF は保存済み）を作る */
    private function processingItem(User $owner, int $pages = 3): PortfolioItem
    {
        $path = "portfolio/{$owner->id}/" . uniqid() . '.pdf';
        Storage::disk('portfolio_test')->put($path, "%PDF-1.4\nx");

        return PortfolioItem::create([
            'user_id' => $owner->id, 'type' => 'pdf', 'title' => 'T', 'original_name' => 'a.pdf', 'mime' => 'application/pdf',
            'size_bytes' => 10, 'file_path' => $path, 'page_count' => $pages, 'status' => 'processing',
        ]);
    }

    private function jpeg(int $w = 200, int $h = 300, string $format = 'jpeg'): UploadedFile
    {
        $im = new Imagick();
        $im->newImage($w, $h, new ImagickPixel('rgb(10,120,200)'));
        $im->setImageFormat($format);

        return UploadedFile::fake()->createWithContent('p.jpg', $im->getImageBlob());
    }

    private function sendPage(User $u, PortfolioItem $item, int $n, ?UploadedFile $file = null)
    {
        return $this->actingAs($u)->post(
            route('portfolio.api.pdf.page', ['item' => $item->id, 'page' => $n]),
            ['image' => $file ?? $this->jpeg()],
            ['Accept' => 'application/json'],
        );
    }

    public function test_ページ画像を保存し寸法はサーバーが画像から読み取る(): void
    {
        $this->setUpDisk();
        $u = $this->designer();
        $item = $this->processingItem($u);

        $this->sendPage($u, $item, 1, $this->jpeg(320, 480))->assertOk()->assertJson(['processed' => 1, 'total' => 3]);

        $page = $item->pages()->where('page_no', 1)->first();
        $this->assertSame([320, 480], [(int) $page->width, (int) $page->height]);
        Storage::disk('portfolio_test')->assertExists($page->path);
        $this->assertStringEndsWith('_p001.jpg', $page->path);
    }

    public function test_同じページの再送は上書きされ行は増えない(): void
    {
        $this->setUpDisk();
        $u = $this->designer();
        $item = $this->processingItem($u);

        $this->sendPage($u, $item, 2, $this->jpeg(100, 100))->assertOk();
        $this->sendPage($u, $item, 2, $this->jpeg(150, 200))->assertOk();

        $this->assertSame(1, $item->pages()->count());
        $this->assertSame(150, (int) $item->pages()->first()->width);
    }

    public function test_JPEG以外やページ範囲外や巨大寸法は拒否する(): void
    {
        $this->setUpDisk();
        $u = $this->designer();
        $item = $this->processingItem($u);

        $this->sendPage($u, $item, 1, $this->jpeg(50, 50, 'png'))->assertStatus(422);   // PNG
        $this->sendPage($u, $item, 0)->assertStatus(422);                                // 0 ページ目は範囲外
        $this->sendPage($u, $item, 4)->assertStatus(422);                                // ページ数（3）を超える
        $this->sendPage($u, $item, 1, UploadedFile::fake()->createWithContent('x.jpg', 'not an image'))->assertStatus(422);
        $this->sendPage($u, $item, 1, $this->jpeg(4100, 10))->assertStatus(422);        // 長辺 4000px 超
        $this->assertSame(0, $item->pages()->count());
    }

    public function test_他人の作品や変換中以外の作品には送れない(): void
    {
        $this->setUpDisk();
        $owner = $this->designer();
        $other = $this->designer();
        $item = $this->processingItem($owner);

        $this->assertContains($this->sendPage($other, $item, 1)->getStatusCode(), [403, 404]);

        $item->update(['status' => 'ready']);
        $this->sendPage($owner, $item, 1)->assertStatus(409);
        $this->assertSame(0, $item->pages()->count());
    }

    public function test_容量上限を超えるページは作品ごと破棄される(): void
    {
        $this->setUpDisk();
        config(['filesystems.portfolio_quota' => 100]);
        $u = $this->designer();
        $item = $this->processingItem($u);

        $this->sendPage($u, $item, 1)->assertStatus(422);

        $this->assertDatabaseMissing('portfolio_items', ['id' => $item->id]);
    }

    public function test_全ページが揃えば完了処理でサムネイルと実サイズが確定する(): void
    {
        $this->setUpDisk();
        $u = $this->designer();
        $item = $this->processingItem($u);
        foreach ([1, 2, 3] as $n) {
            $this->sendPage($u, $item, $n, $this->jpeg(200 + $n, 300))->assertOk();
        }

        $res = $this->actingAs($u)->postJson(route('portfolio.api.pdf.pages_complete', ['item' => $item->id]))->assertOk();

        $item->refresh();
        $this->assertSame('ready', $item->status);
        $this->assertSame(201, (int) $item->width);
        $this->assertNotNull($item->thumb_path);
        Storage::disk('portfolio_test')->assertExists($item->thumb_path);
        $this->assertGreaterThan(10, (int) $item->size_bytes);
        $this->assertNotNull($res->json('item'));
    }

    public function test_ページが欠けていると完了できない(): void
    {
        $this->setUpDisk();
        $u = $this->designer();
        $item = $this->processingItem($u);
        $this->sendPage($u, $item, 1)->assertOk();
        $this->sendPage($u, $item, 3)->assertOk(); // 2 ページ目が欠けている

        $this->actingAs($u)->postJson(route('portfolio.api.pdf.pages_complete', ['item' => $item->id]))->assertStatus(422);

        $this->assertSame('processing', $item->refresh()->status);
    }
}

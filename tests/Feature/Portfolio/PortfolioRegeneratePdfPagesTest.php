<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioItemPage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Tests\TestCase;

/** 保存済み PDF のページ画像の再変換（portfolio:regenerate-pdf-pages）のテスト */
class PortfolioRegeneratePdfPagesTest extends TestCase
{
    use DatabaseTransactions;

    private function cmykPdfBytes(): string
    {
        $im = new Imagick();
        $im->newImage(300, 200, new ImagickPixel('white'));
        $d = new ImagickDraw();
        $d->setFillColor(new ImagickPixel('rgb(255,0,0)'));
        $d->rectangle(0, 0, 149, 199);
        $im->drawImage($d);
        $im->transformImageColorspace(Imagick::COLORSPACE_CMYK);
        $im->setImageFormat('pdf');

        return $im->getImagesBlob();
    }

    private function gsOrSkip(): void
    {
        if (! class_exists(Imagick::class) || trim((string) @shell_exec('gs --version 2>/dev/null')) === '') {
            $this->markTestSkipped('Imagick / Ghostscript がありません。');
        }
    }

    private function red(string $blob): array
    {
        $im = new Imagick();
        $im->readImageBlob($blob);
        $c = $im->getImagePixelColor((int) ($im->getImageWidth() / 4), (int) ($im->getImageHeight() / 2))->getColor();

        return [$c['r'], $c['g'], $c['b']];
    }

    private function makeItem(string $title = 'T'): array
    {
        Storage::fake('portfolio_test');
        config(['filesystems.portfolio_disk' => 'portfolio_test']);
        $c = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $user = User::factory()->create(['user_role' => 'user', 'company_id' => $c->id]);
        $disk = Storage::disk('portfolio_test');

        $base = "portfolio/{$user->id}/abc";
        $disk->put($base . '.pdf', $this->cmykPdfBytes());
        // 反転した（誤った）ページ画像を置く
        $bad = new Imagick();
        $bad->newImage(100, 80, new ImagickPixel('rgb(0,255,255)'));
        $bad->setImageFormat('jpeg');
        $disk->put($base . '_p001.jpg', $bad->getImagesBlob());

        $item = PortfolioItem::create([
            'user_id' => $user->id, 'type' => 'pdf', 'title' => $title, 'original_name' => 'a.pdf', 'mime' => 'application/pdf',
            'size_bytes' => 1, 'file_path' => $base . '.pdf', 'page_count' => 1, 'status' => 'ready',
        ]);
        PortfolioItemPage::create(['item_id' => $item->id, 'page_no' => 1, 'path' => $base . '_p001.jpg', 'width' => 100, 'height' => 80]);

        return [$item, $disk, $base];
    }

    public function test_反転したページ画像が正しい色で作り直される(): void
    {
        $this->gsOrSkip();
        [$item, $disk, $base] = $this->makeItem();

        $this->artisan('portfolio:regenerate-pdf-pages', ['--item' => $item->id])->assertSuccessful();

        [$r, $g, $b] = $this->red($disk->get($base . '_p001.jpg'));
        $this->assertTrue($r > 200 && $g < 90 && $b < 90, "赤のはずが ($r,$g,$b)");
        $disk->assertExists($base . '_t.jpg');
        $item->refresh();
        $this->assertSame($base . '_t.jpg', $item->thumb_path);
        $this->assertGreaterThan(1, $item->size_bytes);
        $this->assertNotSame(100, (int) $item->pages()->first()->width);
    }

    public function test_dry_runは何も変更しない(): void
    {
        $this->gsOrSkip();
        [$item, $disk, $base] = $this->makeItem();
        $before = $disk->get($base . '_p001.jpg');

        $this->artisan('portfolio:regenerate-pdf-pages', ['--item' => $item->id, '--dry-run' => true])->assertSuccessful();

        $this->assertSame($before, $disk->get($base . '_p001.jpg'));
        $disk->assertMissing($base . '_t.jpg');
    }

    public function test_原本が無い作品は飛ばして失敗コードを返し他に影響しない(): void
    {
        [$item, $disk, $base] = $this->makeItem();
        $disk->delete($base . '.pdf');
        $before = $disk->get($base . '_p001.jpg');

        $this->artisan('portfolio:regenerate-pdf-pages', ['--item' => $item->id])->assertFailed();

        $this->assertSame($before, $disk->get($base . '_p001.jpg'));
    }
}

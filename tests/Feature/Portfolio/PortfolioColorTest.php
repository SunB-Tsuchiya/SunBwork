<?php

namespace Tests\Feature\Portfolio;

use App\Services\Portfolio\ImageProcessor;
use App\Services\Portfolio\PdfRasterizer;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Tests\TestCase;

/**
 * CMYK（印刷用の色）の PDF・JPEG が、ネガポジ反転せずに画像化されること。
 * 2026-11-18 の不具合: CMYK で読み込まれた画像に setImageColorspace(sRGB)（名前を付け替えるだけで色は変換しない）を使っていたため、
 * 白が黒・赤がシアンに反転した。RGB の素材では起きないので、素材は CMYK と RGB の両方で確かめる。
 *
 * 素材: 左=赤・中=シアン・右=白 の 3 色のパッチ。
 */
class PortfolioColorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        if (! class_exists(Imagick::class)) {
            $this->markTestSkipped('Imagick がありません。');
        }
        $this->dir = sys_get_temp_dir() . '/portfolio_color_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** 3 色のパッチ画像（RGB）。$cmyk なら CMYK に変換して返す */
    private function patches(bool $cmyk): Imagick
    {
        $im = new Imagick();
        $im->newImage(600, 200, new ImagickPixel('white'));
        foreach ([[0, 'rgb(255,0,0)'], [200, 'rgb(0,255,255)'], [400, 'rgb(255,255,255)']] as [$x, $color]) {
            $d = new ImagickDraw();
            $d->setFillColor(new ImagickPixel($color));
            $d->rectangle($x, 0, $x + 199, 199);
            $im->drawImage($d);
        }
        if ($cmyk) {
            $im->transformImageColorspace(Imagick::COLORSPACE_CMYK);
        }

        return $im;
    }

    private function makePdf(bool $cmyk): string
    {
        $im = $this->patches($cmyk);
        $im->setImageFormat('pdf');
        $path = $this->dir . '/' . ($cmyk ? 'cmyk' : 'rgb') . '.pdf';
        $im->writeImage($path);

        return $path;
    }

    private function makeJpeg(bool $cmyk): string
    {
        $im = $this->patches($cmyk);
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality(95);
        $path = $this->dir . '/' . ($cmyk ? 'cmyk' : 'rgb') . '.jpg';
        $im->writeImage($path);

        return $path;
    }

    /** パッチの中心の色 [r, g, b]（0 = 左, 1 = 中, 2 = 右） */
    private function pixel(string $jpegBlob, int $patch): array
    {
        $im = new Imagick();
        $im->readImageBlob($jpegBlob);
        $x = (int) ($im->getImageWidth() * ($patch * 200 + 100) / 600);
        $c = $im->getImagePixelColor($x, (int) ($im->getImageHeight() / 2))->getColor();

        return [$c['r'], $c['g'], $c['b']];
    }

    /** 左=赤、中=シアン、右=白（反転していない）こと */
    private function assertNotInverted(string $blob): void
    {
        [$r1, $g1, $b1] = $this->pixel($blob, 0);
        $this->assertTrue($r1 > 200 && $g1 < 90 && $b1 < 90, "左のパッチが赤ではありません: ($r1,$g1,$b1)");

        [$r2, $g2, $b2] = $this->pixel($blob, 1);
        $this->assertTrue($r2 < 60 && $g2 > 140 && $b2 > 200, "中のパッチがシアンではありません: ($r2,$g2,$b2)");

        [$r3, $g3, $b3] = $this->pixel($blob, 2);
        $this->assertTrue($r3 > 240 && $g3 > 240 && $b3 > 240, "右のパッチが白ではありません（黒になっていれば反転）: ($r3,$g3,$b3)");
    }

    private function requireGhostscript(): void
    {
        if (trim((string) @shell_exec('gs --version 2>/dev/null')) === '') {
            $this->markTestSkipped('Ghostscript がありません。');
        }
    }

    public function test_CMYKのPDFはネガポジ反転せずに画像化される(): void
    {
        $this->requireGhostscript();

        $out = (new PdfRasterizer())->renderPage($this->makePdf(true), 0);

        $this->assertNotInverted($out['blob']);
    }

    public function test_RGBのPDFは従来どおり画像化される(): void
    {
        $this->requireGhostscript();

        $out = (new PdfRasterizer())->renderPage($this->makePdf(false), 0);

        $this->assertNotInverted($out['blob']);
    }

    public function test_CMYKのJPEGはネガポジ反転せずに縮小される(): void
    {
        $out = (new ImageProcessor())->process($this->makeJpeg(true));

        $this->assertNotInverted($out['main']);
        $this->assertNotInverted($out['thumb']);
    }

    public function test_RGBのJPEGは従来どおり縮小される(): void
    {
        $out = (new ImageProcessor())->process($this->makeJpeg(false));

        $this->assertNotInverted($out['main']);
    }

    public function test_グレースケールのJPEGも壊れない(): void
    {
        $im = new Imagick();
        $im->newImage(300, 100, new ImagickPixel('rgb(240,240,240)'));
        $im->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        $im->setImageFormat('jpeg');
        $path = $this->dir . '/gray.jpg';
        $im->writeImage($path);

        $out = (new ImageProcessor())->process($path);

        $check = new Imagick();
        $check->readImageBlob($out['main']);
        $c = $check->getImagePixelColor(50, 50)->getColor();
        $this->assertTrue($c['r'] > 220 && $c['g'] > 220 && $c['b'] > 220, '明るい灰色が暗くなっています');
    }
}

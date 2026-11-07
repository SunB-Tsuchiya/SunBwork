<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\Portfolio\PortfolioItem;
use App\Models\Portfolio\PortfolioPermission;
use App\Models\User;
use App\Services\Portfolio\PdfRasterizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Tests\TestCase;

/**
 * PDF 取り込み（init → chunk → complete → process）のテスト。
 * 実際に Imagick で複数ページの PDF を作り、サーバーが画像化できることまで確認する。
 */
class PortfolioPdfTest extends TestCase
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

    /** 指定ページ数の PDF を作り、バイナリを返す */
    private function makePdf(int $pages): string
    {
        $all = new Imagick();
        for ($i = 1; $i <= $pages; $i++) {
            $p = new Imagick();
            $p->newImage(300, 400, 'white');
            $p->setImageFormat('pdf');
            $all->addImage($p);
        }
        $all->setImageFormat('pdf');

        return $all->getImagesBlob();
    }

    private function init(User $user, int $size, array $extra = [])
    {
        return $this->actingAs($user)->postJson(route('portfolio.api.pdf.init'), [
            'title' => 'カタログ', 'original_name' => 'catalog.pdf', 'size' => $size,
        ] + $extra);
    }

    /** バイナリを小さな塊に分けて chunk に送る */
    private function sendChunks(User $user, int $id, string $bin, int $chunk = 700): void
    {
        for ($offset = 0; $offset < strlen($bin); $offset += $chunk) {
            $file = UploadedFile::fake()->createWithContent('chunk', substr($bin, $offset, $chunk));
            $this->actingAs($user)->post(route('portfolio.api.items.chunk', ['item' => $id]), [
                'offset' => $offset, 'chunk' => $file,
            ], ['Accept' => 'application/json'])->assertOk();
        }
    }

    public function test_PDFを分割アップしてページ画像まで変換できる(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->makePdf(6);
        $this->assertGreaterThan(1400, strlen($bin), 'テスト用 PDF が複数チャンクになる大きさであること');

        $id = $this->init($user, strlen($bin), ['description' => '説明', 'tags' => ['冊子']])->assertCreated()->json('id');
        $this->sendChunks($user, $id, $bin);
        $this->actingAs($user)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))
            ->assertOk()->assertJsonPath('page_count', 6);
        $this->assertSame('processing', PortfolioItem::find($id)->status);

        // process を繰り返す（1 回 4 ページ → 2 回で完了）
        $first = $this->actingAs($user)->postJson(route('portfolio.api.pdf.process', ['item' => $id]))->assertOk();
        $first->assertJsonPath('done', false)->assertJsonPath('processed', PdfRasterizer::PAGES_PER_CALL);
        $second = $this->actingAs($user)->postJson(route('portfolio.api.pdf.process', ['item' => $id]))->assertOk();
        $second->assertJsonPath('done', true)->assertJsonPath('processed', 6);

        $item = PortfolioItem::with(['pages', 'tags'])->find($id);
        $this->assertSame('ready', $item->status);
        $this->assertCount(6, $item->pages);
        $this->assertSame(['冊子'], $item->tags->pluck('name')->all());
        Storage::disk('portfolio_test')->assertExists($item->file_path);
        Storage::disk('portfolio_test')->assertExists($item->thumb_path);
        Storage::disk('portfolio_test')->assertExists($item->pages[0]->path);
        // 一時ファイルは残らない
        Storage::disk('portfolio_test')->assertMissing("portfolio/{$user->id}/tmp/{$id}.part");

        // ページ画像は本人に配信される
        $this->actingAs($user)->get(route('portfolio.api.pages', ['item' => $id, 'page' => 3]))->assertOk();
        $this->actingAs($user)->get(route('portfolio.api.pages', ['item' => $id, 'page' => 7]))->assertNotFound();
    }

    public function test_削除するとPDF本体もページ画像も消える(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->makePdf(2);
        $id = $this->init($user, strlen($bin))->json('id');
        $this->sendChunks($user, $id, $bin);
        $this->actingAs($user)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))->assertOk();
        $this->actingAs($user)->postJson(route('portfolio.api.pdf.process', ['item' => $id]))->assertJsonPath('done', true);
        $item = PortfolioItem::with('pages')->find($id);
        $paths = [$item->file_path, $item->thumb_path, ...$item->pages->pluck('path')->all()];

        $this->actingAs($user)->deleteJson(route('portfolio.api.items.destroy', ['item' => $id]))->assertOk();

        foreach ($paths as $path) {
            Storage::disk('portfolio_test')->assertMissing($path);
        }
        $this->assertDatabaseMissing('portfolio_item_pages', ['item_id' => $id]);
    }

    public function test_送信位置がずれたら409で現在位置を返す(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->makePdf(2);
        $id = $this->init($user, strlen($bin))->json('id');

        $res = $this->actingAs($user)->post(route('portfolio.api.items.chunk', ['item' => $id]), [
            'offset' => 500, 'chunk' => UploadedFile::fake()->createWithContent('c', 'abc'),
        ], ['Accept' => 'application/json']);

        $res->assertStatus(409)->assertJsonPath('size', 0);
    }

    public function test_申告サイズと違う場合は完了できず作品も残らない(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->makePdf(2);
        $id = $this->init($user, strlen($bin) + 100)->json('id'); // 実際より大きく申告
        $this->sendChunks($user, $id, $bin);

        $this->actingAs($user)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))->assertStatus(422);
        $this->assertDatabaseMissing('portfolio_items', ['id' => $id]);
    }

    public function test_PDFでない中身は拒否され作品も残らない(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $fake = str_repeat('これはPDFではありません', 50); // 拡張子が .pdf でも中身で判定する
        $id = $this->init($user, strlen($fake))->json('id');
        $this->sendChunks($user, $id, $fake);

        $this->actingAs($user)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))
            ->assertStatus(422)->assertJsonPath('message', 'PDF ファイルではありません。');
        $this->assertDatabaseMissing('portfolio_items', ['id' => $id]);
    }

    public function test_ページ数が上限を超えると拒否される(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->makePdf(PdfRasterizer::MAX_PAGES + 1);
        $id = $this->init($user, strlen($bin))->json('id');
        $this->sendChunks($user, $id, $bin, 200000);

        $this->actingAs($user)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))->assertStatus(422);
        $this->assertDatabaseMissing('portfolio_items', ['id' => $id]);
    }

    public function test_スイッチをOFFにしたAdminは閲覧のみでPDFを作れず他人のPDFも操作できない(): void
    {
        $this->fakeDisk();
        $company = Company::create(['name' => 'T-' . uniqid(), 'code' => 'T' . uniqid(), 'company_type' => 'sunbrain']);
        $designer = $this->designer($company);
        $admin = User::factory()->create(['user_role' => 'admin', 'company_id' => $company->id]);
        PortfolioPermission::create(['user_id' => $admin->id, 'enabled' => false]); // OFF にした Admin
        $bin = $this->makePdf(2);

        // OFF の Admin は init できない
        $this->init($admin, strlen($bin))->assertForbidden();

        // 本人が作った PDF: Admin はページ画像を閲覧できるが、chunk / process は 403
        $id = $this->init($designer, strlen($bin))->json('id');
        $this->sendChunks($designer, $id, $bin);
        $this->actingAs($designer)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))->assertOk();
        $this->actingAs($designer)->postJson(route('portfolio.api.pdf.process', ['item' => $id]))->assertJsonPath('done', true);

        $this->actingAs($admin)->get(route('portfolio.api.pages', ['item' => $id, 'page' => 1]))->assertOk();
        $this->actingAs($admin)->postJson(route('portfolio.api.pdf.process', ['item' => $id]))->assertForbidden();
    }

    public function test_別会社の人はPDFのページ画像を見られない(): void
    {
        $this->fakeDisk();
        $designer = $this->designer();
        $stranger = $this->designer(); // 別の会社
        $bin = $this->makePdf(1);
        $id = $this->init($designer, strlen($bin))->json('id');
        $this->sendChunks($designer, $id, $bin);
        $this->actingAs($designer)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))->assertOk();
        $this->actingAs($designer)->postJson(route('portfolio.api.pdf.process', ['item' => $id]))->assertJsonPath('done', true);

        $this->actingAs($stranger)->get(route('portfolio.api.pages', ['item' => $id, 'page' => 1]))->assertNotFound();
        $this->actingAs($stranger)->postJson(route('portfolio.api.items.chunk', ['item' => $id]), [])->assertNotFound();
    }

    public function test_変換中に容量上限を超えたら取り込みを中止して作品を残さない(): void
    {
        $this->fakeDisk();
        $user = $this->designer();
        $bin = $this->makePdf(3);
        // 初回チェック（原本サイズの 2 倍）は通るが、ページ画像を書き始めると超える上限にする
        config(['filesystems.portfolio_quota' => strlen($bin) * 2 + 10]);

        $id = $this->init($user, strlen($bin))->assertCreated()->json('id');
        $this->sendChunks($user, $id, $bin);
        $this->actingAs($user)->postJson(route('portfolio.api.pdf.complete', ['item' => $id]))->assertOk();

        $this->actingAs($user)->postJson(route('portfolio.api.pdf.process', ['item' => $id]))
            ->assertStatus(422)->assertJsonPath('message', '保存容量の上限（10GB）を超えるため、このPDFは取り込めません。');
        $this->assertDatabaseMissing('portfolio_items', ['id' => $id]);
        $this->assertSame([], Storage::disk('portfolio_test')->allFiles("portfolio/{$user->id}"));
    }
}

<?php

namespace Tests\Feature\Portfolio;

use App\Models\Changelog;
use Database\Seeders\ChangelogSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 更新ログの「1件だけ登録」切り替え（CHANGELOG_ONLY）のテスト。
 * 本番へ一部の機能だけを反映するとき、登録済みの他のエントリを上書きしないための仕組み。
 */
class PortfolioChangelogSeedTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        putenv('CHANGELOG_ONLY'); // 他のテストへ漏らさない
        parent::tearDown();
    }

    private function localEdit(string $version): void
    {
        Changelog::where('version', $version)->delete();
        Changelog::create([
            'version' => $version, 'title' => 'ローカルで直した題名', 'released_at' => '2026-10-30',
            'summary' => 'ローカルで直した要約', 'body' => '<p>ローカルで直した本文</p>',
        ]);
    }

    public function test_CHANGELOG_ONLY_を指定すると指定した1件だけが登録され他は上書きされない(): void
    {
        $this->localEdit('announcement-blocks-1');
        Changelog::where('version', 'portfolio-1')->delete();

        putenv('CHANGELOG_ONLY=portfolio-1');
        $this->seed(ChangelogSeeder::class);

        $this->assertDatabaseHas('changelogs', ['version' => 'portfolio-1']);
        // 登録済みの別エントリは、ファイルの内容で上書きされない
        $this->assertSame('ローカルで直した題名', Changelog::where('version', 'announcement-blocks-1')->value('title'));
    }

    public function test_指定しなければ従来どおり全エントリを登録する(): void
    {
        $this->localEdit('announcement-blocks-1');

        putenv('CHANGELOG_ONLY');
        $this->seed(ChangelogSeeder::class);

        $this->assertNotSame('ローカルで直した題名', Changelog::where('version', 'announcement-blocks-1')->value('title'));
        $this->assertDatabaseHas('changelogs', ['version' => 'portfolio-1']);
    }
}

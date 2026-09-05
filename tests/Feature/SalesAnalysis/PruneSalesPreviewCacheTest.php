<?php

namespace Tests\Feature\SalesAnalysis;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PruneSalesPreviewCacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = storage_path('framework/cache/sales-preview-test-' . uniqid());
        File::ensureDirectoryExists($this->tempDir);

        config([
            'sales_analysis.import_preview_cache_store' => 'sales_preview_test',
            'sales_analysis.preview_cache_ttl_minutes' => 30,
            'cache.stores.sales_preview_test' => [
                'driver' => 'file',
                'path' => $this->tempDir,
                'lock_path' => $this->tempDir,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDir);

        parent::tearDown();
    }

    public function test_deletes_files_older_than_double_the_ttl_and_keeps_recent_ones()
    {
        $oldFile = $this->tempDir . '/old.cache';
        $recentFile = $this->tempDir . '/recent.cache';

        File::put($oldFile, 'expired preview data');
        File::put($recentFile, 'recent preview data');

        // TTL=30分の2倍=60分より古い（70分前）ファイルは削除対象、10分前のファイルは対象外
        touch($oldFile, time() - 70 * 60);
        touch($recentFile, time() - 10 * 60);

        $this->artisan('sales:prune-preview-cache')->assertSuccessful();

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);
    }

    public function test_does_nothing_when_directory_does_not_exist_yet()
    {
        File::deleteDirectory($this->tempDir);

        $this->artisan('sales:prune-preview-cache')->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->tempDir);
    }

    public function test_does_nothing_when_configured_store_is_not_file_driver()
    {
        config(['sales_analysis.import_preview_cache_store' => 'array']);

        $recentFile = $this->tempDir . '/recent.cache';
        File::put($recentFile, 'should remain untouched');

        $this->artisan('sales:prune-preview-cache')->assertSuccessful();

        // arrayストア設定時はファイルドライバのディレクトリに一切触れない
        $this->assertFileExists($recentFile);
    }
}

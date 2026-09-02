<?php

namespace Tests\Concerns;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 通常DBと sales DB の2接続を同時に使うテスト用トレイト。
 * migrate:fresh はデフォルト接続のテーブルしか drop しないため、
 * sales接続に前回実行分のテーブルが残っていると CREATE TABLE が
 * 「already exists」で失敗する。事前に sales接続のテーブルだけを
 * 明示的に drop してから通常の migrate:fresh に委譲することで回避する。
 */
trait RefreshesSalesDatabase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    protected $connectionsToTransact = ['mysql', 'sales'];

    protected function refreshTestDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {
            Schema::connection('sales')->dropAllTables();
            DB::connection('sales')->reconnect();

            $this->artisan('migrate:fresh', $this->migrateFreshUsing());

            $this->app[Kernel::class]->setArtisan(null);

            RefreshDatabaseState::$migrated = true;
        }

        $this->beginDatabaseTransaction();
    }
}

<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    private static bool $salesConnectionPrepared = false;

    /**
     * setUp() より前に $this->app を参照すると、まだコンテナが構築されておらず
     * config() が正しい接続設定を返さない（sales dropが無視される）。
     * refreshApplication() 直後はアプリケーションが構築済みかつ RefreshDatabase の
     * migrate 処理より前なので、ここでフックする。
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $this->prepareSalesConnectionOnce();
    }

    /**
     * sales DB migration は通常DBとは別接続のため、RefreshDatabase の migrate:fresh は
     * デフォルト接続（mysql）のテーブルしか drop しない。前回のテストプロセスが sales 接続に
     * テーブルを残したままだと、以後の全 RefreshDatabase テストが「already exists」で失敗する
     * （sales と無関係なテストも含めて既存テストスイート全体に波及する）。
     * テストプロセス開始時に一度だけ sales 接続をクリアしておくことでこれを防ぐ。
     */
    private function prepareSalesConnectionOnce(): void
    {
        if (self::$salesConnectionPrepared) {
            return;
        }
        self::$salesConnectionPrepared = true;

        if (config('database.connections.sales.database')) {
            Schema::connection('sales')->dropAllTables();
        }
    }
}

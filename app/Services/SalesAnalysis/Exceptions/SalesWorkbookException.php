<?php

namespace App\Services\SalesAnalysis\Exceptions;

use Exception;

/**
 * xlsx読取時のファイル構造エラー（複数シート、見出し不一致、破損ファイル等）。
 * メッセージには得意先名・品名・金額等の機密値を含めないこと。
 */
class SalesWorkbookException extends Exception
{
}

<?php

namespace App\Services\SalesAnalysis\Exceptions;

use Exception;

/**
 * 取込確定時のエラー（プレビュー期限切れ、二重取込等）。
 * メッセージには得意先名・品名・金額等の機密値を含めないこと。
 */
class SalesImportConfirmException extends Exception
{
}

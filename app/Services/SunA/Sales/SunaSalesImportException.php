<?php

namespace App\Services\SunA\Sales;

/**
 * 売上レポートの取込で、続行できない問題（形式が違う・ファイルが読めない・確認の期限切れ など）。
 * メッセージは、そのまま利用者に見せられる文言にする（内容の個別データ＝得意先名や金額は入れない）。
 */
class SunaSalesImportException extends \RuntimeException
{
}

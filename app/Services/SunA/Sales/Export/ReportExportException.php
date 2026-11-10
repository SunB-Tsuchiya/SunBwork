<?php

namespace App\Services\SunA\Sales\Export;

/** Excel出力できない条件（データが無い・比べられない など）。画面にそのまま出せる日本語のメッセージを持つ */
class ReportExportException extends \RuntimeException
{
}

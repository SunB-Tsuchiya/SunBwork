<?php

namespace App\Services\SunA\Sales;

/** 売上レポートの会社が未設定のまま、データへアクセスしようとした（fail-closed） */
class SunaCompanyContextMissingException extends \LogicException
{
}

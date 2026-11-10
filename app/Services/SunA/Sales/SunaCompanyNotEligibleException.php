<?php

namespace App\Services\SunA\Sales;

/** サンエー印刷以外の会社（サン・ブレーン等）を、売上レポートの会社に設定しようとした */
class SunaCompanyNotEligibleException extends \DomainException
{
}

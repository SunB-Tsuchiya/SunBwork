<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Department;
use App\Models\SunA\Sales\ReportPermission;
use App\Models\User;
use App\Support\SunA\Area;
use Illuminate\Console\Command;

/**
 * サンエー印刷の権限体系の変更（SUNAROLE_PLAN1.md）の反映前確認。読み取り専用（何も書き換えない）。
 * 新しいルール（所属部署のエリア）で、いままで入れていた総務・経理・営業のエリアに入れなくなる人を一覧にする。
 *   使い方: php artisan suna:area-report
 * 「部署のエリア」が未設定の部署も一覧にする（部署管理で設定してから反映する）。
 */
class SunaAreaReport extends Command
{
    protected $signature = 'suna:area-report';

    protected $description = 'サンエー印刷: 新しい権限のルール（部署のエリア）で、入れなくなる人と、エリア未設定の部署を一覧にする（読み取り専用）';

    public function handle(): int
    {
        $companies = Company::where('company_type', 'general')->get();
        if ($companies->isEmpty()) {
            $this->info('サンエー印刷（一般会社）がありません。');

            return self::SUCCESS;
        }

        foreach ($companies as $company) {
            $this->line("=== {$company->name}（id={$company->id}）===");

            $departments = Department::where('company_id', $company->id)->orderBy('sort_order')->orderBy('id')->get();
            $this->line('[部署とエリア]');
            foreach ($departments as $d) {
                $this->line(sprintf('  %-20s エリア: %s%s', $d->name, Area::label($d->area), $d->area === null ? '  ← 未設定（ユーザーの画面だけになります）' : ''));
            }

            $this->line('[新しいルールで入れなくなるエリア]');
            $lost = 0;
            $users = User::where('company_id', $company->id)->orderBy('department_id')->orderBy('id')->get();
            $deptById = $departments->keyBy('id');
            foreach ($users as $u) {
                if ($u->isSuperAdmin()) {
                    continue;
                }
                $old = $this->oldAreas($u, $deptById->get($u->department_id));
                $new = $this->newAreas($u);
                $gone = array_values(array_diff($old, $new));
                if ($gone !== []) {
                    $lost++;
                    $this->line(sprintf(
                        '  %-16s ロール: %-12s 部署: %-14s いま: %s → 新ルール: %s  （入れなくなる: %s）',
                        $u->name, $u->user_role, $deptById->get($u->department_id)?->name ?? '（なし）',
                        $old === [] ? 'なし' : implode('・', array_map([Area::class, 'label'], $old)),
                        $new === [] ? 'なし' : implode('・', array_map([Area::class, 'label'], $new)),
                        implode('・', array_map([Area::class, 'label'], $gone)),
                    ));
                }
            }
            $this->line($lost === 0 ? '  （該当なし）' : "  合計 {$lost} 人");
        }

        return self::SUCCESS;
    }

    /** 旧ルールで入れるエリア（Clerk 系の画面は総務/経理、営業は売上レポートの許可） */
    private function oldAreas(User $u, ?Department $dept): array
    {
        $areas = [];
        if ($u->isClerk()) {
            $areas[] = str_contains((string) $dept?->name, '経理') ? Area::KEIRI : Area::SOUMU;
        } elseif ($u->isAdmin()) {
            $areas[] = Area::SOUMU;
            $areas[] = Area::KEIRI;
        } elseif ($u->isLeader() && $u->isDepartmentLeader()) {
            $areas[] = Area::SOUMU;
        }
        $perm = ReportPermission::where('user_id', $u->id)->first();
        if ($perm?->enabled) {
            $areas[] = Area::SALES;
        }

        return $areas;
    }

    /** 新ルールで入れるエリア（所属部署のエリア） */
    private function newAreas(User $u): array
    {
        $area = Area::of($u);

        return $area === null ? [] : [$area];
    }
}

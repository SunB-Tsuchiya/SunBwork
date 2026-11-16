<?php
namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\DepartmentWorkDisplaySetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepartmentWorkDisplaySettings
{
    public function company(User $actor, ?int $context): ?int
    {
        abort_unless(in_array($actor->user_role, ['admin','superadmin'], true), 403);
        $id = $actor->isSuperAdmin() ? $context : $actor->company_id;
        if (!$id) return null;
        abort_unless(Company::whereKey($id)->where('company_type','sunbrain')->exists(), 403);
        return (int) $id;
    }

    private function candidates(int $company): Builder
    {
        return User::where('company_id',$company)->whereHas('department',fn ($q) => $q
            ->where('company_id',$company)->where('code','INFO'))
            ->where('is_ghost',false)->whereNull('ghost_owner_id');
    }

    public function snapshot(int $company, bool $lock = false): array
    {
        $query = $this->candidates($company)->ordered()->orderBy('id');
        $users = ($lock ? $query->lockForUpdate() : $query)->get();
        $settings = DepartmentWorkDisplaySetting::where('company_id',$company)
            ->whereIn('user_id',$users->modelKeys())->get()->keyBy(fn ($r) => $r->user_id.':'.$r->department_id);
        $rows = $users->map(function ($u) use ($settings) {
            $setting = $settings->get($u->id.':'.$u->department_id);
            return ['id'=>(int)$u->id,'name'=>$u->name,'department_id'=>(int)$u->department_id,
                'employment_type'=>$u->employment_type ?? 'regular',
                'show_schedule'=>$setting?->show_schedule ?? true, 'show_records'=>$setting?->show_records ?? true];
        })->all();
        // Fingerprint the returned state, including target membership and unchanged audit data.
        $audit = $users->map(function ($u) use ($settings) {
            $r=$settings->get($u->id.':'.$u->department_id);
            return [$u->id,$u->department_id,$r?->id,$r?->changed_by,$r?->getRawOriginal('updated_at')];
        })->all();
        return ['users'=>$rows,'version'=>hash('sha256',json_encode([$rows,$audit],JSON_THROW_ON_ERROR))];
    }

    public function save(User $actor, int $company, array $items, string $version): array
    {
        abort_unless($this->company($actor, $actor->isSuperAdmin() ? $company : null) === $company, 403);
        return DB::transaction(function () use ($actor,$company,$items,$version) {
            Company::whereKey($company)->lockForUpdate()->firstOrFail();
            $snapshot=$this->snapshot($company,true);
            abort_unless(hash_equals($snapshot['version'],$version),409,'ほかの画面で設定または対象者が更新されています。再読み込みしてください。');
            $allowed=collect($snapshot['users'])->keyBy('id');
            foreach ($items as $item) {
                if (!$allowed->has($item['user_id'])) throw ValidationException::withMessages(['items'=>'情報出版の対象者だけを指定してください。']);
            }
            $changed=0;
            foreach ($items as $item) {
                $user=$allowed->get($item['user_id']);
                if ($user['show_schedule']===(bool)$item['show_schedule'] && $user['show_records']===(bool)$item['show_records']) continue;
                $row=DepartmentWorkDisplaySetting::firstOrNew(['user_id'=>$user['id'],'company_id'=>$company,'department_id'=>$user['department_id']]);
                $row->show_schedule=(bool)$item['show_schedule']; $row->show_records=(bool)$item['show_records'];
                $row->changed_by=$actor->id; $row->save(); $changed++;
            }
            return $this->snapshot($company)+['changed'=>$changed];
        },3);
    }

    /** Only shared employee lists use this filter; management analysis and personal input do not. */
    public function visible(Builder $users, string $field): Builder
    {
        if (!in_array($field,['show_schedule','show_records'],true)) throw new \InvalidArgumentException('Unknown display field');
        return $users->whereNotExists(fn ($q) => $q->selectRaw('1')->from('department_work_display_settings as display_setting')
            ->whereColumn('display_setting.user_id','users.id')->whereColumn('display_setting.company_id','users.company_id')
            ->whereColumn('display_setting.department_id','users.department_id')->where('display_setting.'.$field,false));
    }
}

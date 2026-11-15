<?php

namespace App\Services;

use App\Models\PersonalWorkEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkJobInput
{
    public function version(PersonalWorkEntry $entry): string
    {
        $fields = ['work_date','work_category','activity_type','title','project_name_text','plan_slot',
            'planned_start_time','planned_end_time','actual_quantity','actual_unit','actual_minutes',
            'result_note','entry_visibility','project_job_id','broad_project_id','diary_id'];
        $values = [];
        foreach ($fields as $field) $values[$field] = $entry->getRawOriginal($field);
        return hash('sha256', json_encode($values, JSON_THROW_ON_ERROR));
    }

    public function create(Request $request, array $data, ?array $source = null): array
    {
        $token = $request->validate(['request_token' => ['required', 'uuid']])['request_token'];
        $user = $request->user();
        ksort($data);
        $hash = hash('sha256', json_encode([$data, $source], JSON_THROW_ON_ERROR));
        $key = hash('sha256', $user->id.':'.$token);
        return DB::transaction(function () use ($user, $data, $hash, $key) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $receipt = DB::table('work_job_creation_receipts')->where('request_key', $key)->first();
            if ($receipt) {
                abort_unless((int) $receipt->user_id === (int) $user->id && hash_equals($receipt->payload_hash, $hash), 422, '同じ保存キーの内容が変わっています。画面を開き直してください。');
                abort_unless($receipt->entry_id, 410, '作成済みの仕事は削除されています。');
                $entry = PersonalWorkEntry::whereKey($receipt->entry_id)->where('user_id', $user->id)
                    ->where('company_id', $user->company_id)->where('department_id', $user->department_id)->first();
                abort_unless($entry, 410, '作成済みの仕事は参照できません。');
                return ['id' => $entry->id, 'replayed' => true];
            }
            $entry = PersonalWorkEntry::create($data + ['user_id' => $user->id, 'company_id' => $user->company_id, 'department_id' => $user->department_id]);
            DB::table('work_job_creation_receipts')->insert(['request_key'=>$key, 'user_id'=>$user->id,
                'payload_hash'=>$hash, 'entry_id'=>$entry->id, 'created_at'=>now(), 'updated_at'=>now()]);
            return ['id'=>$entry->id, 'replayed'=>false];
        }, 3);
    }
}

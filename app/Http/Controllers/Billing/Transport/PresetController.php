<?php

namespace App\Http\Controllers\Billing\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\Transport\SaveExpenseRequest;
use App\Models\TransportExpenseItem;
use App\Models\TransportPreset;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** 交通費の「よく使う行き先」の登録・修正・削除（本人の分だけ） */
class PresetController extends Controller
{
    public function store(Request $request)
    {
        $data = $this->validated($request);
        $user = $request->user();

        // 同じ行先・用件・区間は 1 件にまとめる（登録し直すと、金額などを上書き）
        $preset = TransportPreset::where('user_id', $user->id)
            ->where('destination', $data['destination'])->where('purpose', $data['purpose'])
            ->where('station_from', $data['station_from'])->where('station_to', $data['station_to'])
            ->first();

        if (!$preset) {
            if (TransportPreset::where('user_id', $user->id)->count() >= TransportPreset::MAX_PER_USER) {
                return back()->withErrors(['preset' => '登録できるのは ' . TransportPreset::MAX_PER_USER . ' 件までです。使わないものを削除してください。']);
            }
            $preset = new TransportPreset(['user_id' => $user->id]);
        }
        $preset->fill($data)->save();

        return back()->with('success', '「' . $data['destination'] . '」を登録しました。');
    }

    public function update(Request $request, TransportPreset $preset)
    {
        abort_unless($preset->user_id === $request->user()->id, 403);
        $preset->fill($this->validated($request))->save();

        return back()->with('success', '登録を更新しました。');
    }

    public function destroy(Request $request, TransportPreset $preset)
    {
        abort_unless($preset->user_id === $request->user()->id, 403);
        $preset->delete();

        return back()->with('success', '登録を削除しました。');
    }

    /** 画面で整形した値を、サーバーでも同じ規則で整える（申請の明細と同じ） */
    private function validated(Request $request): array
    {
        $request->merge([
            'destination'  => SaveExpenseRequest::zenkaku($request->input('destination')),
            'purpose_text' => SaveExpenseRequest::zenkaku($request->input('purpose_text')),
            'station_from' => SaveExpenseRequest::zenkaku($request->input('station_from')),
            'station_to'   => SaveExpenseRequest::zenkaku($request->input('station_to')),
            'base_amount'  => SaveExpenseRequest::yen($request->input('base_amount', 0)),
            'auto_double'  => filter_var($request->input('auto_double', false), FILTER_VALIDATE_BOOLEAN),
        ]);

        $data = $request->validate([
            'destination'  => ['required', 'string', 'max:100'],
            'purpose'      => ['required', Rule::in(array_keys(TransportExpenseItem::PURPOSE_LABELS))],
            'purpose_text' => ['nullable', 'string', 'max:100'],
            'station_from' => ['nullable', 'string', 'max:100'],
            'station_to'   => ['nullable', 'string', 'max:100'],
            'fare_type'    => ['required', 'in:ic,ticket'],
            'base_amount'  => ['required', 'integer', 'min:0', 'max:9999999'],
            'auto_double'  => ['boolean'],
        ], [
            'destination.required' => '行先を入力してから登録してください。',
            'base_amount.integer'  => '金額は数字で入力してください。',
        ]);

        // ×2 は用件が往復のときだけ有効
        $data['auto_double'] = $data['auto_double'] && $data['purpose'] === 'round_trip';
        $data['purpose_text'] = $data['purpose'] === 'other' ? ($data['purpose_text'] ?? null) : null;
        $data['station_from'] = $data['station_from'] ?? '';
        $data['station_to'] = $data['station_to'] ?? '';

        return $data;
    }
}

<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PositionTitle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PositionTitleController extends Controller
{
    public function index()
    {
        $grouped = PositionTitle::orderBy('sort_order')->get()->groupBy('applicable_role');

        return Inertia::render('SuperAdmin/PositionTitles/Index', [
            'adminTitles'  => $grouped->get('admin',  collect())->values(),
            'leaderTitles' => $grouped->get('leader', collect())->values(),
        ]);
    }

    public function edit()
    {
        $grouped = PositionTitle::orderBy('sort_order')->get()->groupBy('applicable_role');

        return Inertia::render('SuperAdmin/PositionTitles/Edit', [
            'adminTitles'  => $grouped->get('admin',  collect())->values(),
            'leaderTitles' => $grouped->get('leader', collect())->values(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'titles'         => 'required|array',
            'titles.*.name'            => 'required|string|max:100',
            'titles.*.applicable_role' => 'required|in:admin,leader',
            'titles.*.sort_order'      => 'required|integer|min:0',
            'deleted_ids'    => 'nullable|array',
            'deleted_ids.*'  => 'integer|exists:position_titles,id',
        ]);

        if (!empty($request->deleted_ids)) {
            PositionTitle::whereIn('id', $request->deleted_ids)->delete();
        }

        foreach ($request->titles as $data) {
            if (!empty($data['id'])) {
                PositionTitle::where('id', $data['id'])->update([
                    'name'            => $data['name'],
                    'applicable_role' => $data['applicable_role'],
                    'sort_order'      => (int) $data['sort_order'],
                ]);
            } else {
                PositionTitle::create([
                    'name'            => $data['name'],
                    'applicable_role' => $data['applicable_role'],
                    'sort_order'      => (int) $data['sort_order'],
                ]);
            }
        }

        return redirect()->route('superadmin.position_titles.index')
            ->with('success', '役職称号を保存しました。');
    }
}

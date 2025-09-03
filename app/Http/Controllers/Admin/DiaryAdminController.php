<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Diary;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class DiaryAdminController extends Controller
{
    /**
     * Show diaries for admins, grouped by department for the admin's company.
     */
    public function index(Request $request)
    {
        $admin = Auth::user();
        $companyId = $admin->company_id;

        $date = $request->input('date', now()->toDateString());

        // load users in company with their departments and diaries for the date
        $users = User::with('department')
            ->where('company_id', $companyId)
            ->get();

        $userIds = $users->pluck('id')->toArray();

        $diaries = Diary::whereIn('user_id', $userIds)
            ->whereDate('date', $date)
            ->with('user')
            ->get()
            ->groupBy(function ($d) {
                return $d->user->department ? $d->user->department->name : '未所属';
            });

        // collect all read_by ids so we can resolve names in one query
        $allReadIds = [];
        foreach ($diaries as $list) {
            foreach ($list as $d) {
                if (!empty($d->read_by) && is_array($d->read_by)) {
                    $allReadIds = array_merge($allReadIds, $d->read_by);
                }
            }
        }
        $allReadIds = array_values(array_unique($allReadIds));

        $namesMap = [];
        if (!empty($allReadIds)) {
            $namesMap = User::whereIn('id', $allReadIds)->pluck('name', 'id')->toArray();
        }

        // prepare departments => list of rows
        $departments = [];
        foreach ($diaries as $deptName => $list) {
            $departments[] = [
                'department' => $deptName,
                'diaries' => $list->map(function ($d) use ($namesMap) {
                    $readBy = $d->read_by ?? [];
                    $readByNames = array_map(function ($id) use ($namesMap) {
                        return $namesMap[$id] ?? ('ID:' . $id);
                    }, is_array($readBy) ? $readBy : []);

                    return [
                        'id' => $d->id,
                        'user_id' => $d->user_id,
                        'name' => $d->user->name,
                        'description' => mb_substr(strip_tags($d->content ?? ''), 0, 20),
                        'date' => $d->date->toDateString(),
                        'read_by' => $readBy,
                        'read_by_names' => $readByNames,
                    ];
                })->values(),
            ];
        }

        return Inertia::render('Admin/Diaries/Index', [
            'departments' => $departments,
            'date' => $date,
        ]);
    }

    /**
     * Show a diary (read-only)
     */
    public function show(Diary $diary)
    {
        $this->authorize('view', $diary);
        $diary->load('user');

        $readBy = $diary->read_by ?? [];
        $readByNames = [];
        if (!empty($readBy) && is_array($readBy)) {
            $names = User::whereIn('id', $readBy)->pluck('name', 'id')->toArray();
            $readByNames = array_map(function ($id) use ($names) {
                return $names[$id] ?? ('ID:' . $id);
            }, $readBy);
        }

        $diaryArray = $diary->toArray();
        $diaryArray['read_by_names'] = $readByNames;

        return Inertia::render('Admin/Diaries/Show', [
            'diary' => $diaryArray,
        ]);
    }

    /**
     * Mark as read and optionally add admin comment.
     */
    public function markRead(Request $request, Diary $diary)
    {
        $this->authorize('view', $diary);
        $admin = Auth::user();

        $readBy = $diary->read_by ?? [];
        if (!in_array($admin->id, $readBy)) {
            $readBy[] = $admin->id;
        }

        $adminComments = $diary->admin_comments ?? [];
        if ($request->filled('comment')) {
            $adminComments[] = [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'comment' => mb_substr($request->input('comment'), 0, 1000),
                'created_at' => now()->toDateTimeString(),
            ];
        }

        $diary->read_by = array_values($readBy);
        $diary->admin_comments = $adminComments;
        $diary->save();

        return redirect()->back()->with('success', '既読/コメントを保存しました');
    }
}

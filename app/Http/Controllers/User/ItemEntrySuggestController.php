<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use Illuminate\Http\Request;

class ItemEntrySuggestController extends Controller
{
    public function suggestions(Request $request, ProjectJob $projectJob)
    {
        $authUser = $request->user();

        $isMember = $projectJob->user_id === $authUser->id
            || $projectJob->coordinators()->where('users.id', $authUser->id)->exists()
            || $projectJob->teamMembers()->where('user_id', $authUser->id)->exists();
        $isAdmin = in_array($authUser->user_role, ['admin', 'superadmin']);
        abort_unless($isMember || $isAdmin, 403);

        $q = $request->query('q', '');
        $entries = $projectJob->itemEntries()
            ->when($q, fn($query) => $query->where('name', 'like', "%{$q}%"))
            ->orderBy('sort_order')
            ->limit(30)
            ->pluck('name');

        return response()->json(['suggestions' => $entries]);
    }
}

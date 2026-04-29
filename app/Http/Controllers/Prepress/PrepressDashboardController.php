<?php

namespace App\Http\Controllers\Prepress;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PrepressDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->load('department');

        $this->authorizePrepress($user);

        return inertia('Prepress/Dashboard', [
            'user' => $user,
        ]);
    }

    protected function authorizePrepress($user): void
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return;
        }
        if (!$user->department || $user->department->name !== '製版') {
            abort(403, 'Prepress エリアは製版部署のみアクセスできます。');
        }
    }
}

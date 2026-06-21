<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class GuideController extends Controller
{
    public function index()
    {
        return Inertia::render('Guide/Index');
    }

    public function user()
    {
        return Inertia::render('Guide/User');
    }

    public function coordinator()
    {
        $role = Auth::user()->user_role;
        if (! in_array($role, ['coordinator', 'leader', 'admin', 'superadmin'])) {
            abort(403);
        }

        return Inertia::render('Guide/Coordinator');
    }

    public function leader()
    {
        $role = Auth::user()->user_role;
        if (! in_array($role, ['leader', 'admin', 'superadmin'])) {
            abort(403);
        }

        return Inertia::render('Guide/Leader');
    }

    public function admin()
    {
        $role = Auth::user()->user_role;
        if (! in_array($role, ['admin', 'superadmin'])) {
            abort(403);
        }

        return Inertia::render('Guide/Admin');
    }

    public function proofCoordinator()
    {
        $role = Auth::user()->user_role;
        if (! in_array($role, ['proof_coordinator', 'leader', 'admin', 'superadmin'])) {
            abort(403);
        }

        return Inertia::render('Guide/ProofCoordinator');
    }

    public function schedule()
    {
        return Inertia::render('Guide/Schedule');
    }
}

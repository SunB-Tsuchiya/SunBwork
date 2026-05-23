<?php

namespace App\Http\Controllers;

use App\Models\Changelog;
use Inertia\Inertia;

class ChangelogController extends Controller
{
    public function index()
    {
        $changelogs = Changelog::orderBy('released_at', 'desc')
            ->get(['id', 'version', 'title', 'released_at', 'summary']);

        return Inertia::render('Changelogs/Index', [
            'changelogs' => $changelogs,
        ]);
    }

    public function show(Changelog $changelog)
    {
        return Inertia::render('Changelogs/Show', [
            'changelog' => $changelog,
        ]);
    }
}

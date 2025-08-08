<?php

namespace App\Http\Controllers;

use App\Models\Diary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DiaryController extends Controller
{
    public function create(Request $request)
    {
        // date param optional
        $date = $request->query('date', now()->toDateString());
        return Inertia::render('Diaries/Create', [
            'date' => $date,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date',
            'content' => 'required|string',
        ]);
        $data['user_id'] = Auth::id();
        $diary = Diary::create($data);
        return redirect()->route('diaries.show', $diary->id);
    }

    public function show(Diary $diary)
    {
        $this->authorize('view', $diary);
        return Inertia::render('Diaries/Show', [
            'diary' => $diary,
        ]);
    }

    public function edit(Diary $diary)
    {
        $this->authorize('update', $diary);
        return Inertia::render('Diaries/Edit', [
            'diary' => $diary,
        ]);
    }

    public function update(Request $request, Diary $diary)
    {
        $this->authorize('update', $diary);
        $data = $request->validate([
            'content' => 'required|string',
        ]);
        $diary->update($data);
        return redirect()->route('diaries.show', $diary->id);
    }

    public function destroy(Diary $diary)
    {
        $this->authorize('delete', $diary);
        $diary->delete();
        return redirect()->route('dashboard');
    }
}

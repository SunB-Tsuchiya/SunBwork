<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiaryComment;
use Illuminate\Support\Facades\Auth;

class DiaryCommentController extends Controller
{
    /**
     * Update a diary comment if the current user is the author.
     */
    public function update(Request $request, DiaryComment $comment)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        // Only allow owners to edit their comments
        if (intval($comment->user_id) !== intval($user->id)) {
            abort(403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $comment->update(['comment' => $validated['comment']]);

        return response()->json([
            'success' => true,
            'comment' => $comment->only(['id', 'comment', 'user_id', 'user_name', 'created_at', 'updated_at']),
        ], 200);
    }

    /**
     * Delete a diary comment if the current user is the author.
     */
    public function destroy(Request $request, DiaryComment $comment)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        // Only allow owners to delete their comments
        if (intval($comment->user_id) !== intval($user->id)) {
            abort(403);
        }

        $comment->delete();

        return response()->json(['success' => true], 200);
    }
}

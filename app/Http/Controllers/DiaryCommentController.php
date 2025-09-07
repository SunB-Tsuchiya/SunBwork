<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DiaryComment;
use Illuminate\Support\Facades\Auth;

class DiaryCommentController extends Controller
{
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

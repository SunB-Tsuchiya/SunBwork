<?php

namespace App\Http\Controllers\Clerk;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesContextCompany;
use App\Models\ClerkWeekPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClerkWeekPostController extends Controller
{
    use ResolvesContextCompany;

    public function index(Request $request)
    {
        $request->validate([
            'year' => 'required|integer',
            'week' => 'required|integer|min:1|max:53',
        ]);

        $posts = ClerkWeekPost::where('company_id', $this->companyId())
            ->where('year', $request->year)
            ->where('week', $request->week)
            ->with('user:id,name,user_role')
            ->orderBy('created_at')
            ->get();

        return response()->json($posts->map(fn($p) => [
            'id'         => $p->id,
            'body'       => $p->body,
            'parent_id'  => $p->parent_id,
            'user_id'    => $p->user_id,
            'user_name'  => $p->user?->name,
            'user_role'  => $p->user?->user_role,
            'created_at' => $p->created_at?->format('Y-m-d H:i'),
        ]));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'year'      => 'required|integer',
            'week'      => 'required|integer|min:1|max:53',
            'body'      => 'required|string|max:2000',
            'parent_id' => 'nullable|integer|exists:clerk_week_posts,id',
        ]);

        $post = ClerkWeekPost::create(array_merge($validated, [
            'company_id' => $this->companyId(),
            'user_id'    => Auth::id(),
        ]));

        $post->load('user:id,name,user_role');

        return response()->json([
            'id'         => $post->id,
            'body'       => $post->body,
            'parent_id'  => $post->parent_id,
            'user_id'    => $post->user_id,
            'user_name'  => $post->user?->name,
            'user_role'  => $post->user?->user_role,
            'created_at' => $post->created_at?->format('Y-m-d H:i'),
        ], 201);
    }

    public function destroy(ClerkWeekPost $post)
    {
        abort_unless($post->company_id === $this->companyId(), 404);

        $post->delete();

        return response()->noContent();
    }

    private function companyId(): int
    {
        return $this->contextCompanyId() ?? Auth::user()->company_id;
    }
}

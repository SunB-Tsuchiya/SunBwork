<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Team;
use App\Models\TeamBoard;
use App\Models\TeamBoardCard;
use App\Services\AttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TeamBoardCardController extends Controller
{
    public function show(Team $team, TeamBoardCard $card)
    {
        app(TeamRoomController::class)->assertMember($team);
        $board = TeamBoard::where('team_id', $team->id)->firstOrFail();
        abort_unless($card->team_board_id === $board->id, 404);

        $card->load('column', 'creator:id,name');
        $board->load('columns');

        return Inertia::render('TeamRoom/Board/CardShow', [
            'team'  => $team->load('department'),
            'board' => $board,
            'card'  => $card,
        ]);
    }

    public function edit(Team $team, TeamBoardCard $card)
    {
        app(TeamRoomController::class)->assertMember($team);
        $board = TeamBoard::where('team_id', $team->id)->firstOrFail();
        abort_unless($card->team_board_id === $board->id, 404);

        $card->load('column', 'creator:id,name');
        $board->load('columns');

        return Inertia::render('TeamRoom/Board/CardEdit', [
            'team'  => $team->load('department'),
            'board' => $board,
            'card'  => $card,
        ]);
    }

    public function store(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $board = TeamBoard::where('team_id', $team->id)->firstOrFail();

        $validated = $request->validate([
            'team_board_column_id' => 'required|integer|exists:team_board_columns,id',
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
        ]);

        $maxOrder = TeamBoardCard::where('team_board_column_id', $validated['team_board_column_id'])
            ->max('sort_order') ?? -1;

        $card = TeamBoardCard::create([
            'team_board_id'        => $board->id,
            'team_board_column_id' => $validated['team_board_column_id'],
            'title'                => $validated['title'],
            'description'          => $validated['description'] ?? null,
            'sort_order'           => $maxOrder + 1,
            'created_by'           => Auth::id(),
        ]);

        if ($request->hasFile('files')) {
            $svc = new AttachmentService();
            foreach ($request->file('files') as $file) {
                try {
                    $svc->storeUploadedFile($file, $card, Auth::id());
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('TeamBoardCard attachment failed: ' . $e->getMessage());
                }
            }
        }

        $card->load('attachments', 'creator:id,name');

        return response()->json($card, 201);
    }

    public function update(Request $request, Team $team, TeamBoardCard $card)
    {
        app(TeamRoomController::class)->assertMember($team);

        $board = TeamBoard::where('team_id', $team->id)->firstOrFail();
        abort_unless($card->team_board_id === $board->id, 404);

        $validated = $request->validate([
            'team_board_column_id' => 'nullable|integer|exists:team_board_columns,id',
            'title'                => 'nullable|string|max:255',
            'description'          => 'nullable|string',
            'sort_order'           => 'nullable|integer',
        ]);

        $data = array_filter($validated, fn($v) => $v !== null);
        // description は null（空クリア）を許可
        if (array_key_exists('description', $validated)) {
            $data['description'] = $validated['description'];
        }
        $card->update($data);

        $card->load('attachments', 'creator:id,name', 'column:id,name,color');

        if ($request->header('X-Inertia')) {
            return redirect()->route('team-rooms.board.cards.show', ['team' => $team->id, 'card' => $card->id]);
        }

        return response()->json($card);
    }

    public function reorder(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $request->validate([
            'ids'   => 'required|array',
            'ids.*' => 'integer',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->ids as $i => $id) {
                TeamBoardCard::where('id', $id)->update(['sort_order' => $i]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function updateColor(Request $request, Team $team, TeamBoardCard $card)
    {
        app(TeamRoomController::class)->assertMember($team);

        $board = TeamBoard::where('team_id', $team->id)->firstOrFail();
        abort_unless($card->team_board_id === $board->id, 404);

        $validated = $request->validate([
            'card_color' => 'nullable|string|max:30',
        ]);

        $card->update(['card_color' => $validated['card_color'] ?? null]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, Team $team, TeamBoardCard $card)
    {
        app(TeamRoomController::class)->assertMember($team);

        $board = TeamBoard::where('team_id', $team->id)->firstOrFail();
        abort_unless($card->team_board_id === $board->id, 404);

        $card->delete();

        if ($request->header('X-Inertia')) {
            return redirect()->route('team-rooms.show', ['team' => $team->id]);
        }

        return response()->noContent();
    }
}

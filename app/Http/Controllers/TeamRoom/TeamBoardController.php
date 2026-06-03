<?php

namespace App\Http\Controllers\TeamRoom;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamBoard;
use App\Models\TeamBoardColumn;
use Illuminate\Http\Request;

class TeamBoardController extends Controller
{
    public function store(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        if (TeamBoard::where('team_id', $team->id)->exists()) {
            return response()->json(['message' => 'すでにボードが存在します'], 422);
        }

        $board = TeamBoard::create([
            'team_id' => $team->id,
            'name'    => 'プロジェクトボード',
        ]);

        $defaults = [
            ['name' => '予定',   'color' => 'yellow', 'sort_order' => 0],
            ['name' => '作業中', 'color' => 'blue',   'sort_order' => 1],
            ['name' => '完了',   'color' => 'green',  'sort_order' => 2],
        ];

        foreach ($defaults as $col) {
            TeamBoardColumn::create(array_merge($col, ['team_board_id' => $board->id]));
        }

        $board->load('columns');

        return response()->json($board, 201);
    }

    public function updateColumns(Request $request, Team $team)
    {
        app(TeamRoomController::class)->assertMember($team);

        $board = TeamBoard::where('team_id', $team->id)->firstOrFail();

        $request->validate([
            'columns'              => 'required|array',
            'columns.*.id'         => 'nullable|integer',
            'columns.*.name'       => 'required|string|max:100',
            'columns.*.color'      => 'required|string|max:50',
            'columns.*.sort_order' => 'integer',
        ]);

        $incoming = collect($request->columns);
        $incomingIds = $incoming->pluck('id')->filter()->values();

        // 削除: incoming に含まれない既存カラムを削除（カードもカスケード削除）
        TeamBoardColumn::where('team_board_id', $board->id)
            ->whereNotIn('id', $incomingIds)
            ->delete();

        // 更新 or 新規作成
        foreach ($incoming as $i => $col) {
            if (! empty($col['id'])) {
                TeamBoardColumn::where('id', $col['id'])
                    ->where('team_board_id', $board->id)
                    ->update([
                        'name'       => $col['name'],
                        'color'      => $col['color'],
                        'sort_order' => $i,
                    ]);
            } else {
                TeamBoardColumn::create([
                    'team_board_id' => $board->id,
                    'name'          => $col['name'],
                    'color'         => $col['color'],
                    'sort_order'    => $i,
                ]);
            }
        }

        $board->load('columns.cards');

        return response()->json($board);
    }
}

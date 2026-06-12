<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\MeetingRoom;
use App\Models\RoomReservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleRoomReservationController extends Controller
{
    public function store(Request $request, MeetingRoom $room)
    {
        $validated = $request->validate([
            'title'    => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at'   => 'required|date|after:starts_at',
            'event_id' => 'nullable|exists:events,id',
            'notes'    => 'nullable|string',
        ]);

        $this->checkConflict($room->id, $validated['starts_at'], $validated['ends_at']);

        $reservation = RoomReservation::create([
            'meeting_room_id' => $room->id,
            'user_id'         => Auth::id(),
            ...$validated,
        ]);

        return response()->json($reservation->load('meetingRoom:id,name,color'), 201);
    }

    public function update(Request $request, RoomReservation $reservation)
    {
        $this->authorizeActor($reservation);

        $validated = $request->validate([
            'title'    => 'sometimes|string|max:255',
            'starts_at' => 'sometimes|date',
            'ends_at'   => 'sometimes|date|after:starts_at',
            'notes'    => 'nullable|string',
        ]);

        $starts = $validated['starts_at'] ?? $reservation->starts_at;
        $ends   = $validated['ends_at']   ?? $reservation->ends_at;
        $this->checkConflict($reservation->meeting_room_id, $starts, $ends, $reservation->id);

        $reservation->update($validated);

        return response()->json($reservation->load('meetingRoom:id,name,color'));
    }

    public function destroy(RoomReservation $reservation)
    {
        $this->authorizeActor($reservation);
        $reservation->delete();

        return response()->json(['ok' => true]);
    }

    private function checkConflict(int $roomId, $starts, $ends, ?int $excludeId = null): void
    {
        $query = RoomReservation::where('meeting_room_id', $roomId)
            ->where(function ($q) use ($starts, $ends) {
                $q->whereBetween('starts_at', [$starts, $ends])
                  ->orWhereBetween('ends_at', [$starts, $ends])
                  ->orWhere(fn ($q2) => $q2->where('starts_at', '<=', $starts)->where('ends_at', '>=', $ends));
            });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            abort(422, 'この時間帯は既に予約されています');
        }
    }

    private function authorizeActor(RoomReservation $reservation): void
    {
        $user = Auth::user();
        if ($reservation->user_id !== $user->id && !$user->isAdmin() && !$user->isSuperAdmin()) {
            abort(403);
        }
    }
}

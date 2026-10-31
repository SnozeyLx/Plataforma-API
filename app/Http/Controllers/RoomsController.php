<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RoomsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'only_active' => 'in:0,1',
        ]);

        $rooms = Room::select();

        if($request->has("only_active") && $request->query("only_active") == "1") {
           $rooms = $rooms->where('finished_at',null);
        }

        $rooms = $rooms->orderBy("id", "asc")->paginate();

        return [
            "success" => true,
            /** @var LengthAwarePaginator<Room> **/
            "rooms" => $rooms
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'live_url' => 'required|string',
        ]);

        if(Room::where("user_id", $request->user()->id)->where("finished_at", null)->exists()) {
            return response()->json([
                "success" => false,
                "message" => "Cannot start new room, another room still in progress"
            ], 400);
        }

        $room = Room::create([
            'user_id' => $request->user()->id,
            'live_url' =>  $request->live_url
        ]);

        return [
            "success" => true,
            /** @var Room */
            "room" => $room
        ];
    }


    public function show(Room $room) {
        return [
            'success' => true,
               /** @var Room */
            'room'    => $room
        ];
    }


    public function games(Room $room) {
        $games = Game::where("room_id", $room->id)->orderBy("id", "desc")->paginate();

        return [
            'success' => true,
            /** @var LengthAwarePaginator<Game> **/
            "games" => $games
        ];
    }

    public function finish(Request $request, Room $room) {
        if($room->user_id !== $request->user()->id) return abort(404);

        if($room->finished_at !== null) {
            return response()->json([
                "success" => false,
                "message" => "Cannot finish, room already finished"
            ], 400);
        }

        if(Game::where("room_id", $room->id)->where("finished_at",null)->exists()) {
            return response()->json([
                "success" => false,
                "message" => "Cannot finish, a game in this room still in progress"
            ], 400);
        }

        $room->update([
            "finished_at" => Carbon::now()
        ]);

        return [
            "success" => true,
            "message" => "Room finished successfuly"
        ];
    }
}


<?php

namespace App\Http\Controllers;

use App\Enums\BetStatus;
use App\Models\Bet;
use App\Models\Game;
use App\Models\Points;
use App\Models\User;
use App\Rules\CardOnGameValidation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    public function index()
    {
        $game = Game::select('*')->get();

        return [
            'success' => true,
            'game'    => $game
        ];
    }

    public function start(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'uuid' => 'required',
            'card_left' => 'required|exists:cards,id',
            'card_right' => 'required|exists:cards,id',
        ]);

        // opcional: se você quiser impedir 2 games abertos na MESMA room:
        // if (Game::where('room_id', $validated['room_id'])->whereNull('finished_at')->exists()) { ... }

        if (Game::where('user_id', $request->user()->id)->whereNull('finished_at')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A previous match is still in progress. Please end the match before starting a new one.',
            ], 455);
        }

        $game = new Game();
        $game->room_id = $validated['room_id'];
        $game->user_id = $request->user()->id;
        $game->uuid = $validated['uuid'];
        $game->card_left = $validated['card_left'];
        $game->card_right = $validated['card_right'];
        $game->save();

        return [
            'success' => true,
            'message' => 'Match created successfully.',
            'game_id' => $game->id,
            'user_id' => $game->user_id,
            'uuid' => $game->uuid,
            'card_left' => $game->card_left,
            'card_right' => $game->card_right,
        ];
    }

    public function show(Game $game)
    {
        return [
            'success' => true,
            'game'    => $game
        ];
    }

    public function setCards(Request $request, Game $game)
    {
        $validated = $request->validate([
            'card_left' => 'required|exists:cards,id',
            'card_right' => 'required|exists:cards,id',
        ]);

        if ($game->user_id !== $request->user()->id) return abort(404);

        if ($game->card_left !== null && $game->card_right !== null) {
            return [
                "success" => false,
                "message" => 'Bad request',
                "erros" => [
                    "cards" => "Cards already selected"
                ]
            ];
        }

        if ($game->card_left == null && $game->card_right == null) {
            $game->update([
                'card_left'  => $validated['card_left'],
                'card_right' => $validated['card_right'],
            ]);
        }

        return [
            'success'    => true,
            'message'    => 'Cards selected successfully.',
            'game_id'    => $game->id,
            'card_left'  => $game->card_left,
            'card_right' => $game->card_right,
        ];
    }

    public function finish(Request $request, Game $game)
    {
        $validated = $request->validate([
            'card_winner' => ['required', 'exists:cards,id', new CardOnGameValidation($game)]
        ]);

        if ($game->user_id !== $request->user()->id) return abort(404);

        $card_id = $validated["card_winner"];

        DB::transaction(function () use ($game, $card_id) {

            // ✅ 0) trava o GAME
            $lockedGame = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();

            if ($lockedGame->finished_at !== null) {
                abort(response()->json([
                    "success" => false,
                    "message" => "Cannot finish, game already finished"
                ], 409));
            }

            // ✅ 1) FECHA CEDO
            $lockedGame->update([
                "card_winner" => $card_id,
                "finished_at" => Carbon::now()
            ]);

            // ✅ 2) trava bets pendentes
            $pendingBets = Bet::where("game_id", $lockedGame->id)
                ->where("status", BetStatus::Pending)
                ->lockForUpdate()
                ->get();

            if ($pendingBets->isEmpty()) {
                return;
            }

            // participantes
            $allUserIds = $pendingBets->pluck('user_id')->unique()->values();

            // vencedoras
            $winnerBets = $pendingBets->where('card_id', $card_id);
            $winnerUserIds = $winnerBets->pluck('user_id')->unique()->values();

            $winnerUsers = User::whereIn('id', $winnerUserIds)->lockForUpdate()->get()->keyBy('id');
            $winnerBetsByUser = $winnerBets->groupBy('user_id');

            foreach ($winnerBetsByUser as $uid => $betsGroup) {
                $user = $winnerUsers->get($uid);
                if (!$user) continue;

                $sum_balance = 0.0;

                foreach ($betsGroup as $bet) {
                    $sum_balance += ((float)$bet->value) * 1.95;

                    $bet->status = BetStatus::Win;
                    $bet->save();
                }

                $user->increment('balance', $sum_balance);

                Points::create([
                    'user_id' => $user->id,
                    'points'  => 0.50,
                ]);
            }

            // losses
            $loserBetIds = $pendingBets->where('card_id', '!=', $card_id)->pluck('id')->values();
            if ($loserBetIds->isNotEmpty()) {
                Bet::whereIn('id', $loserBetIds)->update(["status" => BetStatus::Loss]);
            }

            // points participantes
            foreach ($allUserIds as $uid) {
                Points::create([
                    'user_id' => $uid,
                    'points'  => 2.00,
                ]);
            }

            // ✅ 6) AGORA SIM: salva balance_after (saldo final) no BetSnapshot
            $finalBalances = User::whereIn('id', $allUserIds)
                ->select('id', 'balance')
                ->get()
                ->keyBy('id');

            foreach ($allUserIds as $uid) {
                $u = $finalBalances->get($uid);
                if (!$u) continue;

                \App\Models\BetSnapshot::where('game_id', $lockedGame->id)
                    ->where('user_id', $uid)
                    ->update([
                        'balance_after' => round((float) $u->balance, 2),
                    ]);
            }
        }, 5);

        return [
            "success" => true,
            "message" => "Game finished successfuly"
        ];
    }
}

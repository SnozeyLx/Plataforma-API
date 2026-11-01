<?php

namespace App\Http\Controllers;

use App\Actions\Wallet\DebitAction;
use App\Exceptions\Wallet\InsufficientBalance;
use App\Models\Bet;
use App\Models\BetSnapshot;
use App\Models\Game;
use App\Models\Points;
use App\Models\User;
use App\Rules\CardOnGameValidation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class BetController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'game_id' => 'required|exists:games,id',
            'card_id' => 'required|exists:cards,id',
            'value'   => 'required|numeric',
        ]);

        $userId = $request->user()->id;

        try {
            $validatedData['value'] = round($validatedData['value'], 2);

            $bet = DB::transaction(function () use ($userId, $validatedData) {

                // ✅ trava o GAME e garante que é da ROOM informada
                $game = Game::where('id', $validatedData['game_id'])
                    ->where('room_id', $validatedData['room_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($game->finished_at !== null) {
                    abort(response()->json([
                        "success" => false,
                        "message" => 'Cant register bet, game finished',
                    ], 401));
                }

                // ✅ valida que a carta pertence ao game (com game travado)
                validator(
                    ['card_id' => $validatedData['card_id']],
                    ['card_id' => [new CardOnGameValidation($game)]]
                )->validate();

                // ✅ trava usuário para debitar com segurança
                $lockedUser = User::where('id', $userId)->lockForUpdate()->firstOrFail();

                // saldo antes
                $balanceBefore = round((float) $lockedUser->balance, 2);


                // ✅ bloqueia duplicidade (ideal: índice único no banco também)
                $existing = Bet::where('user_id', $lockedUser->id)
                    ->where('game_id', $game->id)
                    ->exists();

                if ($existing) {
                    abort(response()->json([
                        "success" => false,
                        "message" => "You already have a bet for this game."
                    ], 409));
                }

                // ✅ debita
                (new DebitAction)->handle($lockedUser, $validatedData['value']);

                $lockedUser->refresh();
                $balanceAfter = round((float) $lockedUser->balance, 2);

                // ✅ cria bet
                $bet = Bet::create([
                    'room_id' => $validatedData['room_id'],
                    'game_id' => $game->id,
                    'user_id' => $lockedUser->id,
                    'card_id' => $validatedData['card_id'],
                    'value'   => $validatedData['value'],
                ]);

                // ✅ points da aposta
                Points::create([
                    'user_id' => $lockedUser->id,
                    'points'  => round($bet->value * 0.75, 2),
                ]);

                // cria snapshot/audit
                BetSnapshot::create([
                    'bet_id'         => $bet->id,
                    'room_id'        => $bet->room_id,
                    'game_id'        => $bet->game_id,
                    'user_id'        => $bet->user_id,
                    'card_id'        => $bet->card_id,
                    'bet_value'      => $bet->value,
                    'balance_before' => $balanceBefore,
                ]);

                return $bet;
            }, 5);

            return response()->json([
                "success" => true,
                "message" => 'Bet registered successfully',
                "bet" => $bet
            ]);
        } catch (InsufficientBalance $e) {
            return response()->json([
                "success" => false,
                "message" => 'Insufficient balance, cant register bet',
            ], 402);
        } catch (QueryException $e) {
            // se você criar UNIQUE(user_id, game_id), cai aqui em concorrência
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                return response()->json([
                    "success" => false,
                    "message" => "You already have a bet for this game."
                ], 409);
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                throw $e;
            }

            return response()->json([
                "success" => false,
                "message" => 'Failed to register bet',
            ], 403);
        }
    }

    public function listBets($room_id)
    {
        $bets = Bet::where('bets.room_id', $room_id)
            ->join('games', 'bets.game_id', '=', 'games.id')
            ->join('users', 'bets.user_id', '=', 'users.id')
            ->join('cards as card_left', 'games.card_left', '=', 'card_left.id')
            ->join('cards as card_right', 'games.card_right', '=', 'card_right.id')
            ->select(
                'bets.room_id',
                'users.name as user_name',
                'users.id as user_id',
                'bets.value as bet_value',
                'bets.card_id as bet_card_id'
            )
            ->orderBy("bets.id", "desc")
            ->get();

        $firstGame = Game::where('room_id', $room_id)->first();
        if (!$firstGame) {
            return [
                'success' => false,
                'message' => 'Jogo não encontrado para esta sala.'
            ];
        }

        $cardLeftId = $firstGame->card_left;
        $cardRightId = $firstGame->card_right;
        $totalBetValue = $bets->sum('bet_value');
        $cardLeftBetValue = $bets->where('bet_card_id', $cardLeftId)->sum('bet_value');
        $cardRightBetValue = $bets->where('bet_card_id', $cardRightId)->sum('bet_value');
        $cardLeftPercentage = $totalBetValue > 0 ? ($cardLeftBetValue / $totalBetValue) * 100 : 0;
        $cardRightPercentage = $totalBetValue > 0 ? ($cardRightBetValue / $totalBetValue) * 100 : 0;

        $userBets = $bets->map(function ($bet) use ($cardLeftId, $cardRightId) {
            return [
                'user_name' => $bet->user_name,
                'user_id' => $bet->user_id,
                'bet_value' => $bet->bet_value,
                'card' => $bet->bet_card_id == $cardLeftId ? $cardLeftId : ($bet->bet_card_id == $cardRightId ? $cardRightId : 'N/A')
            ];
        });

        return [
            'success' => true,
            'percentages' => [
                'card_left' => round($cardLeftPercentage, 2) . '%',
                'card_right' => round($cardRightPercentage, 2) . '%'
            ],
            'user_bets' => $userBets
        ];
    }
}

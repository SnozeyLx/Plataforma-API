<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameRollbackController extends Controller
{
    public function rollback(Request $request, Game $game)
    {
        $data = $request->validate([
            'card_id' => 'nullable|exists:cards,id',
        ]);

        $now = Carbon::now();

        try {
            $result = DB::transaction(function () use ($request, $game, $data, $now) {

                $game = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();

                $cardFilter = $data['card_id'] ?? null;

                $eligibleStatuses = [
                    \App\Enums\BetStatus::Win,
                    \App\Enums\BetStatus::Loss,
                    \App\Enums\BetStatus::Pending,
                ];

                // usuários que têm bets elegíveis
                $users = User::whereHas('bets', function ($q) use ($game, $cardFilter, $eligibleStatuses) {
                    $q->where('game_id', $game->id)
                        ->where('room_id', $game->room_id)
                        ->whereIn('status', $eligibleStatuses);

                    if ($cardFilter) {
                        $q->where('card_id', $cardFilter);
                    }
                })
                    ->lockForUpdate()
                    ->get();

                $usersPayload = [];
                $refundedTotal = 0.0;
                $processedBets = 0;

                foreach ($users as $user) {
                    // bets elegíveis do usuário
                    $betsQ = $user->bets()
                        ->where('game_id', $game->id)
                        ->where('room_id', $game->room_id)
                        ->whereIn('status', $eligibleStatuses)
                        ->lockForUpdate();

                    if ($cardFilter) {
                        $betsQ->where('card_id', $cardFilter);
                    }

                    $bets = $betsQ->get();

                    if ($bets->isEmpty()) {
                        continue;
                    }

                    $refundByCard = []; // [card_id => refunded]
                    $userRefundTotal = 0.0;

                    foreach ($bets as $bet) {

                        // REGRA POR STATUS:
                        // Win/Loss/Pending => devolve apenas o valor apostado
                        $amount = round((float)$bet->value, 2);

                        $refundByCard[$bet->card_id] = ($refundByCard[$bet->card_id] ?? 0) + $amount;
                        $userRefundTotal += $amount;

                        // marca como cancelada
                        $bet->status = \App\Enums\BetStatus::Canceled;
                        $bet->save();

                        $processedBets++;
                    }

                    $userRefundTotal = round($userRefundTotal, 2);

                    // devolve saldo do usuário
                    $user->increment('balance', $userRefundTotal);

                    // payload: user_id, saldo devolvido e carta escolhida
                    foreach ($refundByCard as $cid => $amount) {
                        $amount = round((float)$amount, 2);
                        $usersPayload[] = [
                            'user_id'  => (int)$user->id,
                            'card_id'  => (int)$cid,
                            'refunded' => $amount,
                        ];
                        $refundedTotal += $amount;
                    }
                }

                $refundedTotal = round($refundedTotal, 2);

                DB::table('games_canceled')->insert([
                    'game_id' => $game->id,
                    'room_id' => $game->room_id,
                    'canceled_by' => $request->user()->id,
                    'payload' => json_encode([
                        'rollback_type' => $cardFilter ? 'card' : 'game',
                        'target' => [
                            'card_id' => $cardFilter,
                        ],
                        'game' => [
                            'game_id' => (int)$game->id,
                            'room_id' => (int)$game->room_id,
                            'card_left' => $game->card_left,
                            'card_right' => $game->card_right,
                            'card_winner' => $game->card_winner ?? null,
                            'finished_at' => $game->finished_at,
                        ],
                        'summary' => [
                            'bets_total' => (int)$processedBets,
                            'users_affected' => (int)collect($usersPayload)->pluck('user_id')->unique()->count(),
                            'refunded_total' => $refundedTotal,
                        ],
                        'users' => $usersPayload,
                        'executed_at' => $now->toDateTimeString(),
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $game->update([
                    'finished_at' => $now,
                ]);

                return [
                    'processed_bets' => $processedBets,
                    'refunded_total' => $refundedTotal,
                    'users_items' => count($usersPayload),
                ];
            }, 5);

            return response()->json([
                'success' => true,
                'message' => 'Rollback executed successfully',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to rollback bets',
            ], 400);
        }
    }
}

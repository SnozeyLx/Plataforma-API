<?php

namespace App\Jobs;

use App\Models\Bet;
use App\Models\Game;
use App\Models\User;
use App\Models\Points;
use App\Enums\BetStatus;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessGameFinish implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    protected $gameId;
    protected $cardId;

    public function __construct($gameId, $cardId)
    {
        $this->gameId = $gameId;
        $this->cardId = $cardId;
    }

    public function handle()
    {
        DB::transaction(function () {

            $game = Game::lockForUpdate()->findOrFail($this->gameId);

            if ($game->finished_at !== null) {
                return;
            }

            $allUserIds = Bet::where('game_id', $game->id)
                ->distinct()
                ->pluck('user_id');

            $winnerUsers = User::whereHas('bets', function ($q) use ($game) {
                $q->where("card_id", $this->cardId)
                    ->where("game_id", $game->id);
            })->lockForUpdate()->get();

            foreach ($winnerUsers as $user) {

                $bet = $user->bets()
                    ->where("card_id", $this->cardId)
                    ->where("game_id", $game->id)
                    ->where("status", BetStatus::Pending)
                    ->lockForUpdate()
                    ->get();

                $sum = 0;

                Bet::where("game_id", $game->id)
                    ->where("card_id", $this->cardId)
                    ->where("status", BetStatus::Pending)
                    ->update(["status" => BetStatus::Win]);
                $sum += $bet->value * 1.95;


                $user->increment('balance', $sum);

                Points::create([
                    'user_id' => $user->id,
                    'points'  => 0.50,
                ]);
            }

            Bet::where("game_id", $game->id)
                ->where("card_id", "!=", $this->cardId)
                ->where("status", BetStatus::Pending)
                ->update(["status" => BetStatus::Loss]);
            $sum += $bet->value * 1.95;

            $bet->update(['status' => BetStatus::Win]);


            foreach ($allUserIds as $uid) {
                Points::create([
                    'user_id' => $uid,
                    'points'  => 2.00,
                ]);
            }

            $game->update([
                "card_winner" => $this->cardId,
                "finished_at" => Carbon::now()
            ]);
        }, 5);
    }
}

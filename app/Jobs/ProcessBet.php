<?php

namespace App\Jobs;

use App\Models\Bet;
use App\Models\User;
use App\Models\Points;
use App\Actions\Wallet\DebitAction;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ProcessBet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 30;

    protected $data;
    protected $userId;

    public function __construct($data, $userId)
    {
        $this->data = $data;
        $this->userId = $userId;
    }

  public function handle()
{
    Log::info('ProcessBet START', [
        'user_id' => $this->userId,
        'game_id' => $this->data['game_id'],
        'value' => $this->data['value'],
    ]);

    DB::transaction(function () {

        $user = User::where('id', $this->userId)
            ->lockForUpdate()
            ->firstOrFail();

        $exists = Bet::where('user_id', $user->id)
            ->where('game_id', $this->data['game_id'])
            ->exists();

        if ($exists) {
            Log::warning('ProcessBet DUPLICATE', [
                'user_id' => $user->id,
                'game_id' => $this->data['game_id'],
            ]);
            return;
        }

        (new DebitAction)->handle($user, $this->data['value']);

        $bet = Bet::create([
            'room_id' => $this->data['room_id'],
            'game_id' => $this->data['game_id'],
            'user_id' => $user->id,
            'card_id' => $this->data['card_id'],
            'value'   => $this->data['value'],
        ]);

        Points::create([
            'user_id' => $user->id,
            'points'  => round($bet->value * 0.75, 2),
        ]);
    });

    Log::info('ProcessBet END', [
        'user_id' => $this->userId
    ]);
}
}

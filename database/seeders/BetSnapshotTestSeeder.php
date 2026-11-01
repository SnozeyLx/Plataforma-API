<?php

namespace Database\Seeders;

use App\Enums\BetStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Room;
use App\Models\Game;
use App\Models\Card;
use App\Models\Bet;
use App\Models\BetSnapshot;

class BetSnapshotTestSeeder extends Seeder
{
    public function run(): void
    {

        // usuários
        $user1 = User::create([
            'name' => 'Player 1',
            'email' => 'p1@test.com',
            'password' => bcrypt('123456'),
            'balance' => 200
        ]);

        $user2 = User::create([
            'name' => 'Player 2',
            'email' => 'p2@test.com',
            'password' => bcrypt('123456'),
            'balance' => 200
        ]);

        $user3 = User::create([
            'name' => 'Player 3',
            'email' => 'p3@test.com',
            'password' => bcrypt('123456'),
            'balance' => 200
        ]);

        // room (corrigido: user_id obrigatório)
        $room = Room::create([
            'user_id' => $user1->id,
             'live_url' => 'https://youtube.com/watch?v=' . Str::random(10),
        ]);

        // cartas existentes
        $cardLeft = Card::first();
        $cardRight = Card::skip(1)->first();

        // game
        $game = Game::create([
            'room_id' => $room->id,
            'user_id' => $user1->id,
            'uuid' => Str::uuid(),
            'card_left' => $cardLeft->id,
            'card_right' => $cardRight->id
        ]);

        // bets
        $bet1 = Bet::create([
            'room_id' => $room->id,
            'game_id' => $game->id,
            'user_id' => $user1->id,
            'card_id' => $cardLeft->id,
            'value' => 50,
           'status' => BetStatus::Pending,
        ]);

        $bet2 = Bet::create([
            'room_id' => $room->id,
            'game_id' => $game->id,
            'user_id' => $user2->id,
            'card_id' => $cardRight->id,
            'value' => 30,
          'status' => BetStatus::Pending,
        ]);

        $bet3 = Bet::create([
            'room_id' => $room->id,
            'game_id' => $game->id,
            'user_id' => $user3->id,
            'card_id' => $cardLeft->id,
            'value' => 40,
            'status' => BetStatus::Pending,
        ]);

        // snapshots
        BetSnapshot::create([
            'bet_id' => $bet1->id,
            'room_id' => $room->id,
            'game_id' => $game->id,
            'user_id' => $user1->id,
            'card_id' => $cardLeft->id,
            'bet_value' => 50,
            'balance_before' => 200
        ]);

        BetSnapshot::create([
            'bet_id' => $bet2->id,
            'room_id' => $room->id,
            'game_id' => $game->id,
            'user_id' => $user2->id,
            'card_id' => $cardRight->id,
            'bet_value' => 30,
            'balance_before' => 200
        ]);

        BetSnapshot::create([
            'bet_id' => $bet3->id,
            'room_id' => $room->id,
            'game_id' => $game->id,
            'user_id' => $user3->id,
            'card_id' => $cardLeft->id,
            'bet_value' => 40,
            'balance_before' => 200
        ]);
    }
}

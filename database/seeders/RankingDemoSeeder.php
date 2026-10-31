<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Room;
use App\Models\Game;
use App\Models\Bet;
use App\Models\Card;
use App\Enums\BetStatus;
use Carbon\Carbon;

class RankingDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() < 20) {
            User::factory()->count(20)->create();
        }

        $users = User::pluck('id')->all();

        if (Card::count() === 0) {
            Card::create(['name' => 'Ace of Hearts']);
            Card::create(['name' => 'King of Spades']);
            Card::create(['name' => 'Queen of Diamonds']);
            Card::create(['name' => 'Jack of Clubs']);
        }

        $cards = Card::pluck('id')->all();

        if (Room::count() < 5) {
            for ($i = 0; $i < 5; $i++) {
                Room::create([
                    'user_id' => $users[array_rand($users)],
                    'live_url' => 'https://example.com/live/' . uniqid()
                ]);
            }
        }

        $rooms = Room::pluck('id')->all();

        $this->createGamesAndBets($rooms, $users, $cards, Carbon::today(), Carbon::now(), 4);
        $this->createGamesAndBets($rooms, $users, $cards, Carbon::now()->startOfWeek(), Carbon::now(), 6);
        $this->createGamesAndBets($rooms, $users, $cards, Carbon::now()->startOfMonth(), Carbon::now(), 10);
    }

    private function createGamesAndBets(array $rooms, array $users, array $cards, Carbon $from, Carbon $to, int $gamesCount): void
    {
        for ($i = 0; $i < $gamesCount; $i++) {
            $roomId = $rooms[array_rand($rooms)];
            $userId = $users[array_rand($users)];
            $cardLeft = $cards[array_rand($cards)];
            $cardRight = $cards[array_rand($cards)];
            while ($cardRight === $cardLeft) {
                $cardRight = $cards[array_rand($cards)];
            }

            $game = Game::create([
                'card_left' => $cardLeft,
                'card_right' => $cardRight,
                'card_winner' => null,
                'user_id' => $userId,
                'room_id' => $roomId,
                'finished_at' => null,
            ]);

            $betsPerGame = rand(5, 15);
            $createdAt = Carbon::createFromTimestamp(rand($from->timestamp, $to->timestamp));

            $winningCard = rand(0, 1) === 0 ? $cardLeft : $cardRight;

            for ($b = 0; $b < $betsPerGame; $b++) {
                $bettor = $users[array_rand($users)];
                $chosenCard = rand(0, 1) === 0 ? $cardLeft : $cardRight;
                $value = rand(10, 500);
                $status = $chosenCard === $winningCard ? BetStatus::Win : BetStatus::Loss;

                Bet::create([
                    'game_id' => $game->id,
                    'user_id' => $bettor,
                    'room_id' => $roomId,
                    'card_id' => $chosenCard,
                    'value' => $value,
                    'status' => $status,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            $game->card_winner = $winningCard;
            $game->finished_at = $createdAt->copy()->addMinutes(rand(10, 120));
            $game->save();
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\BetStatus;
use App\Services\UserPointsService;
use App\Services\VipService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VipController extends Controller
{
    public function vip(
        Request $request,
        VipService $vipService,
        UserPointsService $pointsService
    ) {
        $user = $request->user();
        $userId = $user->id;

        // Regra: 4 dias sem apostar => zera points
        $pointsService->resetIfInactive($user);
        $user->refresh();

        // VIP (derivado de users.points)
        $vip = $vipService->progress($user);

        // Base: bets do usuário em games finalizados
        $base = DB::table('bets')
            ->join('games', 'bets.game_id', '=', 'games.id')
            ->where('bets.user_id', $userId)
            ->whereNotNull('games.finished_at');

        // ======================
        // STATS (para os cards)
        // ======================

        // wins/losses por BET
        $wins = (int) (clone $base)->where('bets.status', BetStatus::Win)->count();
        $losses = (int) (clone $base)->where('bets.status', BetStatus::Loss)->count();
        $totalDecided = $wins + $losses;
        $winRate = $totalDecided > 0 ? (int) round(($wins / $totalDecided) * 100) : 0;

        // lucro total líquido (mesma regra do finish())
        $profitRow = (clone $base)->selectRaw("
            COALESCE(SUM(
                CASE
                    WHEN bets.status = ? THEN
                        CASE
                            WHEN bets.card_id = games.card_left  THEN (bets.value * 2.0)  - bets.value
                            WHEN bets.card_id = games.card_right THEN (bets.value * 0.95) - bets.value
                            ELSE 0
                        END
                    WHEN bets.status = ? THEN -bets.value
                    ELSE 0
                END
            ), 0) AS total_profit
        ", [BetStatus::Win, BetStatus::Loss])->first();

        $totalProfit = (float) ($profitRow->total_profit ?? 0);

        // dias jogando (dias distintos com jogo finalizado em que ele apostou)
        $daysPlaying = (int) (clone $base)
            ->selectRaw("COUNT(DISTINCT DATE(games.finished_at)) as days_played")
            ->value('days_played');

        // ======================
        // ACHIEVEMENTS (mesma lógica do AchievementController)
        // ======================
        $achievements = $this->buildAchievements($userId, (int)($vip['vip_level'] ?? 0));

        // trophies
        $trophiesTotal = count($achievements);
        $trophiesUnlocked = count(array_filter($achievements, fn($a) => $a['unlocked']));

        // ======================
        // XP (no seu layout você está usando "XP", mas aqui vamos usar POINTS como XP visual)
        // xpCurrent = vip.point (no seu JSON veio "point")
        // xpTotal = vip.next_threshold
        // ======================
        $xpCurrent = (float) DB::table('points')
            ->where('user_id', $userId)
            ->sum('points'); // soma todos os registros

        $xpTotal = (float) ($vip['next_threshold'] ?? 1000);

        return response()->json([
            'success' => true,

            // bloco vip como você já tem
            'vip' => $vip,

            // stats para os cards
            'stats' => [
                'wins' => $wins,
                'winRate' => $winRate,
                'totalProfit' => round($totalProfit, 2),
                'daysPlaying' => $daysPlaying,
            ],

            // XP visual (usa points)
            'xp' => [
                'current' => $xpCurrent,
                'total' => $xpTotal,
            ],

            // trophies
            'trophies' => [
                'unlocked' => $trophiesUnlocked,
                'total' => $trophiesTotal,
            ],

            // achievements já no formato que o front precisa
            'achievements' => array_map(function ($a) {
                return [
                    'id' => $a['id'],
                    'title' => $a['title'],
                    'desc' => $a['description'],
                    'unlocked' => $a['unlocked'],
                    'progress' => (int) round($a['percent']),
                ];
            }, $achievements),
        ]);
    }

    private function buildAchievements(int $userId, int $vipLevel): array
    {
        $userGames = DB::table('bets')
            ->join('games', 'bets.game_id', '=', 'games.id')
            ->where('bets.user_id', $userId)
            ->whereNotNull('games.finished_at');

        $hasAnyBet = DB::table('bets')->where('user_id', $userId)->exists();

        $hasAnyWin = DB::table('bets')
            ->where('user_id', $userId)
            ->where('status', BetStatus::Win)
            ->exists();

        $totalBetsCount = DB::table('bets')->where('user_id', $userId)->count();

        $winsByGameCount = (clone $userGames)
            ->where('bets.status', BetStatus::Win)
            ->distinct('games.id')
            ->count('games.id');

        $totalBetValue = (float) (clone $userGames)->sum('bets.value');

        $totalProfit = (float) (clone $userGames)->selectRaw("
            COALESCE(SUM(
                CASE
                    WHEN bets.status = ? THEN
                        CASE
                            WHEN bets.card_id = games.card_left  THEN (bets.value * 2.0)  - bets.value
                            WHEN bets.card_id = games.card_right THEN (bets.value * 0.95) - bets.value
                            ELSE 0
                        END
                    WHEN bets.status = ? THEN -bets.value
                    ELSE 0
                END
            ), 0) AS total_profit
        ", [BetStatus::Win, BetStatus::Loss])->value('total_profit');

        $profitByGame = (clone $userGames)
            ->selectRaw("
                games.id as game_id,
                DATE(games.finished_at) as played_day,
                SUM(
                    CASE
                        WHEN bets.status = ? THEN
                            CASE
                                WHEN bets.card_id = games.card_left  THEN (bets.value * 2.0)  - bets.value
                                WHEN bets.card_id = games.card_right THEN (bets.value * 0.95) - bets.value
                                ELSE 0
                            END
                        WHEN bets.status = ? THEN -bets.value
                        ELSE 0
                    END
                ) AS profit_in_game,
                games.finished_at
            ", [BetStatus::Win, BetStatus::Loss])
            ->groupBy('games.id', 'played_day', 'games.finished_at')
            ->orderBy('games.finished_at')
            ->get();

        $maxProfitInGame = (float) ($profitByGame->max('profit_in_game') ?? 0);

        $roundResults = DB::table('bets')
            ->join('games', 'bets.game_id', '=', 'games.id')
            ->where('bets.user_id', $userId)
            ->whereNotNull('games.finished_at')
            ->selectRaw("
                games.id as game_id,
                MAX(CASE WHEN bets.status = ? THEN 1 ELSE 0 END) as won_round,
                games.finished_at
            ", [BetStatus::Win])
            ->groupBy('games.id', 'games.finished_at')
            ->orderBy('games.finished_at')
            ->get();

        $bestWinStreak = 0;
        $currentStreak = 0;
        foreach ($roundResults as $r) {
            if ((int)$r->won_round === 1) {
                $currentStreak++;
                $bestWinStreak = max($bestWinStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        $playedDays = $profitByGame
            ->pluck('played_day')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $bestDayStreak = $this->bestConsecutiveDayStreak($playedDays);

        return [
            $this->ach('first_round', 'Primeira Ronda', 'Faça sua primeira aposta', $hasAnyBet ? 1 : 0, 1),
            $this->ach('first_win', 'Primeira Vitória', 'Ganhe sua primeira aposta', $hasAnyWin ? 1 : 0, 1),
            $this->ach('lucky_streak', 'Sequência de Sorte', 'Vença 3 apostas seguidas', min($bestWinStreak, 3), 3),
            $this->ach('king_of_round', 'Rei da Ronda', 'Vença 1000 rodadas no total', $winsByGameCount, 1000),
            $this->ach('flash_bettor', 'Apostador Relâmpago', 'Faça 50 apostas rápidas', $totalBetsCount, 50),
            $this->ach('profit_master', 'Mestre dos Lucros', 'Acumule R$ 50.000 em ganhos', max(0, $totalProfit), 50000),
            $this->ach('daily_player_30', 'Jogador Diário', 'Jogue por 30 dias seguidos', min($bestDayStreak, 30), 30),
            $this->ach('single_round_10k', 'R$ 10.000 em uma única ronda', 'Badge exclusivo', max(0, $maxProfitInGame), 10000),
            $this->ach('daily_player_7', 'Jogador Diário', 'Jogue por 7 dias consecutivos', min($bestDayStreak, 7), 7),
            $this->ach('elite_round', 'Elite Ronda', 'Acumule R$ 10.000 em apostas', $totalBetValue, 10000),
            $this->ach('vip_status', 'Status VIP', 'Alcance nível VIP', min($vipLevel, 1), 1),
        ];
    }

    private function ach(string $id, string $title, string $description, $current, $target): array
    {
        $currentNum = (float) $current;
        $targetNum  = (float) $target;

        $percent = $targetNum > 0 ? round(($currentNum / $targetNum) * 100, 2) : 0;
        if ($percent > 100) $percent = 100;

        return [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'current' => $currentNum,
            'target' => $targetNum,
            'percent' => $percent,
            'unlocked' => $currentNum >= $targetNum,
        ];
    }

    private function bestConsecutiveDayStreak(array $days): int
    {
        if (count($days) === 0) return 0;
        sort($days);

        $best = 1;
        $cur = 1;

        for ($i = 1; $i < count($days); $i++) {
            $prev = Carbon::parse($days[$i - 1]);
            $now  = Carbon::parse($days[$i]);

            if ($prev->copy()->addDay()->isSameDay($now)) {
                $cur++;
                $best = max($best, $cur);
            } else {
                $cur = 1;
            }
        }
        return $best;
    }
}

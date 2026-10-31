<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Bet;
use App\Models\Points;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Carbon\Carbon;

class RankingController extends Controller
{
    private function queryRanking(Carbon $from, Carbon $to, int $limit)
    {
        // Subquery: soma de pontos por usuário (tabela points)
        $pointsSub = Points::query()
            ->select('user_id', DB::raw('COALESCE(SUM(points),0) as total_points'))
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->groupBy('user_id');

        // Subquery: métricas de bets por usuário (tabela bets)
        $betsSub = Bet::query()
            ->select(
                'user_id',
                DB::raw('COALESCE(MAX(value),0) as biggest_bet'),
                DB::raw('COUNT(id) as bets_count'),
                DB::raw('COALESCE(SUM(CASE WHEN status = "win" THEN 1 ELSE 0 END),0) as wins_count'),
                DB::raw('COUNT(DISTINCT game_id) as games_count'),
                DB::raw('CASE WHEN COUNT(id) > 0 THEN (SUM(CASE WHEN status = "win" THEN 1 ELSE 0 END) * 100.0 / COUNT(id)) ELSE 0 END as percentage'),
                // ✅ MAIOR VITÓRIA (maior value somente quando status=win)
                DB::raw('COALESCE(MAX(CASE WHEN status = "win" THEN value ELSE 0 END),0) as value')
            )
            ->whereBetween('created_at', [$from->toDateTimeString(), $to->toDateTimeString()])
            ->groupBy('user_id');

        $users = User::query()
            ->select(
                'users.id',
                'users.name',
                'users.image_profile as avatar',
                DB::raw('COALESCE(p.total_points,0) as points'),
                //DB::raw('COALESCE(MAX(CASE WHEN status = "win" THEN value ELSE 0 END),0) as biggest_win'),
                DB::raw('COALESCE(b.biggest_bet,0) as biggest_bet'),
                DB::raw('COALESCE(b.bets_count,0) as bets_count'),
                DB::raw('COALESCE(b.wins_count,0) as wins_count'),
                DB::raw('COALESCE(b.games_count,0) as games_count'),
                DB::raw('COALESCE(b.percentage,0) as percentage'),
                DB::raw('COALESCE(b.value,0) as value'),
                //DB::raw('COALESCE(b.biggest_win,0) as biggest_win')
            )
            ->leftJoinSub($pointsSub, 'p', function ($join) {
                $join->on('p.user_id', '=', 'users.id');
            })
            ->leftJoinSub($betsSub, 'b', function ($join) {
                $join->on('b.user_id', '=', 'users.id');
            })
            // mostra só quem tem pontos no período (se quiser mostrar todos, remove)
            ->whereRaw('COALESCE(p.total_points,0) > 0')
            ->orderByDesc('points')
            ->limit($limit)
            ->get();

        $currentUserId = null;
        try {
            $authUser = JWTAuth::parseToken()->authenticate();
            if ($authUser) $currentUserId = $authUser->id;
        } catch (\Throwable $e) {}

        return $users->map(function ($u) use ($currentUserId) {
            $avatar = $u->avatar;
            if ($avatar) {
                if (!Str::startsWith($avatar, ['http://', 'https://'])) {
                    $avatar = url('storage/' . ltrim($avatar, '/'));
                }
            } else {
                $avatar = null;
            }

            return [
                'id' => (int) $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar, // mantém como está (se você usa número 1..24 no front)
                'points' => round((float) $u->points, 2),

                // ✅ compatibilidade opcional (se seu front ainda usa point_daily/weekly/monthly)
                // você pode manter no daily/weekly/monthly (ver abaixo)

                'biggest_bet' => round((float) $u->biggest_bet, 2),
                'value' => round((float) $u->value, 2), // ✅ MAIOR VITÓRIA
                'bets_count' => (int) $u->bets_count,
                'wins_count' => (int) $u->wins_count,
                'percentage' => round((float) $u->percentage, 2),
                'is_current_user' => $currentUserId ? ((int)$u->id === (int)$currentUserId) : false,
            ];
        });
    }

    public function daily(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $from = Carbon::today();
        $to = Carbon::now();

        $ranking = $this->queryRanking($from, $to, $limit);

        // ✅ se você quiser manter o front antigo funcionando (point_daily),
        // pode devolver também esse campo como alias:
        $ranking = $ranking->map(function ($u) {
            $u['point_daily'] = $u['points'];
            return $u;
        });

        return [
            'success' => true,
            'period' => 'daily',
            'ranking' => $ranking,
        ];
    }

    public function weekly(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $from = Carbon::now()->startOfWeek();
        $to = Carbon::now();

        $ranking = $this->queryRanking($from, $to, $limit);

        $ranking = $ranking->map(function ($u) {
            $u['point_weekly'] = $u['points'];
            return $u;
        });

        return [
            'success' => true,
            'period' => 'weekly',
            'ranking' => $ranking,
        ];
    }

    public function monthly(Request $request)
    {
        $limit = (int) $request->query('limit', 10);
        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now();

        $ranking = $this->queryRanking($from, $to, $limit);

        $ranking = $ranking->map(function ($u) {
            $u['point_monthly'] = $u['points'];
            return $u;
        });

        return [
            'success' => true,
            'period' => 'monthly',
            'ranking' => $ranking,
        ];
    }
}

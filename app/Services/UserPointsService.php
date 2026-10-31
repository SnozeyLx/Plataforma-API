<?php

namespace App\Services;

use App\Models\Points;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserPointsService
{
    public function storeSnapshot(User $user, array $calc): void
    {
        Points::create([
            'user_id' => $user->id,
            'points'  => $calc['total_points'],
        ]);
    }

    // se você ainda usa isso:
    public function resetIfInactive(User $user): void
    {
        $lastBetAt = DB::table('bets')
            ->where('user_id', $user->id)
            ->max('created_at');

        if (!$lastBetAt) return;

        $daysInactive = Carbon::parse($lastBetAt)->diffInDays(now());

        if ($daysInactive >= 1 && (float)$user->points > 0) {
            Points::update(['points' => 0]);
        }
    }
}

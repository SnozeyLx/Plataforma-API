<?php

namespace App\Http\Controllers;

use App\Services\PointsRulesService;
use App\Services\UserPointsService;
use Illuminate\Http\Request;

class PointsController extends Controller
{
    public function points(Request $request, $rules, UserPointsService $userPoints)
    {
        $user = $request->user();

        $userPoints->resetIfInactive($user);
        $user->refresh();

        $from = $request->query('from'); // YYYY-MM-DD opcional
        $to   = $request->query('to');   // YYYY-MM-DD opcional

        $calc = $rules->calculate($user->id, $from, $to);

        // opcional: manter sincronizado no banco
        $user->update(['points' => $calc['total_points']]);

        return response()->json([
            'success' => true,
            'points' => $calc,
        ]);
    }
}

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

        $from = $request->query('from');
        $to   = $request->query('to');

        $calc = $rules->calculate($user->id, $from, $to);

        // grava na tabela points (snapshot ou update)
        $userPoints->storeSnapshot($user, $calc);
        // OU: updateOrCreate(...) se você preferir 1 registro por usuário

        return response()->json([
            'success' => true,
            'points' => $calc,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\RewardType;
use App\Models\Roulette;
use Illuminate\Http\Request;

class RouletteController extends Controller
{
    public function index()
    {
        return response()->json(
            Roulette::orderBy('id', 'desc')->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->roulette_coin < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient roulette coins.'
            ], 400);
        }

        $value = $this->pickWeightedValue();


        // Desconta moeda
        $user->decrement('roulette_coin');

        // Define recompensa
        switch (true) {

            case ($value <= 12):
                $reward = RewardType::Reward_1;
                $user->increment('balance', 10); // Adiciona R$10,00 ao saldo
                break;
            case ($value <= 25):
                $reward = RewardType::Reward_2;
                break;
            case ($value <= 38):
                $reward = RewardType::Reward_3;
                $user->increment('balance', 20); // Adiciona R$20,00 ao saldo
                break;
            case ($value <= 51):
                $reward = RewardType::Reward_4;
                break;
            case ($value <= 64):
                $reward = RewardType::Reward_5;
                $user->increment('balance', 50); // Adiciona R$50,00 ao saldo
                break;
            case ($value <= 77):
                $reward = RewardType::Reward_6;
                break;
            case ($value <= 90):
                $reward = RewardType::Reward_7;
                $user->increment('balance', 100); // Adiciona R$100,00 ao saldo
                break;
            default:
                $reward = RewardType::Reward_8;
        }

        // Salva histórico da roleta
        Roulette::create([
            'image_profile' => $user->image_profile,
            'name' => $user->name,
            'reward' => $reward->value,
        ]);

        return response()->json([
            'success' => true,
            'value' => $value,
            'reward' => $reward->value,
            'reward_value' => $reward->value
        ]);
    }


    private function pickWeightedValue(): int
{
    $enableIphone  = filter_var(env('ROULETTE_ENABLE_IPHONE', false), FILTER_VALIDATE_BOOLEAN);
    $enablePs5     = filter_var(env('ROULETTE_ENABLE_PS5', false), FILTER_VALIDATE_BOOLEAN);
    $enableMacbook = filter_var(env('ROULETTE_ENABLE_MACBOOK', false), FILTER_VALIDATE_BOOLEAN);

    $segments = [
        ['min' => 1,  'max' => 12,  'weight' => 25], // R$10
        ['min' => 13, 'max' => 25,  'weight' => 1,  'enabled' => $enableIphone],  // iPhone
        ['min' => 26, 'max' => 38,  'weight' => 18], // R$20
        ['min' => 39, 'max' => 51,  'weight' => 1,  'enabled' => $enablePs5],     // PS5
        ['min' => 52, 'max' => 64,  'weight' => 6],  // R$50
        ['min' => 65, 'max' => 77,  'weight' => 45], // perdeu
        ['min' => 78, 'max' => 90,  'weight' => 3],  // R$100
        ['min' => 91, 'max' => 100, 'weight' => 1,  'enabled' => $enableMacbook], // Macbook
    ];

    // remove os desativados
    $segments = array_values(array_filter($segments, fn($s) => !isset($s['enabled']) || $s['enabled'] === true));

    $total = array_sum(array_column($segments, 'weight'));
    $r = random_int(1, $total);

    foreach ($segments as $seg) {
        $r -= $seg['weight'];
        if ($r <= 0) return random_int($seg['min'], $seg['max']);
    }

    return random_int(65, 77);
}


public function spinConfig()
{
    $enableIphone  = filter_var(env('ROULETTE_ENABLE_IPHONE', false), FILTER_VALIDATE_BOOLEAN);
    $enablePs5     = filter_var(env('ROULETTE_ENABLE_PS5', false), FILTER_VALIDATE_BOOLEAN);
    $enableMacbook = filter_var(env('ROULETTE_ENABLE_MACBOOK', false), FILTER_VALIDATE_BOOLEAN);

    $segments = [
        ['label' => 'Ganhou R$ 10,00', 'range' => [1, 12]],
        ['label' => $enableIphone  ? 'Ganhou Iphone' : '', 'range' => [13, 25]],
        ['label' => 'Ganhou R$ 20,00', 'range' => [26, 38]],
        ['label' => $enablePs5     ? 'Ganhou um PS5' : '', 'range' => [39, 51]],
        ['label' => 'Ganhou R$ 50,00', 'range' => [52, 64]],
        ['label' => 'Não foi dessa vez', 'range' => [65, 77]],
        ['label' => 'Ganhou R$ 100,00', 'range' => [78, 90]],
        ['label' => $enableMacbook ? 'Ganhou Macbook Apple' : '', 'range' => [91, 100]],
    ];

    $spinConfig = [
        ['label' => 'Ganhou R$ 10,00', 'duration' => 2.5, 'spins' => 3],
        ['label' => $enableIphone  ? 'Ganhou Iphone' : '', 'duration' => 3.0, 'spins' => 4],
        ['label' => 'Ganhou R$ 20,00', 'duration' => 3.5, 'spins' => 5],
        ['label' => $enablePs5     ? 'Ganhou um PS5' : '', 'duration' => 4.0, 'spins' => 6],
        ['label' => 'Ganhou R$ 50,00', 'duration' => 4.5, 'spins' => 7],
        ['label' => 'Não foi dessa vez', 'duration' => 5.0, 'spins' => 8],
        ['label' => 'Ganhou R$ 100,00', 'duration' => 5.5, 'spins' => 9],
        ['label' => $enableMacbook ? 'Ganhou Macbook Apple' : '', 'duration' => 6.0, 'spins' => 10],
    ];

    return response()->json([
        'segments' => $segments,
        'spinConfig' => $spinConfig,
    ]);
}



}

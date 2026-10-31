<?php

namespace App\Services;

use App\Models\User;

class VipService
{
    public function thresholds(): array
    {
        return config('vip.thresholds', [
            0 => 0,
            1 => 500,
            2 => 1100,
            3 => 1800,
            4 => 2600,
            5 => 3500,
            6 => 4500,
            7 => 5600,
            8 => 6800,
            9 => 8100,
            10 => 9500,
            11 => 11000,
            12 => 12600,
            13 => 14400,
            14 => 16400,
            15 => 18600,
            16 => 21000,
            17 => 23600,
            18 => 26400,
            19 => 29400,
            20 => 32600,
            21 => 36000,
            22 => 39600,
            23 => 43400,
            24 => 47400,
            25 => 51600,
            26 => 56100,
            27 => 61100,
            28 => 66600,
            29 => 72600,
            30 => 79100,
            31 => 86100,
            32 => 93600,
            33 => 101600,
            34 => 110100,
            35 => 119100,
            36 => 128600,
            37 => 138600,
            38 => 149100,
            39 => 160100,
            40 => 172100,
            41 => 185100,
            42 => 199100,
            43 => 214100,
            44 => 230100,
            45 => 247100,
            46 => 265100,
            47 => 284100,
            48 => 304100,
            49 => 325100,
            50 => 347100,
            51 => 370100,
            52 => 395100,
            53 => 445100,
        ]);
    }

    public function levelFromPoints(float $point): int
    {
        $level = 0;
        foreach ($this->thresholds() as $lvl => $min) {
            if ($point >= $min) $level = (int) $lvl;
        }
        return $level;
    }

    public function progress(User $user): array
    {
        $thresholds = $this->thresholds();

        $point = (float)($user->point ?? 0);
        $level = $this->levelFromPoints($point);

        $currentMin = $thresholds[$level] ?? 0;
        $nextLevel = $level + 1;
        $nextMin = $thresholds[$nextLevel] ?? null;

        if ($nextMin === null) {
            return [
                'is_vip' => $level >= 1,
                'vip_level' => $level,
                'point' => $point,
                'next_level' => null,
                'next_threshold' => null,
                'progress_percent' => 100,
            ];
        }

        $range = max(1, $nextMin - $currentMin);
        $done = min($range, max(0, $point - $currentMin));
        $percent = round(($done / $range) * 100, 2);

        return [
            'is_vip' => $level >= 1,
            'vip_level' => $level,
            'point' => $point,
            'next_level' => $nextLevel,
            'next_threshold' => $nextMin,
            'progress_percent' => $percent,
        ];
    }

    // Dados extras só quando VIP
    public function vipExtras(int $vipLevel): array
    {
        // você pode personalizar por nível
        $benefitsByLevel = [
            1 => [
                'badge' => 'VIP Bronze',
                'benefits' => ['Suporte prioritário', 'Badge no perfil'],
            ],
            2 => [
                'badge' => 'VIP Prata',
                'benefits' => ['Cashback melhor', 'Suporte prioritário', 'Badge no perfil'],
            ],
            3 => [
                'badge' => 'VIP Ouro',
                'benefits' => ['Cashback premium', 'Saque mais rápido', 'Badge no perfil'],
            ],
        ];

        return $benefitsByLevel[$vipLevel] ?? [
            'badge' => 'VIP',
            'benefits' => [],
        ];
    }
}

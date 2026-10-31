<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Roulette;

class RouletteDemoSeeder extends Seeder
{

    private function createRoulettes(string $name, string $image_profile, string $reward): void
    {
        Roulette::create([
            'name' => $name,
            'image_profile' => $image_profile,
            'reward' => $reward
        ]);
    }
    public function run(): void
    {
        $this->createRoulettes(name: 'bobina', image_profile: '1', reward: 'R$10,00');
        $this->createRoulettes(name: 'bobina', image_profile: '2', reward: 'R$20,00');
        $this->createRoulettes(name: 'bobina', image_profile: '3', reward: 'R$30,00');
        $this->createRoulettes(name: 'bobina', image_profile: '4', reward: 'R$40,00');
        $this->createRoulettes(name: 'bobina', image_profile: '5', reward: 'R$50,00');
        $this->createRoulettes(name: 'bobina', image_profile: '6', reward: 'R$60,00');
        $this->createRoulettes(name: 'bobina', image_profile: '7', reward: 'R$70,00');
        $this->createRoulettes(name: 'bobina', image_profile: '8', reward: 'R$80,00');
    }
}

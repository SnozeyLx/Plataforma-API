<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Database\Seeders\RankingDemoSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RankingDemoSeeder::class);
        $this->call(RouletteDemoSeeder::class);
            $this->call(BetSnapshotTestSeeder::class);
    }
}

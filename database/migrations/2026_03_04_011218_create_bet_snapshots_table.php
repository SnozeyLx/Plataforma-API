<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bet_snapshots', function (Blueprint $table) {
            $table->id();


            $table->foreignId('bet_id')->constrained('bets')->cascadeOnDelete();


            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();


            $table->foreignId('card_id')->constrained('cards');
            $table->decimal('bet_value', 18, 2);

            $table->decimal('balance_before', 18, 2);
            $table->decimal('balance_after', 18, 2)->nullable();

            $table->timestamps();
            $table->unique('bet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bet_snapshots');
    }
};

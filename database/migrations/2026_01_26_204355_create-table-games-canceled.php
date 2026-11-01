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
          Schema::create('games_canceled', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('game_id');
            $table->unsignedBigInteger('room_id');
            $table->unsignedBigInteger('canceled_by'); // admin/suporte que executou

            $table->json('payload');

            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games');
            $table->foreign('room_id')->references('id')->on('rooms');
            $table->foreign('canceled_by')->references('id')->on('users');

            $table->index(['game_id']);
            $table->index(['room_id']);
            $table->index(['canceled_by']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games_canceled');
    }
};

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
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('card_left')->nullable()->change();
            $table->foreignId('card_right')->nullable()->change();
            $table->foreignId('card_winner')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('card_left')->change();
            $table->foreignId('card_right')->change();
            $table->foreignId('card_winner')->change();
        });
    }
};

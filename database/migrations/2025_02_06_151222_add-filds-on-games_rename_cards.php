<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('games')) {
            Schema::table("games", function (Blueprint $table) {
                // Verifique se a coluna 'card_1' existe antes de renomeá-la
                if (Schema::hasColumn('games', 'card_1')) {
                    $table->renameColumn('card_1', 'card_left');
                }

                // Verifique se a coluna 'card_2' existe antes de renomeá-la
                if (Schema::hasColumn('games', 'card_2')) {
                    $table->renameColumn('card_2', 'card_right');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('games')) {
            Schema::table("games", function (Blueprint $table) {
                // Verifique se a coluna 'card_left' existe antes de renomeá-la
                if (Schema::hasColumn('games', 'card_left')) {
                    $table->renameColumn('card_left', 'card_1');
                }

                // Verifique se a coluna 'card_right' existe antes de renomeá-la
                if (Schema::hasColumn('games', 'card_right')) {
                    $table->renameColumn('card_right', 'card_2');
                }
            });
        }
    }
};

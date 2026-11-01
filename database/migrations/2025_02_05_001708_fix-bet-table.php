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
        Schema::dropColumns("bets", ["card_select","value_bet"]);
        Schema::table("bets", function (Blueprint $table) {
            $table->foreignId('card_id')->after('user_id')->references('id')->on('cards')->onDelete('cascade');
        });
        Schema::table("bets", function (Blueprint $table) {
            $table->decimal("value", 15, 2)->after("card_id");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("bets", function (Blueprint $table) {
            $table->dropConstrainedForeignId("card_id");
            $table->dropColumn("value");
        });
        Schema::table('bets', function (Blueprint $table) {
            $table->string('card_select');
            $table->decimal('value_bet', 8, 2);
        });
    }
};

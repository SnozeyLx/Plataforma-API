<?php

use App\Enums\UserRole;
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
        Schema::table("games", callback: function (Blueprint $table) {
            $table->integer("room_id")->nullable()->after("card_2");
            $table->integer("status")->nullable()->after("card_2");
            $table->integer("user_id")->nullable()->after("card_2");
            $table->string("card_winner")->nullable()->after("card_2");

        });

    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("games", callback: function (Blueprint $table) {
            $table->dropColumn("card_2");
        });
    }
};

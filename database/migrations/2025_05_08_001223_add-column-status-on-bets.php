<?php

use App\Enums\BetStatus;
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
        Schema::table("bets", function (Blueprint $table) {
            $table->string("status")->default(BetStatus::Pending)->after("value");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("bets", function (Blueprint $table) {
            $table->dropColumn("status");
        });
    }
};

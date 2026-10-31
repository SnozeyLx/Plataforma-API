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
        Schema::table('users', function(Blueprint $table) {
            $table->float('point')->after('balance')->nullable();
            $table->integer('roulette_coin')->after('point')->nullable();
            $table->float('point_daily')->after('roulette_coin')->nullable();
            $table->float('point_weekly')->after('point_daily')->nullable();
            $table->float('point_monthly')->after('point_weekly')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function(Blueprint $table) {
            $table->dropColumn('point');
            $table->dropColumn('roulette_coin');
            $table->dropColumn('point_daily');
            $table->dropColumn('point_weekly');
            $table->dropColumn('point_monthly');
        });
    }
};

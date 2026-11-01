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
        Schema::table("room", callback: function (Blueprint $table) {
            $table->string("status")->nullable()->after("live_url");

        });

    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("room", callback: function (Blueprint $table) {
            $table->dropColumn("live_url");
        });
    }
};

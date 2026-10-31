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
        Schema::table("users", function (Blueprint $table) {
            $table->string("birth_day")->nullable()->after("role"); // Removido o default incorreto
            $table->string('phone')->nullable()->after("role");
            $table->string('address')->nullable()->after("role");
            $table->string('city')->nullable()->after("role");
            $table->string('state')->nullable()->after("role");
            $table->string('cpf')->nullable()->after("role");
            $table->string('post_code')->nullable()->after("role");
            $table->string('pix')->nullable()->after("role");
            $table->string('image_profile')->nullable()->after("role");
        });

    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("users", function (Blueprint $table) {
            $table->dropColumn("role");
        });
    }
};

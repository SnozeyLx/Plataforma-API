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
        Schema::create('balance_statements', function (Blueprint $table) {
            $table->id();
            $table->string("uuid")->unique();
            $table->string("type");
            $table->decimal("value", 15, 2);
            $table->string("external_id")->nullable();
            $table->text("description")->nullable();
            $table->text("qr_code")->nullable();
            $table->text("qr_code_image")->nullable();
            $table->text("payment_link")->nullable();
            $table->datetime("expiration")->nullable();
            $table->string("status");
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_statements');
    }
};

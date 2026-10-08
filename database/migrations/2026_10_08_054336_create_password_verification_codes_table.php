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
        Schema::create('password_verification_codes', function (Blueprint $table) {
            $table->id();
            // sha256 of the 6-digit code, never the code itself: a leaked
            // database row must not be enough to change the password.
            $table->string('code_hash', 64)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Hash of the new password, staged here so the password only
            // changes after the emailed code is confirmed.
            $table->string('pending_password_hash')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('used')->default(false);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_verification_codes');
    }
};

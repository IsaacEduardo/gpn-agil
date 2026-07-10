<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Definições gerais de notificação por utilizador: quiet hours (com fuso) e digest.
     */
    public function up(): void
    {
        Schema::create('notification_user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->time('quiet_start')->default('22:00:00');
            $table->time('quiet_end')->default('07:00:00');
            $table->string('timezone')->nullable();
            $table->boolean('digest_enabled')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_user_settings');
    }
};

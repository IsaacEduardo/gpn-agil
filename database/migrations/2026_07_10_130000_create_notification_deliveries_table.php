<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trilha de auditoria de entrega de notificações: um registo por canal e por
     * tentativa (enviado/falhado), para rastreabilidade e prova de entrega.
     */
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable()->index();
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');
            $table->string('notification_type');   // FQCN da notificação
            $table->string('event_type')->nullable(); // 'type' canónico do payload
            $table->string('channel');              // database | mail | broadcast | ...
            $table->string('status');               // sent | failed
            $table->text('response')->nullable();   // motivo/erro em caso de falha
            $table->timestamp('created_at')->nullable();

            $table->index(['notifiable_type', 'notifiable_id']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};

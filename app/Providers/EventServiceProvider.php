<?php

namespace App\Providers;

use App\Domain\DocumentManagement\Events\DocumentoAssinadoEvent;
use App\Events\DocumentoArquivado;
use App\Listeners\ApplyNotificationPreferences;
use App\Listeners\LogDocumentoArquivado;
use App\Listeners\LogDocumentoAssinado;
use App\Events\TaskAssigned;
use App\Listeners\NotificationAuditSubscriber;
use App\Listeners\SendTaskAssignedNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        DocumentoArquivado::class => [
            LogDocumentoArquivado::class,
        ],
        TaskAssigned::class => [
            SendTaskAssignedNotification::class,
        ],
        NotificationSending::class => [
            ApplyNotificationPreferences::class,
        ],
        // Evento de Domínio DDD — disparado quando um documento é assinado digitalmente.
        DocumentoAssinadoEvent::class => [
            LogDocumentoAssinado::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        NotificationAuditSubscriber::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

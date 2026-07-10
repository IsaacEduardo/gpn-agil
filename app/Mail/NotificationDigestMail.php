<?php

namespace App\Mail;

use App\Models\User;
use App\Support\NotificationPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Resumo diário das notificações não lidas de um utilizador.
 */
class NotificationDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \Illuminate\Notifications\DatabaseNotification>  $notifications
     */
    public function __construct(public User $user, public Collection $notifications)
    {
        $this->queue = 'notifications';
    }

    public function envelope(): Envelope
    {
        $total = $this->notifications->count();

        return new Envelope(
            subject: "Resumo de notificações: {$total} por ler",
        );
    }

    public function content(): Content
    {
        $items = $this->notifications->map(function ($n) {
            $p = NotificationPresenter::present($n->data ?? []);
            $p['created_at_human'] = optional($n->created_at)->diffForHumans();

            return $p;
        });

        return new Content(
            markdown: 'emails.notifications.digest',
            with: [
                'user' => $this->user,
                'items' => $items,
            ],
        );
    }
}

<?php

namespace App\Console\Commands;

use App\Mail\NotificationDigestMail;
use App\Models\NotificationUserSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envia o resumo diário de notificações não lidas aos utilizadores que optaram
 * por recebê-lo (digest_enabled) e que têm e-mail e notificações por ler.
 */
class SendNotificationDigest extends Command
{
    protected $signature = 'notifications:digest {--limit=20 : Máximo de itens por resumo}';

    protected $description = 'Envia o resumo diário de notificações não lidas aos utilizadores subscritos';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $sent = 0;

        NotificationUserSetting::query()
            ->where('digest_enabled', true)
            ->with('user')
            ->chunk(200, function ($settings) use (&$sent, $limit) {
                foreach ($settings as $setting) {
                    $user = $setting->user;
                    if (! $user || empty($user->email)) {
                        continue;
                    }

                    $unread = $user->unreadNotifications()->latest()->limit($limit)->get();
                    if ($unread->isEmpty()) {
                        continue;
                    }

                    try {
                        Mail::to($user->email)->send(new NotificationDigestMail($user, $unread));
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::error('Falha ao enviar digest de notificações', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Resumos enviados: {$sent}");
        Log::info("notifications:digest concluído. Enviados: {$sent}");

        return Command::SUCCESS;
    }
}
